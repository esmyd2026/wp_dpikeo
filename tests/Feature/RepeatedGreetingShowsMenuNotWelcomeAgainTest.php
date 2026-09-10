<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowNode;
use App\Models\MarketingFlowStep;
use App\Models\MarketingFlowVersion;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: el cliente escribió "hola" y, un minuto
 * después el mismo día, "menu" -- ambos mostraron la bienvenida completa
 * repetida ("¡Hola, Gregorio Osorio!...") en vez de que el segundo mostrara
 * el menú principal directo. Causa: si el grafo visual está publicado,
 * resolveGraphStartPayload() se consultaba ANTES de revisar si el contacto
 * ya fue saludado hoy -- como ese nodo de inicio es siempre el mismo texto
 * estático, "ya saludado hoy" nunca se llegaba a aplicar mientras el grafo
 * estuviera publicado.
 */
class RepeatedGreetingShowsMenuNotWelcomeAgainTest extends TestCase
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
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Gregorio Osorio', 'status' => 'active']);

        return [$profile, $contact];
    }

    private function sendGreeting(WhatsappBusinessProfile $profile, WhatsappContact $contact, string $text, string $messageId): void
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number, 'id' => $messageId, 'text' => ['body' => $text],
        ]]);
    }

    public function test_a_second_greeting_the_same_day_shows_the_main_menu_with_the_classic_flow(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id, 'step_key' => MarketingStepKey::WELCOME, 'name' => 'Bienvenida inicial',
            'message_template' => '¡Hola, {{nombre}}! 👋 Bienvenido a DPIKEOS.', 'is_enabled' => true,
        ]);

        $this->sendGreeting($profile, $contact, 'hola', 'wamid.1');
        $this->sendGreeting($profile, $contact, 'menu', 'wamid.2');

        // La bienvenida completa solo debe salir UNA vez (el primer "hola");
        // si "menu" la repitiera, contaría 2.
        $welcomeSentCount = Http::recorded(fn ($request) => str_contains(json_encode($request->data()), 'Bienvenido a DPIKEOS'))->count();
        $this->assertSame(1, $welcomeSentCount, 'La bienvenida completa no debe repetirse en el segundo saludo del día.');

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'menu_productos'));
    }

    public function test_a_second_greeting_the_same_day_shows_the_main_menu_even_with_a_published_graph(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact] = $this->fixture();

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        $startNode = MarketingFlowNode::create([
            'flow_id' => $flow->id, 'node_uuid' => 'start-node', 'node_type' => MarketingFlowNode::TYPE_MESSAGE,
            'name' => 'Inicio', 'message_template' => '¡Hola, {{nombre}}! 👋 Bienvenido a DPIKEOS (grafo).',
            'is_enabled' => true, 'is_start' => true,
        ]);
        MarketingFlowVersion::create([
            'flow_id' => $flow->id, 'version_number' => 1, 'is_current' => true, 'published_at' => now(),
            'snapshot' => [
                'start_node_uuid' => 'start-node',
                'nodes' => [
                    'start-node' => [
                        'node_uuid' => 'start-node', 'node_type' => MarketingFlowNode::TYPE_MESSAGE,
                        'message_template' => $startNode->message_template, 'config' => [], 'is_enabled' => true,
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $this->sendGreeting($profile, $contact, 'hola', 'wamid.1');
        $this->sendGreeting($profile, $contact, 'menu', 'wamid.2');

        // El texto del nodo de inicio del grafo debe salir UNA sola vez (el
        // primer "hola") -- si "menu" lo repitiera, contaría 2.
        $graphTextSentCount = Http::recorded(fn ($request) => str_contains(json_encode($request->data()), 'grafo'))->count();
        $this->assertSame(1, $graphTextSentCount, 'El nodo de inicio del grafo no debe repetirse en el segundo saludo del día.');

        // La segunda vez el mismo día debe caer al menú clásico (con botones).
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'menu_productos'));
    }
}
