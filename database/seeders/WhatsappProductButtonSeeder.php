<?php

namespace Database\Seeders;

use App\Models\WhatsappAction;
use App\Models\WhatsappButton;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WhatsappProductButtonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Primero nos aseguramos de que existan las acciones necesarias
        $this->call(WhatsappActionSeeder::class);

        // Obtenemos los perfiles de negocio
        $profiles = WhatsappBusinessProfile::all();

        foreach ($profiles as $profile) {
            // Botón de Comprar
            WhatsappButton::updateOrCreate(
                [
                    'business_profile_id' => $profile->id,
                    'action_id' => WhatsappAction::where('code', 'comprar')->first()->id
                ],
                [
                    'title' => 'Comprar',
                    'icon' => '🛒',
                    'type' => 'reply',
                    'order' => 1,
                    'is_active' => true,
                    'metadata' => ['requires_confirmation' => true]
                ]
            );

            // Botón de Ver Detalles
            WhatsappButton::updateOrCreate(
                [
                    'business_profile_id' => $profile->id,
                    'action_id' => WhatsappAction::where('code', 'ver_producto')->first()->id
                ],
                [
                    'title' => 'Ver detalles',
                    'icon' => '👁️',
                    'type' => 'reply',
                    'order' => 2,
                    'is_active' => true,
                    'metadata' => ['shows_details' => true]
                ]
            );

            // Botón de Volver a Productos
            WhatsappButton::updateOrCreate(
                [
                    'business_profile_id' => $profile->id,
                    'action_id' => WhatsappAction::where('code', 'volver_productos')->first()->id
                ],
                [
                    'title' => 'Volver a productos',
                    'icon' => '🔙',
                    'type' => 'reply',
                    'order' => 3,
                    'is_active' => true,
                    'metadata' => ['returns_to_menu' => true]
                ]
            );
        }
    }
}
