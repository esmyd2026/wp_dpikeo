<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
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

    public function test_choosing_tarjeta_also_resumes_to_the_microsite_link_instead_of_an_empty_payment_link(): void
    {
        [$profile, $contact] = $this->fixture();
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();

        $response = $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        // Debe ser el link del micrositio (cta_url con "Ver menú"), no un
        // link de pago de tarjeta para un pedido de $0.00, y el carrito no
        // debe cerrarse -- todavía no hay nada que pagar.
        $this->assertSame('cta_url', $response['interactive']['type']);
        $this->assertStringContainsString('/pedido/', $response['interactive']['action']['parameters']['url']);
        $this->assertSame('active', $cart->fresh()->status);
        $this->assertSame('tarjeta', $cart->fresh()->payment_method);
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
