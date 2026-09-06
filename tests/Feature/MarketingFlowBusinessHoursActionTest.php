<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\BusinessBranch;
use App\Models\BusinessBranchHour;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El botón "Horarios" del menú principal (custom:horario_atencion) se arma en
 * vivo desde las Sucursales -- sin esto, cualquier cambio de horario obligaba
 * a editar también un texto fijo del flujo, y quedaban fácilmente
 * desincronizados. Si el admin no configuró un texto propio para esa clave,
 * el bot genera el mensaje solo con los datos reales de BusinessBranchHour.
 */
class MarketingFlowBusinessHoursActionTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invoke($service, ...$args);
    }

    private function makeProfileWithMainMenuHorarioButton(): array
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
            'step_key' => MarketingStepKey::MAIN_MENU,
            'name' => 'Menú principal',
            'message_template' => '¿En qué te ayudo?',
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'button',
                'buttons' => [
                    ['id' => 'horario_atencion', 'title' => '⏰ Horarios', 'action' => 'custom:horario_atencion'],
                ],
            ],
        ]);

        return [$profile, $contact];
    }

    public function test_falls_back_to_not_recognized_when_no_active_branches_exist(): void
    {
        [, $contact] = $this->makeProfileWithMainMenuHorarioButton();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $response = $this->invoke($service, 'resolveFlowInlineResponse', ['horario_atencion', $contact]);

        $this->assertNull($response, 'Sin sucursales activas no hay nada que mostrar -- cae al fallback genérico del llamador.');
    }

    public function test_renders_hours_for_a_single_branch_without_repeating_its_name(): void
    {
        [$profile, $contact] = $this->makeProfileWithMainMenuHorarioButton();

        $branch = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'M1', 'is_default' => true, 'is_active' => true]);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 1, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 0, 'is_closed' => true]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $response = $this->invoke($service, 'resolveFlowInlineResponse', ['horario_atencion', $contact]);

        $this->assertSame('text', $response['type']);
        $body = $response['text']['body'];
        $this->assertStringNotContainsString('Matriz', $body, 'Con una sola sucursal no hace falta repetir su nombre.');
        $this->assertStringContainsString('Lunes: 09:00 - 18:00', $body);
        $this->assertStringContainsString('Domingo: Cerrado', $body);
    }

    public function test_shows_branch_names_when_there_is_more_than_one(): void
    {
        [$profile, $contact] = $this->makeProfileWithMainMenuHorarioButton();

        $a = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'M1', 'is_default' => true, 'is_active' => true]);
        $b = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Norte', 'code' => 'N1', 'is_active' => true]);
        BusinessBranchHour::create(['business_branch_id' => $a->id, 'day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        BusinessBranchHour::create(['business_branch_id' => $b->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '20:00']);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $body = $this->invoke($service, 'resolveFlowInlineResponse', ['horario_atencion', $contact])['text']['body'];

        $this->assertStringContainsString('Matriz', $body);
        $this->assertStringContainsString('Norte', $body);
        $this->assertStringContainsString('09:00 - 18:00', $body);
        $this->assertStringContainsString('10:00 - 20:00', $body);
    }

    public function test_admin_defined_custom_action_text_wins_over_the_automatic_one(): void
    {
        [$profile, $contact] = $this->makeProfileWithMainMenuHorarioButton();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'M1', 'is_default' => true, 'is_active' => true]);

        $step = MarketingFlowStep::where('flow_id', MarketingFlow::first()->id)->firstOrFail();
        $config = $step->config;
        $config['custom_actions'] = ['horario_atencion' => 'Atendemos todos los días, 24 horas.'];
        $step->update(['config' => $config]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $body = $this->invoke($service, 'resolveFlowInlineResponse', ['horario_atencion', $contact])['text']['body'];

        $this->assertSame('Atendemos todos los días, 24 horas.', $body);
    }
}
