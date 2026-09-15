<?php

namespace Tests\Feature;

use App\Models\DeliveryConfirmationToken;
use App\Models\DeliveryDriver;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\DeliveryConfirmationService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: avisarle al cliente que su pedido va en camino solo lo
 * dispara el propio repartidor desde su enlace público de entrega -- ya NO
 * existe un botón para esto en el panel del operador. La razón: un operador
 * reportó que el aviso se disparaba justo al asignar el repartidor desde el
 * panel de delivery (confundía el botón "Avisar que va en camino", pensado
 * para el repartidor, con parte del flujo de despacho). El guardián
 * metadata['on_the_way_notified_at'] se conserva para que el aviso solo se
 * mande una vez, aunque ahora solo haya un disparador posible.
 */
class DeliveryOnTheWayNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart, DeliveryDriver} */
    private function dispatchedDeliveryFixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente',
            'last_inbound_at' => now(),
        ]);
        $driver = DeliveryDriver::create([
            'business_profile_id' => $profile->id, 'first_name' => 'Pedro', 'last_name' => 'Ruiz',
            'phone_number' => '593991234567', 'is_active' => true,
        ]);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 12.5,
            'payment_method' => 'efectivo',
            'metadata' => [
                'pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driver->id,
                'order_details' => ['order_number' => 'ORD-900'],
            ],
        ]);

        return [$profile, $contact, $cart, $driver];
    }

    public function test_the_driver_can_notify_the_customer_from_the_public_link(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, , $cart] = $this->dispatchedDeliveryFixture();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $response = $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]));

        $response->assertOk();
        $response->assertJson(['ok' => true, 'already' => false]);
        $this->assertNotNull($cart->fresh()->metadata['on_the_way_notified_at'] ?? null);
        $this->assertSame('driver', $cart->fresh()->metadata['on_the_way_notified_by']);
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'va en camino'));
    }

    public function test_clicking_it_twice_from_the_public_link_only_sends_once(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, , $cart] = $this->dispatchedDeliveryFixture();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]))->assertOk();
        $firstSentCount = count(Http::recorded());

        $second = $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]));

        $second->assertOk();
        $second->assertJson(['ok' => true, 'already' => true]);
        $this->assertCount($firstSentCount, Http::recorded());
    }

    public function test_the_admin_panel_no_longer_exposes_a_route_to_trigger_this_notification(): void
    {
        [, , $cart] = $this->dispatchedDeliveryFixture();

        $response = $this->postJson("/admin/delivery/{$cart->id}/avisar-en-camino");

        // La ruta ya no existe -- el aviso solo lo dispara el repartidor
        // desde su enlace público (ver test_the_driver_can_notify_the_customer_from_the_public_link).
        $response->assertStatus(404);
    }

    public function test_it_fails_clearly_when_no_driver_has_been_dispatched_yet(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, , $cart] = $this->dispatchedDeliveryFixture();
        $metadata = $cart->metadata;
        unset($metadata['last_dispatch_driver_id']);
        $cart->metadata = $metadata;
        $cart->save();

        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $response = $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]));

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
        Http::assertNothingSent();
    }
}
