<?php

namespace Tests\Feature;

use App\Models\OrderAlertEvent;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: la pantalla de Pedidos debía sonar/actualizarse
 * ante CUALQUIER cambio importante del cliente (comprobante enviado,
 * factura elegida, pedido de asesor), no solo pedidos nuevos -- el sondeo
 * viejo (AdminController::pollNewOrders) solo detecta filas NUEVAS por id,
 * nunca un cambio de metadata en un pedido ya conocido. Se agregó un log
 * liviano de eventos (OrderAlertEvent) con su propio cursor.
 */
class OrderAlertEventsTest extends TestCase
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

    public function test_receiving_a_payment_proof_logs_an_order_alert_event(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 9.0,
            'payment_method' => 'transferencia', 'metadata' => ['order_details' => ['order_number' => 'ORD-001']],
        ]);

        $service = $this->service($profile);
        $this->invoke($service, 'registrarComprobantePago', [$contact, $cart, [
            'id' => 'wamid.proof.'.uniqid(),
            'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg'],
            'timestamp' => (string) now()->timestamp,
        ], 'image']);

        $event = OrderAlertEvent::where('whatsapp_cart_id', $cart->id)->firstOrFail();
        $this->assertSame(OrderAlertEvent::TYPE_PAYMENT_PROOF, $event->event_type);
        $this->assertSame($profile->id, $event->business_profile_id);
        $this->assertSame('ORD-001', $event->payload['order_number']);
    }

    public function test_choosing_factura_logs_an_order_alert_event_and_alerts_staff(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['delivery_dispatch_numbers' => '593987000001'],
        ]);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $service = $this->service($profile);
        $this->invoke($service, 'chooseInvoiceType', [$contact, $cart->id, 'factura']);

        $event = OrderAlertEvent::where('whatsapp_cart_id', $cart->id)->firstOrFail();
        $this->assertSame(OrderAlertEvent::TYPE_INVOICE_CONFIRMED, $event->event_type);
        $this->assertSame('factura', $event->payload['invoice_type']);
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Preferencia de facturaci'));
    }

    public function test_choosing_consumidor_final_logs_an_order_alert_event(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $service = $this->service($profile);
        $this->invoke($service, 'chooseInvoiceType', [$contact, $cart->id, 'consumidor_final']);

        $event = OrderAlertEvent::where('whatsapp_cart_id', $cart->id)->firstOrFail();
        $this->assertSame(OrderAlertEvent::TYPE_INVOICE_CONFIRMED, $event->event_type);
        $this->assertSame('consumidor_final', $event->payload['invoice_type']);
    }

    public function test_agent_handoff_logs_an_order_alert_event_when_an_order_is_open(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $service = $this->service($profile);
        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'agent', 'title' => '💬 Hablar con asesor']],
        ]]);

        $event = OrderAlertEvent::where('whatsapp_cart_id', $cart->id)->firstOrFail();
        $this->assertSame(OrderAlertEvent::TYPE_AGENT_REQUEST, $event->event_type);
    }

    public function test_agent_handoff_does_not_crash_without_any_open_order(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();

        $service = $this->service($profile);
        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'agent', 'title' => '💬 Hablar con asesor']],
        ]]);

        $this->assertSame(0, OrderAlertEvent::count());
        $this->assertFalse($contact->fresh()->bot_enabled);
    }
}
