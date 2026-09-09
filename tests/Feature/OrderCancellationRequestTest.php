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
 * Pedido explícito en vivo: "en todo que le permita al cliente poder
 * solicitar cancelar el pedido, porque pasa que se demoran en preparar y el
 * cliente solicita que se cancele". Antes, una vez que el pedido pasaba a
 * "En cocina" o "Listo", el cliente se quedaba sin ninguna opción de
 * cancelar (ni siquiera pedirlo) más que "hablar con un asesor". Ahora el
 * botón "Cancelar pedido" sigue apareciendo, pero en esas dos etapas no
 * cancela de una -- registra la solicitud y avisa al equipo por WhatsApp
 * para que decida (el negocio ya pudo haber gastado insumos o el pedido ya
 * puede estar armado).
 */
class OrderCancellationRequestTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function fixture(array $configMetadata = []): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create(['business_profile_id' => $profile->id, 'metadata' => $configMetadata]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        return [$profile, $contact];
    }

    private function service(WhatsappBusinessProfile $profile): WhatsappService
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        return $service;
    }

    public function test_an_order_being_prepared_still_shows_a_cancel_button(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PREPARING, 'total' => 10]);

        $service = $this->service($profile);
        $response = $this->invoke($service, 'buildActiveOrderStatusResponse', [$contact]);

        $buttonIds = collect($response['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('cancelar_pedido_'.$cart->id, $buttonIds);
    }

    public function test_a_ready_order_still_shows_a_cancel_button(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 10]);

        $service = $this->service($profile);
        $response = $this->invoke($service, 'buildActiveOrderStatusResponse', [$contact]);

        $buttonIds = collect($response['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('cancelar_pedido_'.$cart->id, $buttonIds);
    }

    public function test_pressing_cancel_while_preparing_does_not_cancel_but_registers_the_request_and_alerts_staff(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture(['delivery_dispatch_numbers' => '593987000001']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PREPARING, 'total' => 10]);

        $service = $this->service($profile);
        $response = $this->invoke($service, 'cancelarPedido', [$contact, $cart->id]);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('Ya le avisamos a nuestro equipo', $response['text']['body']);
        $this->assertSame(WhatsappCart::STATUS_PREPARING, $cart->fresh()->status);
        $this->assertNotEmpty($cart->fresh()->metadata['cancellation_requested_at'] ?? null);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Solicitud de cancelaci')
            && str_contains(json_encode($request->data()), $cart->getOrderNumber()));
    }

    public function test_pressing_cancel_a_second_time_does_not_alert_staff_again(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture(['delivery_dispatch_numbers' => '593987000001']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PREPARING, 'total' => 10,
            'metadata' => ['cancellation_requested_at' => now()->subMinutes(5)->toIso8601String()],
        ]);

        $service = $this->service($profile);
        $this->invoke($service, 'cancelarPedido', [$contact, $cart->id]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Solicitud de cancelaci'));
    }

    public function test_a_ready_order_cancellation_request_also_alerts_staff(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture(['delivery_dispatch_numbers' => '593987000001']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 10]);

        $service = $this->service($profile);
        $this->invoke($service, 'cancelarPedido', [$contact, $cart->id]);

        $this->assertSame(WhatsappCart::STATUS_READY, $cart->fresh()->status);
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Solicitud de cancelaci'));
    }

    public function test_pending_confirmed_and_paid_orders_still_cancel_instantly_not_as_a_request(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAID, 'total' => 10]);

        $service = $this->service($profile);
        $response = $this->invoke($service, 'cancelarPedido', [$contact, $cart->id]);

        $this->assertSame('interactive', $response['type']);
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->fresh()->status);
    }

    public function test_a_completed_order_cannot_be_cancelled_or_requested(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 10]);

        $service = $this->service($profile);
        $response = $this->invoke($service, 'cancelarPedido', [$contact, $cart->id]);

        // No hay una transición válida desde 'completed' hacia 'cancelled'
        // (ver OrderLifecycleService::ALLOWED_TRANSITIONS) -- se mantiene el
        // mismo mensaje de error genérico que ya existía para este caso.
        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('ha ocurrido un error', $response['text']['body']);
        $this->assertSame(WhatsappCart::STATUS_COMPLETED, $cart->fresh()->status);
    }
}
