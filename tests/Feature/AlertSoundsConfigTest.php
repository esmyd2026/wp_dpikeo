<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: "quiero que sea más ruidoso o permíteme seleccionar los
 * sonidos desde el panel administrativo".
 */
class AlertSoundsConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_sounds_are_used_when_nothing_is_configured(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $config = WhatsappChatbotConfig::create(['business_profile_id' => $profile->id, 'metadata' => []]);

        $this->assertSame([
            'new_order' => 'fuerte',
            'payment_proof' => 'fuerte',
            'invoice_confirmed' => 'normal',
            'agent_request' => 'urgente',
        ], $config->alert_sounds);
    }

    public function test_a_configured_sound_overrides_the_default_and_invalid_values_are_ignored(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $config = WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['alert_sounds' => ['new_order' => 'suave', 'payment_proof' => 'algo-invalido']],
        ]);

        $this->assertSame('suave', $config->alert_sounds['new_order']);
        // Valor no permitido -- se ignora y queda el default.
        $this->assertSame('fuerte', $config->alert_sounds['payment_proof']);
    }
}
