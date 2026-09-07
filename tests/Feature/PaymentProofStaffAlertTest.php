<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: el operador solo se enteraba de que llegó un comprobante
 * si entraba manualmente al detalle del pedido en el panel. Ahora, al
 * registrar el comprobante, se le avisa por WhatsApp a los números de
 * despacho configurados (mismo canal que las alertas de pedidos demorados).
 */
class PaymentProofStaffAlertTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    public function test_staff_is_alerted_by_whatsapp_when_a_payment_proof_is_received(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['delivery_dispatch_numbers' => '593987000001, 593987000002'],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Gregorio Osorio']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 9.00,
            'payment_method' => 'transferencia',
            'metadata' => ['order_details' => ['order_number' => 'ORD-001']],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $this->invoke($service, 'registrarComprobantePago', [$contact, $cart, [
            'id' => 'wamid.proof.'.uniqid(),
            'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg'],
            'timestamp' => (string) now()->timestamp,
        ], 'image']);

        Http::assertSent(function ($request) {
            $body = $request['text']['body'] ?? '';

            return str_contains($body, 'Comprobante de pago recibido')
                && str_contains($body, 'ORD-001')
                && str_contains($body, 'Gregorio Osorio');
        });
    }

    public function test_nothing_is_sent_when_no_dispatch_numbers_are_configured(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create(['business_profile_id' => $profile->id, 'metadata' => []]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 9.00,
            'payment_method' => 'transferencia', 'metadata' => ['order_details' => ['order_number' => 'ORD-002']],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $this->invoke($service, 'registrarComprobantePago', [$contact, $cart, [
            'id' => 'wamid.proof.'.uniqid(),
            'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg'],
            'timestamp' => (string) now()->timestamp,
        ], 'image']);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Comprobante de pago recibido'));
    }
}
