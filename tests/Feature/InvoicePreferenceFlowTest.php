<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowNode;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: al final del pedido, una vez que ya quedó pagado (o
 * confirmado, si nunca pasa por "Pagado" -- caso de efectivo), el bot
 * pregunta factura o consumidor final. Con factura pide 4 datos (nombre,
 * RUC/cédula, dirección, correo) en un solo mensaje, o los reusa de una
 * factura anterior con confirmación. El paso se puede desactivar igual que
 * "para llevar o servir" (ver CheckoutStepConfigIgnoresPublishStateTest).
 *
 * El disparo real vive en OrderLifecycleService::transition() (ver
 * maybeTriggerInvoicePreference), que corre en un job "afterResponse" -- en
 * los tests que invocan el servicio por reflexión (sin pasar por el kernel
 * HTTP real) hay que forzar ese job con $this->app->terminate().
 */
class InvoicePreferenceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        return [$profile, $contact];
    }

    private function service(WhatsappBusinessProfile $profile): WhatsappService
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        return $service;
    }

    public function test_confirming_a_cash_order_triggers_the_invoice_preference_prompt(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'active', 'total' => 10, 'payment_method' => 'efectivo',
        ]);

        $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);
        $this->app->terminate();

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'invoice_type_factura_'));
        $this->assertTrue((bool) ($cart->fresh()->metadata['invoice_prompt_sent'] ?? false));
    }

    public function test_choosing_consumidor_final_marks_the_order_and_needs_no_more_data(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $response = $this->invoke($service, 'chooseInvoiceType', [$contact, $cart->id, 'consumidor_final']);

        $this->assertSame('text', $response['type']);
        $this->assertFalse((bool) $cart->fresh()->requires_invoice);
        $this->assertSame('none', $cart->fresh()->invoice_status);
    }

    public function test_choosing_factura_with_no_saved_data_asks_for_the_4_fields_in_one_message(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $response = $this->invoke($service, 'chooseInvoiceType', [$contact, $cart->id, 'factura']);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('nombre completo', $response['text']['body']);
        $this->assertStringContainsString('cédula o RUC', $response['text']['body']);
        $this->assertTrue((bool) $cart->fresh()->requires_invoice);
        $this->assertSame('requested', $cart->fresh()->invoice_status);
        $this->assertTrue((bool) ($cart->fresh()->metadata['awaiting_invoice_data'] ?? false));
    }

    public function test_sending_the_4_labeled_fields_in_one_message_saves_the_invoice_data_and_syncs_the_contact(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10,
            'metadata' => ['awaiting_invoice_data' => true], 'requires_invoice' => true, 'invoice_status' => 'requested',
        ]);

        $text = "Nombre: Juan Pérez\nRUC o cédula: 0912345678\nDirección: Av. Siempre Viva 123\nCorreo: juan@example.com";
        $response = $this->invoke($service, 'handleInvoiceDataMessage', [$contact, $cart, $text]);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('Juan Pérez', $response['text']['body']);

        $freshCart = $cart->fresh();
        $this->assertSame('data_ready', $freshCart->invoice_status);
        $this->assertSame('Juan Pérez', $freshCart->invoice_data['billing_legal_name']);
        $this->assertSame('0912345678', $freshCart->invoice_data['billing_id']);
        $this->assertSame('cedula', $freshCart->invoice_data['billing_type']);
        $this->assertSame('Av. Siempre Viva 123', $freshCart->invoice_data['address']);
        $this->assertSame('juan@example.com', $freshCart->invoice_data['email']);
        $this->assertArrayNotHasKey('awaiting_invoice_data', $freshCart->metadata ?? []);

        $freshContact = $contact->fresh();
        $this->assertSame('0912345678', $freshContact->billing_id);
        $this->assertSame('Juan Pérez', $freshContact->billing_legal_name);
    }

    /**
     * Bug real reportado en vivo: los clientes no se ponen a escribir
     * etiquetas ni saltos de línea, mandan todo junto en una sola línea
     * ("gregorio osorio 0962398350001 aborada iv gregorio_osorio@gmail.com").
     * El parser debe entenderlo igual, usando el correo y la racha de
     * dígitos (cédula/RUC) como referencias inequívocas para separar nombre
     * y dirección alrededor de la cédula/RUC.
     */
    public function test_a_single_unlabeled_line_is_understood_without_any_formatting(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10,
            'metadata' => ['awaiting_invoice_data' => true], 'requires_invoice' => true, 'invoice_status' => 'requested',
        ]);

        $text = 'gregorio osorio 0962398350001 aborada iv gregorio_osorio@gmail.com';
        $response = $this->invoke($service, 'handleInvoiceDataMessage', [$contact, $cart, $text]);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('gregorio osorio', $response['text']['body']);

        $freshCart = $cart->fresh();
        $this->assertSame('data_ready', $freshCart->invoice_status);
        $this->assertSame('gregorio osorio', $freshCart->invoice_data['billing_legal_name']);
        $this->assertSame('0962398350001', $freshCart->invoice_data['billing_id']);
        $this->assertSame('ruc', $freshCart->invoice_data['billing_type']);
        $this->assertSame('aborada iv', $freshCart->invoice_data['address']);
        $this->assertSame('gregorio_osorio@gmail.com', $freshCart->invoice_data['email']);
    }

    public function test_a_missing_field_is_reprompted_without_losing_the_ones_already_understood(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10,
            'metadata' => ['awaiting_invoice_data' => true],
        ]);

        $text = "Nombre: Juan Pérez\nDirección: Av. Siempre Viva 123";
        $response = $this->invoke($service, 'handleInvoiceDataMessage', [$contact, $cart, $text]);

        $this->assertStringContainsString('cédula o RUC', $response['text']['body']);
        $this->assertStringContainsString('correo', $response['text']['body']);
        $this->assertTrue((bool) ($cart->fresh()->metadata['awaiting_invoice_data'] ?? false));
        $this->assertNull($cart->fresh()->invoice_data);
    }

    public function test_a_repeat_customer_with_a_previous_invoice_is_offered_reuse_instead_of_asking_again(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        $previousCart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 10,
            'invoice_data' => [
                'billing_legal_name' => 'Juan Pérez', 'billing_id' => '0912345678',
                'billing_type' => 'cedula', 'address' => 'Av. Siempre Viva 123', 'email' => 'juan@example.com',
            ],
            'invoice_status' => 'data_ready', 'requires_invoice' => true,
        ]);
        $newCart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 20]);

        $response = $this->invoke($service, 'chooseInvoiceType', [$contact, $newCart->id, 'factura']);

        $this->assertSame('interactive', $response['type']);
        $this->assertStringContainsString('Juan Pérez', $response['interactive']['body']['text']);
        $this->assertStringContainsString('¿Facturamos con estos mismos datos?', $response['interactive']['body']['text']);
        $this->assertTrue((bool) ($newCart->fresh()->metadata['awaiting_invoice_reuse_confirmation'] ?? false));

        $confirmResponse = $this->invoke($service, 'confirmInvoiceReuse', [$contact, $newCart->id, true]);
        $this->assertSame('text', $confirmResponse['type']);
        $this->assertSame('Juan Pérez', $newCart->fresh()->invoice_data['billing_legal_name']);
        $this->assertSame('data_ready', $newCart->fresh()->invoice_status);
        $this->assertArrayNotHasKey('awaiting_invoice_reuse_confirmation', $newCart->fresh()->metadata ?? []);

        unset($previousCart);
    }

    public function test_declining_reuse_asks_for_fresh_data_instead_of_the_saved_ones(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = $this->service($profile);

        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 10,
            'invoice_data' => [
                'billing_legal_name' => 'Juan Pérez', 'billing_id' => '0912345678',
                'billing_type' => 'cedula', 'address' => 'Av. Siempre Viva 123', 'email' => 'juan@example.com',
            ],
            'invoice_status' => 'data_ready', 'requires_invoice' => true,
        ]);
        $newCart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 20,
            'metadata' => ['awaiting_invoice_reuse_confirmation' => true],
        ]);

        $response = $this->invoke($service, 'confirmInvoiceReuse', [$contact, $newCart->id, false]);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('Datos para tu factura', $response['text']['body']);
        $this->assertTrue((bool) ($newCart->fresh()->metadata['awaiting_invoice_data'] ?? false));
        $this->assertNull($newCart->fresh()->invoice_data);
    }

    public function test_disabling_the_step_with_factura_as_fixed_default_skips_the_question_but_still_collects_data(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowNode::create([
            'flow_id' => $flow->id, 'node_uuid' => 'checkout-node-uuid', 'node_type' => MarketingFlowNode::TYPE_CHECKOUT,
            'name' => 'Checkout (sistema)',
            'config' => ['steps' => ['invoice_type' => ['enabled' => false, 'default' => 'factura']]],
            'is_enabled' => true,
        ]);

        $service = $this->service($profile);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 10, 'payment_method' => 'efectivo']);

        $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);
        $this->app->terminate();

        // No debe mandar los botones de factura/consumidor (el paso está
        // desactivado), pero sí debe haber pedido los 4 datos directamente.
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'invoice_type_factura_'));
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Datos para tu factura'));
        $this->assertTrue((bool) $cart->fresh()->requires_invoice);
        $this->assertTrue((bool) ($cart->fresh()->metadata['awaiting_invoice_data'] ?? false));
    }

    public function test_disabling_the_step_with_consumidor_final_as_fixed_default_asks_nothing(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowNode::create([
            'flow_id' => $flow->id, 'node_uuid' => 'checkout-node-uuid', 'node_type' => MarketingFlowNode::TYPE_CHECKOUT,
            'name' => 'Checkout (sistema)',
            'config' => ['steps' => ['invoice_type' => ['enabled' => false, 'default' => 'consumidor_final']]],
            'is_enabled' => true,
        ]);

        $service = $this->service($profile);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 10, 'payment_method' => 'efectivo']);

        $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);
        $this->app->terminate();

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'invoice_type_factura_'));
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'RUC o c'));
        $this->assertFalse((bool) $cart->fresh()->requires_invoice);
        $this->assertSame('none', $cart->fresh()->invoice_status);
    }

    public function test_marking_an_order_as_paid_from_the_admin_panel_also_triggers_the_prompt(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        [$company, $profile, $user] = $this->adminFixture();
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990002222', 'name' => 'Cliente Panel', 'status' => 'active']);
        $contact->last_inbound_at = now();
        $contact->save();
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 15, 'payment_method' => 'transferencia',
        ]);

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->post("/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'invoice_type_factura_'));
    }

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function adminFixture(): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();

        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Factura', 'slug' => 'empresa-factura', 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => $company->name, 'display_name' => $company->name,
            'phone_number' => '593990003333', 'phone_number_id' => 'FACTURA-PHONE', 'access_token' => 'token-factura', 'status' => 'connected',
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $profile, $user];
    }
}
