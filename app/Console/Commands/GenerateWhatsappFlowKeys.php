<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateWhatsappFlowKeys extends Command
{
    protected $signature = 'whatsapp:flow-keys {--force : Reemplaza claves existentes}';
    protected $description = 'Genera el par RSA requerido por Meta WhatsApp Flows';

    public function handle(): int
    {
        $privatePath = config('whatsapp.flows.private_key_path');
        $publicPath = config('whatsapp.flows.public_key_path');
        $disk = Storage::disk('local');

        if (!$this->option('force') && ($disk->exists($privatePath) || $disk->exists($publicPath))) {
            $this->error('Ya existen claves. Use --force solo si Meta no tiene asociada la clave actual.');
            return self::FAILURE;
        }

        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => config('whatsapp.flows.openssl_config'),
        ];
        $key = openssl_pkey_new($options);
        if ($key === false || !openssl_pkey_export($key, $private, null, $options)) {
            $this->error('No fue posible generar la clave RSA.');
            return self::FAILURE;
        }

        $details = openssl_pkey_get_details($key);
        $disk->put($privatePath, $private);
        $disk->put($publicPath, $details['key']);

        $this->info('Claves generadas correctamente.');
        $this->line('Clave pública para Meta: ' . storage_path('app/' . $publicPath));
        $this->warn('No comparta ni suba al repositorio la clave privada.');

        return self::SUCCESS;
    }
}
