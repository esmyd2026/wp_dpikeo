<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\Company;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\MarketingFlowVersion;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: el flujo visual (grafo) publicado divergió del editor
 * clásico para el saludo/menú, y el admin prefiere que el bot use
 * directamente lo configurado en "Flujo del bot" en vez de mantener los dos
 * sistemas sincronizados a mano. El comando marketing-flow:unpublish
 * despublica el grafo (sin borrar nada) para que el motor clásico vuelva a
 * responder el saludo.
 */
class UnpublishMarketingFlowGraphTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, WhatsappContact} */
    private function fixtureWithPublishedGraph(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Dpikeos', 'slug' => 'dpikeos-test', 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Dpikeos', 'display_name' => 'Dpikeos',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id, 'step_key' => MarketingStepKey::WELCOME, 'name' => 'Bienvenida inicial',
            'message_template' => 'Bienvenida del editor CLÁSICO, {{nombre}}.', 'is_enabled' => true,
            'config' => ['interactive_type' => 'text'],
        ]);
        $startNodeUuid = (string) Str::uuid();
        MarketingFlowVersion::create([
            'flow_id' => $flow->id, 'version_number' => 1, 'is_current' => true, 'published_at' => now(),
            'snapshot' => [
                'start_node_uuid' => $startNodeUuid,
                'nodes' => [
                    $startNodeUuid => [
                        'node_uuid' => $startNodeUuid, 'node_type' => 'start', 'name' => 'Inicio',
                        'message_template' => 'Bienvenida del GRAFO publicado, divergente.',
                        'config' => ['interactive_type' => 'text'], 'is_enabled' => true,
                    ],
                ],
                'edges' => [],
            ],
        ]);

        return [$company, $profile, $contact];
    }

    public function test_unpublishing_makes_the_greeting_fall_back_to_the_classic_editor(): void
    {
        [$company, $profile, $contact] = $this->fixtureWithPublishedGraph();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $ref = new \ReflectionMethod($service, 'resolveGraphStartPayload');
        $ref->setAccessible(true);

        // Antes de despublicar: el grafo manda su propio texto, no el del editor clásico.
        $beforePayload = $ref->invoke($service, $contact);
        $this->assertNotNull($beforePayload);
        $this->assertStringContainsString('GRAFO publicado', json_encode($beforePayload));

        $this->artisan('marketing-flow:unpublish', ['company' => 'dpikeos-test'])
            ->expectsOutputToContain('quedó despublicado')
            ->assertSuccessful();

        // Después: ya no hay grafo publicado, así que no manda nada -- el
        // motor clásico (handleGreetingMessage) es quien sigue.
        $service2 = app(WhatsappService::class);
        $service2->setWebhookPhoneNumberId($profile->phone_number_id);
        $ref2 = new \ReflectionMethod($service2, 'resolveGraphStartPayload');
        $ref2->setAccessible(true);
        $afterPayload = $ref2->invoke($service2, $contact);
        $this->assertNull($afterPayload);
    }

    public function test_command_reports_when_the_company_has_no_flow_at_all(): void
    {
        Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Sin flujo', 'slug' => 'sin-flujo-test', 'status' => 'active']);
        WhatsappBusinessProfile::create([
            'company_id' => Company::where('slug', 'sin-flujo-test')->value('id'),
            'business_name' => 'Sin flujo', 'display_name' => 'Sin flujo', 'phone_number' => '593990000002',
            'phone_number_id' => 'PHONE-SIN-FLUJO', 'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        $this->artisan('marketing-flow:unpublish', ['company' => 'sin-flujo-test'])
            ->expectsOutputToContain('nada que despublicar')
            ->assertSuccessful();
    }

    public function test_command_fails_for_an_unknown_company_slug(): void
    {
        $this->artisan('marketing-flow:unpublish', ['company' => 'no-existe'])
            ->assertFailed();
    }
}
