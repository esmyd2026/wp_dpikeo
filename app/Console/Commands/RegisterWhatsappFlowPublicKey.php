<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RegisterWhatsappFlowPublicKey extends Command
{
    protected $signature = 'whatsapp:flow-register-public-key';
    protected $description = 'Registra la clave pública de WhatsApp Flows en el número Cloud API configurado';

    public function handle(): int
    {
        $phoneNumberId = config('whatsapp.phone_number_id');
        $token = config('whatsapp.token');
        $path = config('whatsapp.flows.public_key_path');

        if (!$phoneNumberId || !$token) {
            $this->error('Faltan WHATSAPP_PHONE_NUMBER_ID o WHATSAPP_TOKEN en .env.');
            return self::FAILURE;
        }
        if (!Storage::disk('local')->exists($path)) {
            $this->error('No existe la clave pública. Ejecute primero: php artisan whatsapp:flow-keys');
            return self::FAILURE;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post(
                rtrim(config('whatsapp.api_url'), '/') . '/' . config('whatsapp.api_version') . "/{$phoneNumberId}/whatsapp_business_encryption",
                ['business_public_key' => Storage::disk('local')->get($path)]
            );

        if ($response->successful()) {
            $this->info('Clave pública registrada correctamente en Meta.');
            return self::SUCCESS;
        }

        $this->error('Meta rechazó el registro: ' . ($response->json('error.message') ?? $response->body()));
        return self::FAILURE;
    }
}
