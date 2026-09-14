<?php

namespace Tests\Feature;

use App\Mail\StorefrontPasswordResetCode;
use App\Models\Company;
use App\Models\OrderAlertEvent;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Mi cuenta" del storefront: registro/login por teléfono+contraseña sobre
 * el mismo WhatsappContact que ya usa el bot, con historial/estado de
 * pedidos -- pedido explícito en vivo del usuario ("que se pueda crear una
 * cuenta como cliente... y que le permita ver sus cuenta y el estado de su
 * pedidos y el historial").
 */
class StorefrontAccountTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(string $phoneSuffix): array
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593999'.$phoneSuffix, 'phone_number_id' => 'DPIKEOS-ACC-'.$phoneSuffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        return [$company, $profile];
    }

    public function test_a_new_customer_can_register_and_is_logged_in_immediately(): void
    {
        [$company] = $this->makeProfile('100001');

        $response = $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Ana Torres',
            'phone' => '0991112222',
            'password' => 'secreto1',
            'password_confirmation' => 'secreto1',
        ]);

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('customer.name', 'Ana Torres');
        $this->assertDatabaseHas('whatsapp_contacts', ['phone_number' => '0991112222', 'name' => 'Ana Torres']);

        $me = $this->getJson("/tienda/{$company->slug}/cuenta/yo");
        $me->assertOk()->assertJsonPath('customer.phone', '0991112222');
    }

    public function test_registering_twice_with_the_same_phone_is_rejected(): void
    {
        [$company] = $this->makeProfile('100002');

        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Ana Torres', 'phone' => '0991112223', 'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $response = $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Otra Persona', 'phone' => '0991112223', 'password' => 'otraclave', 'password_confirmation' => 'otraclave',
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_a_contact_created_by_an_order_without_a_password_can_claim_its_account(): void
    {
        [$company, $profile] = $this->makeProfile('100003');
        WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '0991112224', 'name' => 'Cliente Bot',
        ]);

        $response = $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Cliente Bot', 'phone' => '0991112224', 'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(1, WhatsappContact::where('phone_number', '0991112224')->count());
    }

    public function test_login_with_correct_credentials_works_and_wrong_password_is_rejected(): void
    {
        [$company] = $this->makeProfile('100004');
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Ana Torres', 'phone' => '0991112225', 'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();
        $this->postJson("/tienda/{$company->slug}/cuenta/salir")->assertOk();

        $bad = $this->postJson("/tienda/{$company->slug}/cuenta/entrar", ['phone' => '0991112225', 'password' => 'incorrecta']);
        $bad->assertOk()->assertJsonPath('ok', false);

        $good = $this->postJson("/tienda/{$company->slug}/cuenta/entrar", ['phone' => '0991112225', 'password' => 'secreto1']);
        $good->assertOk()->assertJsonPath('ok', true)->assertJsonPath('customer.name', 'Ana Torres');
    }

    public function test_customer_can_recover_password_with_a_whatsapp_code(): void
    {
        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.password-reset']]], 200),
        ]);
        [$company, $profile] = $this->makeProfile('100009');
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '0991112231',
            'name' => 'Cliente Recuperación',
            'password' => Hash::make('clave-anterior'),
            'status' => 'active',
        ]);

        $requested = $this->postJson("/tienda/{$company->slug}/cuenta/recuperar", [
            'phone' => '0991112231',
        ]);

        $requested->assertOk()->assertJsonPath('ok', true);
        $sentText = collect(Http::recorded())
            ->map(fn (array $exchange) => data_get($exchange[0]->data(), 'text.body'))
            ->filter()
            ->first();
        $this->assertIsString($sentText);
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $sentText);
        preg_match('/\b(\d{6})\b/', $sentText, $matches);

        $reset = $this->postJson("/tienda/{$company->slug}/cuenta/restablecer", [
            'phone' => '0991112231',
            'code' => $matches[1],
            'password' => 'clave-nueva',
            'password_confirmation' => 'clave-nueva',
        ]);

        $reset->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('customer.name', 'Cliente Recuperación');
        $this->assertTrue(Hash::check('clave-nueva', $contact->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => "storefront:{$profile->id}:0991112231",
        ]);
        $this->getJson("/tienda/{$company->slug}/cuenta/yo")
            ->assertOk()
            ->assertJsonPath('customer.phone', '0991112231');
    }

    public function test_customer_can_request_the_recovery_code_by_email_and_it_is_saved(): void
    {
        Mail::fake();
        [$company, $profile] = $this->makeProfile('100011');
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '0991112233',
            'name' => 'Cliente Correo',
            'password' => Hash::make('clave-anterior'),
            'status' => 'active',
        ]);

        $requested = $this->postJson("/tienda/{$company->slug}/cuenta/recuperar", [
            'phone' => '0991112233',
            'channel' => 'email',
            'email' => 'cliente@example.com',
        ]);

        $requested->assertOk()->assertJsonPath('ok', true);
        Mail::assertSent(StorefrontPasswordResetCode::class, fn ($mail) => $mail->hasTo('cliente@example.com'));
        $this->assertSame('cliente@example.com', $contact->fresh()->metadata['email'] ?? null);
    }

    public function test_requesting_the_code_by_email_without_one_on_file_asks_for_it_without_sending_mail(): void
    {
        Mail::fake();
        [$company, $profile] = $this->makeProfile('100012');
        WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '0991112234',
            'name' => 'Cliente Sin Correo',
            'password' => Hash::make('clave-anterior'),
            'status' => 'active',
        ]);

        $requested = $this->postJson("/tienda/{$company->slug}/cuenta/recuperar", [
            'phone' => '0991112234',
            'channel' => 'email',
        ]);

        $requested->assertOk()->assertJsonPath('ok', false)->assertJsonPath('needs_email', true);
        Mail::assertNothingSent();
    }

    public function test_requesting_by_email_reuses_an_already_saved_email_without_needing_it_again(): void
    {
        Mail::fake();
        [$company, $profile] = $this->makeProfile('100013');
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '0991112235',
            'name' => 'Cliente Con Correo',
            'password' => Hash::make('clave-anterior'),
            'status' => 'active',
            'metadata' => ['email' => 'ya-guardado@example.com'],
        ]);

        $requested = $this->postJson("/tienda/{$company->slug}/cuenta/recuperar", [
            'phone' => '0991112235',
            'channel' => 'email',
        ]);

        $requested->assertOk()->assertJsonPath('ok', true);
        Mail::assertSent(StorefrontPasswordResetCode::class, fn ($mail) => $mail->hasTo('ya-guardado@example.com'));
        $this->assertSame('ya-guardado@example.com', $contact->fresh()->metadata['email'] ?? null);
    }

    public function test_an_incorrect_recovery_code_does_not_change_the_password(): void
    {
        [$company, $profile] = $this->makeProfile('100010');
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '0991112232',
            'name' => 'Cliente Seguro',
            'password' => Hash::make('clave-original'),
            'status' => 'active',
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => "storefront:{$profile->id}:0991112232",
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson("/tienda/{$company->slug}/cuenta/restablecer", [
            'phone' => '0991112232',
            'code' => '654321',
            'password' => 'clave-intrusa',
            'password_confirmation' => 'clave-intrusa',
        ]);

        $response->assertOk()->assertJsonPath('ok', false);
        $this->assertTrue(Hash::check('clave-original', $contact->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => "storefront:{$profile->id}:0991112232",
        ]);
    }

    public function test_order_history_and_status_are_scoped_to_the_logged_in_customer(): void
    {
        [$company, $profile] = $this->makeProfile('100005');
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Ana Torres', 'phone' => '0991112226', 'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $contact = WhatsappContact::where('phone_number', '0991112226')->firstOrFail();
        $otherContact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '0991119999', 'name' => 'Otro Cliente']);

        $customerOrder = WhatsappCart::create([
            'contact_id' => $contact->id,
            'total' => 15.5,
            'status' => WhatsappCart::STATUS_PREPARING,
            'payment_method' => 'efectivo',
            'metadata' => [
                'service_type' => 'delivery',
                'delivery' => ['address' => 'Av. Principal 123', 'reference' => 'Casa azul'],
                'operational_timeline' => [
                    ['from' => 'pending', 'to' => 'confirmed', 'at' => now()->subMinutes(10)->toIso8601String(), 'user_id' => 99],
                    ['from' => 'confirmed', 'to' => 'preparing', 'at' => now()->toIso8601String(), 'user_id' => 99],
                ],
            ],
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => 'Menú de prueba',
            'type' => 'list',
            'content' => 'Productos',
            'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'business_profile_id' => $profile->id,
            'title' => 'Combos',
            'action_id' => 'combos',
            'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'business_profile_id' => $profile->id,
            'category' => 'Combos',
            'sku' => 'COMBO-DETALLE',
            'name' => 'Combo especial',
            'price' => 15.5,
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 5,
        ]);
        $customerOrder->items()->create([
            'whatsapp_price_id' => $product->id,
            'name' => 'Combo especial',
            'price' => 15.5,
            'quantity' => 1,
            'line_note' => 'Opción: Grande · Extras: Salsa especial',
        ]);
        WhatsappCart::create(['contact_id' => $contact->id, 'total' => 8, 'status' => WhatsappCart::STATUS_CANCELLED]);
        WhatsappCart::create(['contact_id' => $contact->id, 'total' => 3, 'status' => 'active']);
        WhatsappCart::create(['contact_id' => $otherContact->id, 'total' => 99, 'status' => WhatsappCart::STATUS_PAID]);

        $response = $this->getJson("/tienda/{$company->slug}/cuenta/pedidos");

        $response->assertOk();
        $orders = $response->json('orders');
        $this->assertCount(2, $orders);
        $this->assertEqualsCanonicalizing(['En preparación', 'Cancelado'], array_column($orders, 'status_label'));
        $detail = collect($orders)->firstWhere('id', $customerOrder->id);
        $this->assertSame('Combo especial', data_get($detail, 'items.0.name'));
        $this->assertSame('Opción: Grande · Extras: Salsa especial', data_get($detail, 'items.0.selection'));
        $this->assertSame('Delivery', data_get($detail, 'fulfillment.label'));
        $this->assertSame('Av. Principal 123', data_get($detail, 'fulfillment.address'));
        $this->assertSame(['created', 'confirmed', 'preparing'], array_column($detail['timeline'], 'status'));
        $this->assertSame('Estamos preparando tu pedido', data_get($detail, 'next_action.title'));
    }

    public function test_orders_endpoint_requires_login(): void
    {
        [$company] = $this->makeProfile('100006');

        $this->getJson("/tienda/{$company->slug}/cuenta/pedidos")->assertStatus(401);
    }

    public function test_customer_can_manage_invoice_and_upload_transfer_proof_from_the_storefront(): void
    {
        Storage::fake('local');
        [$company, $profile] = $this->makeProfile('100008');
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'welcome_message' => 'Hola',
            'default_response' => 'Ayuda',
            'metadata' => ['bank_transfer_instructions' => "Banco Pichincha\nCuenta 123456"],
            'is_active' => true,
        ]);
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Ana Torres', 'phone' => '0991112230', 'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();
        $contact = WhatsappContact::where('phone_number', '0991112230')->firstOrFail();
        $order = WhatsappCart::create([
            'contact_id' => $contact->id,
            'total' => 25,
            'status' => WhatsappCart::STATUS_PAYMENT_PENDING,
            'payment_method' => 'transferencia',
            'payment_status' => 'awaiting_proof',
            'metadata' => ['pending_payment_proof' => true],
        ]);

        $this->putJson("/tienda/{$company->slug}/cuenta/pedidos/{$order->id}/facturacion", [
            'requires_invoice' => true,
            'billing_type' => 'ruc',
            'billing_id' => '0999999999001',
            'billing_legal_name' => 'Ana Torres S.A.S.',
            'billing_address' => 'Guayaquil, Ecuador',
            'billing_email' => 'factura@example.com',
        ])->assertOk()->assertJsonPath('ok', true);

        $upload = $this->post("/tienda/{$company->slug}/cuenta/pedidos/{$order->id}/comprobante", [
            'proof' => UploadedFile::fake()->image('comprobante.jpg', 800, 600),
        ], ['Accept' => 'application/json']);

        $upload->assertOk()->assertJsonPath('ok', true);
        $order->refresh();
        $this->assertTrue($order->requires_invoice);
        $this->assertSame('data_ready', $order->invoice_status);
        $this->assertSame('0999999999001', $order->invoice_data['billing_id']);
        $this->assertTrue($order->hasPaymentProof());
        $this->assertSame('proof_submitted', $order->payment_status);
        Storage::disk('local')->assertExists($order->metadata['payment_proof']['backup_path']);
        $this->assertDatabaseHas('order_alert_events', ['whatsapp_cart_id' => $order->id, 'event_type' => OrderAlertEvent::TYPE_PAYMENT_PROOF]);

        $orders = $this->getJson("/tienda/{$company->slug}/cuenta/pedidos");
        $orders->assertOk()
            ->assertJsonPath('orders.0.payment.proof_submitted', true)
            ->assertJsonPath('orders.0.payment.bank_instructions', "Banco Pichincha\nCuenta 123456")
            ->assertJsonPath('orders.0.invoice.requires_invoice', true)
            ->assertJsonPath('orders.0.invoice.data.billing_legal_name', 'Ana Torres S.A.S.');
    }

    public function test_logging_in_on_one_company_does_not_authenticate_on_another_companys_storefront(): void
    {
        [$companyA] = $this->makeProfile('100007');
        $companyB = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Otra Tienda', 'slug' => 'otra-tienda-'.uniqid(), 'status' => 'active']);
        WhatsappBusinessProfile::create([
            'company_id' => $companyB->id, 'business_name' => 'OTRA', 'display_name' => 'OTRA',
            'phone_number' => '593888100007', 'phone_number_id' => 'OTRA-100007',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $this->postJson("/tienda/{$companyA->slug}/cuenta/registro", [
            'name' => 'Ana Torres', 'phone' => '0991112227', 'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $response = $this->getJson("/tienda/{$companyB->slug}/cuenta/yo");

        $response->assertOk()->assertJsonPath('customer', null);
    }

    public function test_google_login_creates_a_pending_registration_and_requests_only_the_phone(): void
    {
        [$company] = $this->makeProfile('100020');
        $company->storefrontSetting()->updateOrCreate([], [
            'google_oauth_client_id' => 'google-client-id',
            'google_oauth_client_secret' => 'google-client-secret',
        ]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-20', 'email' => 'ana@example.com',
                'email_verified' => true, 'name' => 'Ana Google',
            ]),
        ]);

        $this->get("/tienda/{$company->slug}")
            ->assertOk()
            ->assertSee('Iniciar sesión con Google')
            ->assertSee('Crear cuenta con Google')
            ->assertSee('fill="#4285F4"', false)
            ->assertDontSee('google-client-secret');

        $redirect = $this->get("/tienda/{$company->slug}/cuenta/google");
        $redirect->assertRedirect();
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $callback = $this->get("/tienda/{$company->slug}/cuenta/google/callback?code=authorization-code&state=".urlencode($query['state']));
        $callback->assertRedirectContains('google=complete');

        $completed = $this->postJson("/tienda/{$company->slug}/cuenta/google/completar", ['phone' => '0991112299']);
        $completed->assertOk()->assertJsonPath('ok', true)->assertJsonPath('customer.email', 'ana@example.com');
        $this->assertDatabaseHas('whatsapp_contacts', [
            'phone_number' => '0991112299', 'google_id' => 'google-user-20', 'google_email' => 'ana@example.com',
        ]);
        $this->getJson("/tienda/{$company->slug}/cuenta/yo")
            ->assertOk()->assertJsonPath('customer.name', 'Ana Google');
    }

    public function test_google_login_links_an_existing_contact_by_verified_email(): void
    {
        [$company, $profile] = $this->makeProfile('100021');
        $company->storefrontSetting()->updateOrCreate([], [
            'google_oauth_client_id' => 'google-client-id',
            'google_oauth_client_secret' => 'google-client-secret',
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '0991112288',
            'name' => 'Cliente existente',
            'billing_email' => 'cliente@example.com',
            'password' => Hash::make('secreto1'),
        ]);
        WhatsappCart::create([
            'contact_id' => $contact->id,
            'total' => 12.50,
            'status' => WhatsappCart::STATUS_COMPLETED,
        ]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-21', 'email' => 'cliente@example.com',
                'email_verified' => true, 'name' => 'Nombre de Google',
            ]),
        ]);

        $redirect = $this->get("/tienda/{$company->slug}/cuenta/google");
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get("/tienda/{$company->slug}/cuenta/google/callback?code=authorization-code&state=".urlencode($query['state']))
            ->assertRedirectContains('google=success');

        $this->assertSame('google-user-21', $contact->fresh()->google_id);
        $this->getJson("/tienda/{$company->slug}/cuenta/yo")
            ->assertOk()
            ->assertJsonPath('customer.phone', '0991112288')
            ->assertJsonPath('customer.email', 'cliente@example.com')
            ->assertJsonPath('customer.purchases_count', 1);
    }
}
