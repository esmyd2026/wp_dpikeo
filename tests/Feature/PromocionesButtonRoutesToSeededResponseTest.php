<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotResponse;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: tocar "🔥 Promociones" en el menú de
 * Información mandaba el genérico "🙏 No reconocí esa opción" en vez de la
 * respuesta sembrada por DpikeosCatalogSeeder. Causa: al switch de
 * button_id le faltaba el case 'promociones' -- sus hermanos del mismo menú
 * ('contacto', 'redes', etc.) sí estaban, este se quedó afuera por
 * descuido.
 */
class PromocionesButtonRoutesToSeededResponseTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    public function test_tapping_promociones_sends_the_seeded_response_not_the_generic_fallback(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        WhatsappChatbotResponse::create([
            'keyword' => 'promociones',
            'response' => 'MARCADOR_PROMOCIONES_VIGENTES',
            'type' => 'text',
            'is_active' => true,
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.promociones-test',
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => ['id' => 'promociones', 'title' => '🔥 Promociones'],
            ],
        ]]);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'MARCADOR_PROMOCIONES_VIGENTES'));
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'No reconoc'));
    }
}
