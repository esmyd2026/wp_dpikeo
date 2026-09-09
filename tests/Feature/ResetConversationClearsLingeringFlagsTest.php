<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\AbandonedCartService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: el admin reinició la conversación desde el
 * panel de chat, el bot avisó "Reiniciamos tu conversación...", y aun así
 * el siguiente "hola" del cliente no mostró el saludo -- el bot le preguntó
 * "¿A nombre de quién recibimos el pedido?" (paso de un pedido viejo armado
 * por "Armar lista", que queda en 'pending' y por eso el reinicio nunca lo
 * toca). La bandera "esperando el nombre de quien recibe" seguía viva y
 * secuestraba cualquier texto siguiente, sin importar el contenido.
 */
class ResetConversationClearsLingeringFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    public function test_resetting_the_conversation_stops_a_leftover_pending_cart_from_hijacking_the_next_greeting(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        // Carrito "pending" (armado por Armar lista) esperando el nombre de
        // quien recibe -- fuera de STALE_STATUSES a propósito, un pedido
        // real no se cancela solo por reiniciar el chat.
        $pendingOrder = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 12.5,
            'metadata' => ['awaiting_delivery_recipient_name' => true],
        ]);

        // El único carrito realmente "abandonado" es uno activo aparte.
        $activeCart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        app(AbandonedCartService::class)->close($activeCart);

        // El pedido pending sigue existiendo tal cual (no se cancela), pero
        // ya no debe quedar "esperando" nada.
        $this->assertSame(WhatsappCart::STATUS_PENDING, $pendingOrder->fresh()->status);
        $this->assertArrayNotHasKey('awaiting_delivery_recipient_name', $pendingOrder->fresh()->metadata ?? []);

        // El siguiente "hola" del cliente, por el camino real de
        // handleTextMessage(), ahora debe mostrar el saludo normal -- no la
        // pregunta vieja de "¿a nombre de quién recibimos el pedido?".
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.greeting-test',
            'text' => ['body' => 'hola'],
        ]]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'a nombre de qui'));
    }
}
