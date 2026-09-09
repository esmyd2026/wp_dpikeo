<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: si el cliente escribe cualquier palabra que no sea un
 * saludo reconocido ("hola", "buenas", etc.) y no tiene ningún pedido en
 * curso, se trata igual que si la sesión estuviera reiniciada -- se le
 * manda el saludo/menú principal, en vez del paso "Mensaje no reconocido".
 * Ese mensaje solo debe seguir apareciendo cuando el cliente SÍ tiene un
 * pedido a medias y escribió algo que no calza con lo que se le pidió.
 */
class UnrecognizedTextWithNoOpenOrderShowsMainMenuTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function fixtureWithFallbackStep(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::FALLBACK_MESSAGE,
            'name' => 'Mensaje no reconocido',
            'message_template' => 'No quiero que te pierdas del menú 😊',
            'is_enabled' => true,
            'config' => ['interactive_type' => 'button', 'buttons' => [['id' => 'menu_productos', 'title' => 'Ver menú']]],
        ]);

        return [$profile, $contact];
    }

    public function test_unrecognized_text_with_no_open_order_shows_the_main_menu_not_the_fallback_step(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixtureWithFallbackStep();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.random-word',
            'text' => ['body' => 'pizza'],
        ]]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'No quiero que te pierdas'));
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'menu_productos') || str_contains(json_encode($request->data()), 'bulk_order_web'));
    }

    public function test_unrecognized_text_with_an_open_order_still_shows_the_fallback_step(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixtureWithFallbackStep();
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 5.99]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.random-word-2',
            'text' => ['body' => 'pizza'],
        ]]);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'No quiero que te pierdas'));
    }
}
