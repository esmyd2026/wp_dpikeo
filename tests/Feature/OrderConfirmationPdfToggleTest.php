<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\OrderConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: agregar una opción en Configuración del chatbot para
 * apagar el envío del PDF de la orden como archivo adjunto -- el mensaje de
 * confirmación ya trae un enlace para verlo/descargarlo, así que el archivo
 * en sí ahora es opcional.
 */
class OrderConfirmationPdfToggleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappCart} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 10]);

        return [$profile, $cart];
    }

    public function test_the_pdf_document_is_sent_by_default(): void
    {
        // Cada llamada necesita un message_id distinto -- WhatsappMessage.message_id
        // es único, y este flujo manda más de un mensaje (documento + botones).
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['id' => 'media-'.uniqid(), 'messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, $cart] = $this->fixture();

        app(OrderConfirmationService::class)->sendToClient($cart);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/media'));
    }

    public function test_the_pdf_document_is_skipped_when_disabled_but_the_confirmation_message_still_has_the_link(): void
    {
        // Cada llamada necesita un message_id distinto -- WhatsappMessage.message_id
        // es único, y este flujo manda más de un mensaje (documento + botones).
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['id' => 'media-'.uniqid(), 'messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $cart] = $this->fixture();
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['send_order_pdf_document' => false],
        ]);

        $result = app(OrderConfirmationService::class)->sendToClient($cart);

        $this->assertTrue($result);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/media'));
        // json_encode() escaparía las barras ("orden\/1\/pdf"), por eso se
        // compara contra el texto del cuerpo directo en vez de codificarlo.
        Http::assertSent(fn ($request) => str_contains($request['interactive']['body']['text'] ?? '', 'orden/'.$cart->id.'/pdf'));
        $this->assertTrue((bool) ($cart->fresh()->metadata['awaiting_client_confirmation'] ?? false));
        $this->assertNotNull($cart->fresh()->metadata['confirmation_pdf_url'] ?? null);
    }

    public function test_admin_can_toggle_the_pdf_document_setting_from_the_chatbot_config_panel(): void
    {
        [$profile] = $this->fixture();
        $template = MessageTemplate::firstOrCreate(
            ['key' => 'order_confirmation_ticket'],
            ['name' => 'Confirmación de pedido', 'body' => 'Confirma tu pedido {{order_number}}']
        );
        $company = \App\Models\Company::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Empresa PDF', 'slug' => 'empresa-pdf', 'status' => 'active',
        ]);
        $profile->update(['company_id' => $company->id]);
        app(\App\Services\PermissionService::class)->syncDefinitions();
        app(\App\Services\PermissionService::class)->syncDefaultRoles();
        $role = \App\Models\Role::where('slug', 'admin')->firstOrFail();
        $user = \App\Models\User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.chatbot.message-templates.update', $template), [
                'body' => $template->body,
                'is_enabled' => 1,
                'send_order_pdf_document' => 0,
            ])
            ->assertRedirect();

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile->id)->first();
        $this->assertFalse($config->send_order_pdf_document);
    }
}
