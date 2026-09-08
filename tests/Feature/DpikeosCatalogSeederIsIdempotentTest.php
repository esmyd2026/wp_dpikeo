<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use Database\Seeders\DpikeosCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: DpikeosCatalogSeeder nació cuando la
 * plataforma tenía un solo WhatsappBusinessProfile (::first() era
 * inequívoco). Volver a correrlo -- un "php artisan db:seed" de rutina --
 * pisaba en silencio cualquier personalización que el admin hubiera hecho
 * desde el panel (nombre del negocio, mensajes del flujo) con el contenido
 * de demo otra vez. El seeder ahora debe ser un no-op si el perfil ya tiene
 * un flujo armado.
 */
class DpikeosCatalogSeederIsIdempotentTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_the_seeder_again_never_overwrites_an_existing_customized_flow(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Mi Negocio Real', 'display_name' => 'Mi Negocio Real', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        $flow = MarketingFlow::create([
            'business_profile_id' => $profile->id, 'name' => 'Mi flujo personalizado', 'is_active' => true, 'is_default' => true,
        ]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::WELCOME,
            'name' => 'Bienvenida inicial',
            'message_template' => '¡Hola {{nombre}}! Bienvenido a mi negocio real, no a DPIKEOS.',
            'is_enabled' => true,
            'config' => ['interactive_type' => 'text'],
        ]);

        (new DpikeosCatalogSeeder())->run();

        $profile->refresh();
        $this->assertSame('Mi Negocio Real', $profile->business_name, 'El seeder no debe pisar el nombre del negocio ya configurado.');
        $this->assertSame(
            '¡Hola {{nombre}}! Bienvenido a mi negocio real, no a DPIKEOS.',
            MarketingFlowStep::where('flow_id', $flow->id)->where('step_key', MarketingStepKey::WELCOME)->first()->message_template
        );
    }
}
