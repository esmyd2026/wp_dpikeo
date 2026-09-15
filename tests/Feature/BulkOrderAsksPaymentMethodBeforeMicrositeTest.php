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
 * Pedido explícito: "Armar lista" mandaba al cliente directo al micrositio
 * sin preguntar el método de pago -- al volver, nadie sabía cómo iba a
 * pagar, y no se lo quiere hacer llegar hasta allá y devolverse para
 * preguntarle. Ahora se pregunta ANTES, con el mismo gate que ya usa
 * addToCart() (una sola vez por pedido); BulkOrderService::submitForContact()
 * ya sabía heredar el payment_method de un carrito "active" previo -- solo
 * faltaba que algo lo dejara guardado ahí antes de entrar al micrositio.
 */
class BulkOrderAsksPaymentMethodBeforeMicrositeTest extends TestCase
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

    public function test_tapping_armar_lista_for_the_first_time_asks_payment_method_instead_of_the_link(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $response = $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);

        $this->assertSame('list', $response['interactive']['type']);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $this->assertSame(['action' => 'bulk_order_web'], $cart->fresh()->metadata['pending_first_action']);
    }

    public function test_choosing_efectivo_resumes_straight_to_the_microsite_link(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();

        $response = $this->invoke($service, 'procesarPagoEfectivo', [$contact, $cart->id]);

        $this->assertSame('cta_url', $response['interactive']['type']);
        $this->assertSame('efectivo', $cart->fresh()->payment_method);
        $this->assertArrayNotHasKey('pending_first_action', $cart->fresh()->metadata ?? []);
    }

    /**
     * Pedido explícito en vivo: "cuando es pago con tarjeta es para la
     * página dpikeos.ec... y cuando es efectivo y transferencia es para el
     * de dpikeos.com" -- tarjeta nunca debe pasar por el micrositio propio
     * (dpikeos.ec es un ecommerce aparte, con su propio catálogo y carrito),
     * ni siquiera con el carrito de "Armar lista" todavía vacío.
     */
    public function test_choosing_tarjeta_sends_the_external_card_link_directly_instead_of_the_microsite(): void
    {
        [$profile, $contact] = $this->fixture();
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/'],
        ]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();

        $response = $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        $this->assertSame('cta_url', $response['interactive']['type']);
        $this->assertSame('https://dpikeos.ec/', $response['interactive']['action']['parameters']['url'] ?? null);
        $this->assertStringNotContainsString('/pedido/', $response['interactive']['action']['parameters']['url'] ?? '');
        // No debe quedar como una "orden" cancelada visible en Pedidos --
        // nunca tuvo productos ni el cliente pidió nada real.
        $this->assertNull($cart->fresh());
    }

    /**
     * Pedido explícito en vivo: el bot generaba un número de orden (visible
     * incluso en el panel de admin) apenas el cliente elegía tarjeta desde
     * "Armar lista", aunque nunca hubiera agregado un producto -- y si
     * después tocaba un botón de pago viejo (referenciando ese carrito ya
     * resuelto), el bot le mostraba un "confirma tu pedido" fantasma de
     * $0.00. Un clic viejo sobre un carrito que ya no existe debe avisar que
     * no se encontró, no inventar un resumen de pedido vacío.
     */
    public function test_a_stale_click_on_an_already_resolved_card_cart_does_not_fabricate_an_order(): void
    {
        [$profile, $contact] = $this->fixture();
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/'],
        ]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        // El cliente toca de nuevo un botón viejo (ej. "Transferencia") que
        // seguía referenciando ese mismo carrito ya resuelto/borrado.
        $response = $this->invoke($service, 'procesarPagoTransferencia', [$contact, $cart->id]);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('no se encontró', $response['text']['body']);
        $this->assertDatabaseMissing('whatsapp_carts', ['id' => $cart->id]);
    }

    public function test_a_second_tap_after_payment_method_is_chosen_skips_straight_to_the_link(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'active', 'total' => 0, 'payment_method' => 'efectivo',
        ]);

        $response = $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);

        $this->assertSame('cta_url', $response['interactive']['type']);
    }
}
