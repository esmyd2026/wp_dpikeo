<?php

namespace Database\Seeders;

use App\Models\WhatsappBusinessProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class WhatsappBusinessProfileSeeder extends Seeder
{
    public function run()
    {
        try {
            $phoneNumber = env('WHATSAPP_PHONE_NUMBER');
            $businessId = env('WHATSAPP_BUSINESS_ID');
            $accessToken = env('WHATSAPP_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');

            if (!$phoneNumber) {
                $this->command->error('WHATSAPP_PHONE_NUMBER no está definido en el archivo .env');
                Log::error('WHATSAPP_PHONE_NUMBER no está definido en el archivo .env');
                return;
            }

            if (!$accessToken) {
                $this->command->error('WHATSAPP_TOKEN no está definido en el archivo .env');
                Log::error('WHATSAPP_TOKEN no está definido en el archivo .env');
                return;
            }

            if (!$phoneNumberId) {
                $this->command->error('WHATSAPP_PHONE_NUMBER_ID no está definido en el archivo .env');
                Log::error('WHATSAPP_PHONE_NUMBER_ID no está definido en el archivo .env');
                return;
            }

            // Verificar si ya existe un perfil
            $existingProfile = WhatsappBusinessProfile::first();
            if ($existingProfile) {
                $this->command->info('Ya existe un perfil de WhatsApp Business');
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
                $this->command->info('Perfil de WhatsApp Business creado exitosamente');
                Log::info('Perfil de WhatsApp Business creado exitosamente', [
                    'phone_number' => $phoneNumber,
                    'profile_id' => $profile->id
                ]);
            } else {
                $this->command->error('Error al crear el perfil de WhatsApp Business');
                Log::error('Error al crear el perfil de WhatsApp Business');
            }
        } catch (\Exception $e) {
            $this->command->error('Error en WhatsappBusinessProfileSeeder: ' . $e->getMessage());
            Log::error('Error en WhatsappBusinessProfileSeeder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
