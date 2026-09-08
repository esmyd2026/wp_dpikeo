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
 * Bug real reportado en vivo: después de mandar el link de pago con
 * tarjeta, el carrito seguía "active" indefinidamente -- cualquier producto
 * nuevo que el cliente intentara agregar chocaba con "ya te enviamos el
 * link" (interceptForPaymentMethod reutiliza el carrito activo), y a los 35
 * minutos el timeout de carritos abandonados lo cerraba solo con el aviso
 * de "parece que no continuarás", aunque el cliente sí había elegido cómo
 * pagar y el resto ocurre en la web externa (no hay forma de saber si pagó
 * ahí). El link de pago ahora cierra el carrito en el momento.
 */
class CardPaymentClosesCartTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/'],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 5.99]);

        return [$profile, $contact, $cart];
    }

    public function test_sending_the_card_payment_link_closes_the_cart_immediately(): void
    {
        [$profile, $contact, $cart] = $this->fixture();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $response = $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        $this->assertSame('cta_url', $response['interactive']['type']);
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->fresh()->status);
    }

    public function test_after_the_card_link_a_new_product_starts_a_fresh_cart_instead_of_repeating_the_link(): void
    {
        [$profile, $contact, $cart] = $this->fixture();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        $ref = new \ReflectionMethod($service, 'interceptForPaymentMethod');
        $ref->setAccessible(true);
        $gate = $ref->invoke($service, $contact, ['action' => 'add', 'product_id' => 1, 'quantity' => 1, 'variation_index' => null]);

        // Debe volver a preguntar el método de pago (carrito nuevo), no
        // repetir "ya te enviamos el link" del carrito viejo ya cerrado.
        $this->assertSame('list', $gate['interactive']['type']);
        $this->assertStringNotContainsString('Ya te enviamos el link', json_encode($gate));
    }

    public function test_card_payment_does_not_close_the_cart_when_no_payment_url_is_configured(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000002',
            'phone_number_id' => 'PHONE-NOURL', 'whatsapp_business_id' => 'WABA-NOURL', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654322', 'name' => 'Cliente', 'status' => 'active']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 5.99]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        // Sin URL configurada no se manda ningún link -- el carrito sigue
        // activo para que el cliente pueda elegir otro método de pago.
        $this->assertSame('active', $cart->fresh()->status);
    }
}
