<?php

namespace Database\Seeders;

use App\Models\WhatsappBusinessProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class WhatsappBusinessProfileSeeder extends Seeder
{
    public function run()
    {
        // Cada empresa arma su propio perfil desde el panel (conexión
        // manual o Embedded Signup, ver CompanyWhatsappController) -- este
        // seeder solo sirve para levantar una instalación completamente
        // nueva sin ningún perfil todavía. Con perfiles ya creados, faltar
        // WHATSAPP_PHONE_NUMBER_ID en el .env es lo normal y esperado, no
        // un error: antes esto se revisaba ANTES de comprobar si ya había
        // un perfil, así que un "php artisan db:seed" de rutina en
        // producción siempre terminaba logueando un error (y si el log no
        // se podía escribir por permisos, tumbaba todo el comando).
        if (WhatsappBusinessProfile::exists()) {
            $this->command?->info('Ya existe un perfil de WhatsApp Business, no se usa .env.');

            return;
        }

        try {
            $phoneNumber = env('WHATSAPP_PHONE_NUMBER');
            $businessId = env('WHATSAPP_BUSINESS_ID');
            $accessToken = env('WHATSAPP_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');

            if (!$phoneNumber || !$accessToken || !$phoneNumberId) {
                $this->command?->info('WhatsappBusinessProfileSeeder: sin credenciales en .env, se omite (conectá el número desde el panel Empresas -> WhatsApp).');

                return;
            }

            // Crear nuevo perfil
            $profileData = [
                'phone_number' => $phoneNumber,
                'phone_number_id' => $phoneNumberId,
                'business_name' => 'DPIKEOS',
                'display_name' => 'DPIKEOS',
                'status' => 'active',
                'access_token' => $accessToken,
                'metadata' => [
                    'description' => 'Pollo, hamburguesas, combos y especiales',
                    'address' => 'Dirección de la tienda',
                    'email' => null,
                    'website' => 'https://www.instagram.com/dpikeos_/'
                ]
            ];

            // Solo incluir whatsapp_business_id si está disponible
            if ($businessId) {
                $profileData['whatsapp_business_id'] = $businessId;
            }

            $profile = WhatsappBusinessProfile::create($profileData);

            if ($profile) {
                $this->command?->info('Perfil de WhatsApp Business creado exitosamente');
                Log::info('Perfil de WhatsApp Business creado exitosamente', [
                    'phone_number' => $phoneNumber,
                    'profile_id' => $profile->id
                ]);
            } else {
                $this->command?->error('Error al crear el perfil de WhatsApp Business');
                Log::error('Error al crear el perfil de WhatsApp Business');
            }
        } catch (\Exception $e) {
            $this->command?->error('Error en WhatsappBusinessProfileSeeder: ' . $e->getMessage());
            Log::error('Error en WhatsappBusinessProfileSeeder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
