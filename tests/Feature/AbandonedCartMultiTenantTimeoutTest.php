<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\AbandonedCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: un cliente recibía "Ya te enviamos el link
 * para pagar tu pedido con tarjeta" de la nada, al elegir un producto nuevo
 * -- porque su carrito "active" viejo (de una compra vieja, con
 * payment_method=tarjeta ya guardado) nunca se cerraba, y
 * interceptForPaymentMethod() lo reusaba indefinidamente en vez de crear uno
 * nuevo. AbandonedCartService::timeoutMinutes() leía
 * WhatsappChatbotConfig::first() (la primera empresa de TODA la tabla, sin
 * filtrar), así que una empresa creada antes sin este límite configurado
 * desactivaba sin querer la limpieza automática para todas las demás.
 */
class AbandonedCartMultiTenantTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function makeCompany(string $slug, ?int $timeoutMinutes): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => $slug, 'display_name' => $slug, 'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => 'PHONE-' . strtoupper($slug), 'whatsapp_business_id' => 'WABA-' . strtoupper($slug),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => $timeoutMinutes ? ['abandoned_cart_timeout_minutes' => $timeoutMinutes] : [],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593' . random_int(100000000, 999999999), 'name' => 'Cliente']);

        return [$profile, $contact];
    }

    public function test_a_stale_cart_is_only_cancelled_against_its_own_companys_configured_timeout(): void
    {
        // A propósito: la empresa SIN límite configurado se crea primero (id
        // más bajo), para reproducir exactamente el bug de WhatsappChatbotConfig::first().
        [, $contactWithoutLimit] = $this->makeCompany('sin-limite', null);
        [, $contactWithLimit] = $this->makeCompany('con-limite-30', 30);

        $staleCartWithoutLimit = WhatsappCart::create(['contact_id' => $contactWithoutLimit->id, 'status' => 'active', 'total' => 0]);
        $staleCartWithoutLimit->forceFill(['updated_at' => now()->subHours(5)])->saveQuietly();

        $staleCartWithLimit = WhatsappCart::create(['contact_id' => $contactWithLimit->id, 'status' => 'active', 'total' => 0]);
        $staleCartWithLimit->forceFill(['updated_at' => now()->subMinutes(45)])->saveQuietly();

        $closed = app(AbandonedCartService::class)->cancelTimedOut();

        $this->assertSame(1, $closed);
        $this->assertSame('active', $staleCartWithoutLimit->fresh()->status, 'Sin límite configurado para SU empresa, no debe cerrarse.');
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $staleCartWithLimit->fresh()->status);
    }

    public function test_timeout_minutes_resolves_independently_per_company(): void
    {
        [$profileA] = $this->makeCompany('empresa-a', 15);
        [$profileB] = $this->makeCompany('empresa-b', 60);

        $service = app(AbandonedCartService::class);

        $this->assertSame(15, $service->timeoutMinutes($profileA->id));
        $this->assertSame(60, $service->timeoutMinutes($profileB->id));
    }

    public function test_a_recently_updated_cart_is_never_closed_even_past_a_short_timeout(): void
    {
        [, $contact] = $this->makeCompany('empresa-activa', 15);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        $closed = app(AbandonedCartService::class)->cancelTimedOut();

        $this->assertSame(0, $closed);
        $this->assertSame('active', $cart->fresh()->status);
    }

    public function test_closing_a_stale_cart_lets_the_next_order_ask_for_payment_method_again(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->makeCompany('empresa-tarjeta', 30);

        $staleCart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'active', 'total' => 0,
            'payment_method' => 'tarjeta', 'metadata' => ['card_payment_link_sent' => true],
        ]);
        $staleCart->forceFill(['updated_at' => now()->subHours(2)])->saveQuietly();

        app(AbandonedCartService::class)->cancelTimedOut();
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $staleCart->fresh()->status);

        $service = app(\App\Services\WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $ref = new \ReflectionMethod($service, 'interceptForPaymentMethod');
        $ref->setAccessible(true);
        $gate = $ref->invoke($service, $contact, ['action' => 'add', 'product_id' => 1, 'quantity' => 1, 'variation_index' => null]);

        // Un carrito NUEVO (no el viejo con tarjeta ya "usada"): debe volver
        // a preguntar el método de pago, no repetir "ya te enviamos el link".
        $this->assertSame('list', $gate['interactive']['type']);
        $this->assertStringNotContainsString('Ya te enviamos el link', json_encode($gate));
    }
}
