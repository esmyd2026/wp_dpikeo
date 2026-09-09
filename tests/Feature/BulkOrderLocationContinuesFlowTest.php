<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: al pedir por el micrositio ("Armar lista") y
 * elegir delivery, compartir la ubicación no continuaba el checkout -- el
 * bot respondía el genérico "Gracias por compartir tu ubicación. ¿En qué
 * más puedo ayudarte?" en vez de pedir el nombre de quien recibe. Causa: el
 * carrito del pedido de "Armar lista" (en 'pending', con la bandera
 * awaiting_delivery_address) no era el único carrito activo/pendiente del
 * contacto -- había además un carrito viejo 'active' (de una navegación
 * anterior en el chat) con un id MENOR, y la consulta sin ->latest() elegía
 * ese, no el correcto.
 */
class BulkOrderLocationContinuesFlowTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    public function test_sharing_location_continues_the_bulk_order_checkout_even_with_an_older_stale_active_cart(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        // Carrito viejo, sin relación con este pedido -- id más bajo, para
        // que una consulta sin ->latest() lo agarre primero.
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        // El pedido real de "Armar lista": id más alto, esperando la
        // dirección de entrega.
        $bulkOrder = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 18.5,
            'metadata' => ['source' => 'bulk_web_form', 'pickup_mode' => 'delivery', 'awaiting_delivery_address' => true],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleLocationMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.location-test',
            'location' => ['latitude' => -2.19, 'longitude' => -79.88],
        ]]);

        // Debe seguir el checkout (pedir el nombre de quien recibe, vía el
        // botón recipient_name_self/other), no caer en el genérico de
        // "gracias por tu ubicación".
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'recipient_name_self'));
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'menu_productos'));

        $this->assertTrue((bool) ($bulkOrder->fresh()->metadata['awaiting_delivery_recipient_name'] ?? false));
    }
}
