<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: al pedir por el micrositio ("Armar lista") y
 * pagar por transferencia, el mensaje de "Tu pedido está casi listo" (el
 * único que ve el cliente antes de tocar Confirmar en ese flujo) nunca traía
 * los datos de la cuenta bancaria -- solo el chat clásico los mostraba, en
 * el "Resumen" antes de confirmar (buildPaymentOrderReview). El cliente se
 * quedaba sin saber a qué cuenta transferir.
 */
class BulkOrderConfirmationShowsBankDetailsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['bank_transfer_instructions' => "Banco Pichincha\nCta. Ahorros 123456\nDpikeos S.A.S"],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        return [$profile, $contact];
    }

    public function test_bulk_order_confirmation_message_includes_bank_details_when_paying_by_transfer(): void
    {
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 12.75,
            'payment_method' => 'transferencia', 'metadata' => ['source' => 'bulk_web_form'],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $payload = $service->buildOrderConfirmationPayload($cart, 'https://pedidos.example.com/orden/1/pdf');

        $body = $payload['interactive']['body']['text'];
        $this->assertStringContainsString('Datos para tu transferencia', $body);
        $this->assertStringContainsString('Banco Pichincha', $body);
        $this->assertStringContainsString('Cta. Ahorros 123456', $body);
    }

    public function test_bulk_order_confirmation_message_has_no_bank_block_for_cash_orders(): void
    {
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 12.75,
            'payment_method' => 'efectivo', 'metadata' => ['source' => 'bulk_web_form'],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $payload = $service->buildOrderConfirmationPayload($cart, 'https://pedidos.example.com/orden/1/pdf');

        $this->assertStringNotContainsString('Datos para tu transferencia', $payload['interactive']['body']['text']);
    }
}
