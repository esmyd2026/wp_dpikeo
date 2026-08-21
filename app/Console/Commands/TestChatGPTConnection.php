<?php

namespace App\Console\Commands;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Services\ChatGPTService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestChatGPTConnection extends Command
{
    protected $signature = 'chatgpt:test {--profile= : ID del perfil de negocio} {--debug : Mostrar información de depuración}';
    protected $description = 'Prueba la conexión con ChatGPT para un perfil de negocio';

    public function handle()
    {
        $profileId = $this->option('profile');
        $debug = $this->option('debug');

        if ($profileId) {
            $profile = WhatsappBusinessProfile::find($profileId);
            if (!$profile) {
                $this->error("No se encontró el perfil de negocio con ID: {$profileId}");
                return 1;
            }
            $this->testProfile($profile, $debug);
        } else {
            $profiles = WhatsappBusinessProfile::all();
            if ($profiles->isEmpty()) {
                $this->error('No hay perfiles de negocio configurados.');
                return 1;
            }

            foreach ($profiles as $profile) {
                $this->testProfile($profile, $debug);
            }
        }

        return 0;
    }

    protected function testProfile(WhatsappBusinessProfile $profile, bool $debug = false)
    {
        $this->info("\nProbando conexión para: {$profile->business_name}");

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile->id)->first();

        if (!$config) {
            $this->error('No hay configuración de ChatGPT para este perfil.');
            return;
        }

        if (!config('whatsapp.chatgpt.enabled', false)) {
            $this->warn('ChatGPT está desactivado globalmente (CHATGPT_ENABLED=false en .env).');
            return;
        }

        if (!$config->chatgpt_enabled) {
            $this->warn('ChatGPT está desactivado para este perfil.');
            return;
        }

        if (empty($config->chatgpt_api_key)) {
            $this->error('No hay API key configurada para este perfil.');
            return;
        }

        $this->info('Configuración encontrada:');
        $this->line("- Modelo: {$config->chatgpt_model}");
        $this->line("- Max Tokens: {$config->chatgpt_max_tokens}");
        $this->line("- Temperature: {$config->chatgpt_temperature}");

        if ($debug) {
            $this->line("\nInformación de depuración:");
            $this->line("- API Key: " . substr($config->chatgpt_api_key, 0, 4) . '...' . substr($config->chatgpt_api_key, -4));
            $this->line("- System Prompt: " . substr($config->chatgpt_system_prompt, 0, 100) . '...');
            $this->line("- Additional Params: " . json_encode($config->chatgpt_additional_params));
        }

        $chatGPT = new ChatGPTService($config);

        if (!$chatGPT->isEnabled()) {
            $this->error('El servicio de ChatGPT no está habilitado.');
            return;
        }

        $this->info("\nEnviando mensaje de prueba...");

        try {
            $response = $chatGPT->query("Hola, ¿podrías presentarte brevemente?");

            if ($response) {
                $this->info("\nRespuesta recibida:");
                $this->line($response);
                $this->info("\n✅ Conexión exitosa!");
            } else {
                $this->error("\n❌ No se recibió respuesta del servicio.");
            }
        } catch (\Exception $e) {
            $this->error("\n❌ Error al conectar con ChatGPT:");
            $this->error($e->getMessage());

            if ($debug) {
                $this->line("\nDetalles del error:");
                $this->line($e->getTraceAsString());
            }
        }
    }
}
