<?php

namespace Database\Seeders;

use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Database\Seeder;

class ChatGPTConfigSeeder extends Seeder
{
    public function run(): void
    {
        $businessProfiles = WhatsappBusinessProfile::all();

        foreach ($businessProfiles as $profile) {
            // Obtener todos los menús del negocio
            $menus = WhatsappMenu::where('business_profile_id', $profile->id)
                ->where('is_active', true)
                ->get();

            // Obtener todos los items de menú y sus precios
            $menuItems = [];
            foreach ($menus as $menu) {
                $items = WhatsappMenuItem::where('menu_id', $menu->id)
                    ->where('is_active', true)
                    ->get();

                foreach ($items as $item) {
                    $prices = WhatsappPrice::where('menu_item_id', $item->id)
                        ->where('is_active', true)
                        ->get();

                    $menuItems[] = [
                        'menu_title' => $menu->title,
                        'item_title' => $item->title,
                        'description' => $item->description,
                        'action_id' => $item->action_id,
                        'prices' => $prices->map(function ($price) {
                            return [
                                'name' => $price->name,
                                'description' => $price->description,
                                'price' => $price->price,
                                'currency' => $price->currency,
                                'is_promo' => $price->is_promo,
                                'promo_price' => $price->promo_price
                            ];
                        })->toArray()
                    ];
                }
            }

            // Crear el prompt del sistema con toda la información
            $systemPrompt = $this->generateSystemPrompt($profile, $menuItems);

            // Crear o actualizar la configuración de ChatGPT
            WhatsappChatbotConfig::updateOrCreate(
                ['business_profile_id' => $profile->id],
                [
                    'chatgpt_enabled' => false, // Por defecto desactivado
                    'chatgpt_model' => 'gpt-3.5-turbo',
                    'chatgpt_system_prompt' => $systemPrompt,
                    'chatgpt_max_tokens' => 500,
                    'chatgpt_temperature' => 0.7,
                    'chatgpt_additional_params' => json_encode([
                        'presence_penalty' => 0.6,
                        'frequency_penalty' => 0.0,
                    ]),
                ]
            );
        }
    }

    protected function generateSystemPrompt($profile, $menuItems): string
    {
        $prompt = "Eres un asistente virtual para {$profile->business_name}. ";
        $prompt .= "Información del negocio:\n";
        $prompt .= "- Descripción: " . ($profile->description ?? 'No disponible') . "\n";
        $prompt .= "- Categoría: " . ($profile->category ?? 'No disponible') . "\n";
        $prompt .= "- Dirección: " . ($profile->address ?? 'No disponible') . "\n";
        $prompt .= "- Email: " . ($profile->email ?? 'No disponible') . "\n";
        $prompt .= "- Sitio web: " . ($profile->website ?? 'No disponible') . "\n\n";

        if (!empty($menuItems)) {
            $prompt .= "Menú y Productos:\n";
            foreach ($menuItems as $item) {
                $prompt .= "- {$item['menu_title']} > {$item['item_title']}\n";
                if (!empty($item['description'])) {
                    $prompt .= "  Descripción: {$item['description']}\n";
                }

                if (!empty($item['prices'])) {
                    foreach ($item['prices'] as $price) {
                        $prompt .= "  * {$price['name']}\n";
                        if (!empty($price['description'])) {
                            $prompt .= "    {$price['description']}\n";
                        }
                        if (!empty($price['is_promo']) && !empty($price['promo_price'])) {
                            $prompt .= "    Precio promocional: {$price['promo_price']} {$price['currency']}\n";
                        } else {
                            $prompt .= "    Precio: {$price['price']} {$price['currency']}\n";
                        }
                    }
                }
                $prompt .= "\n";
            }
        } else {
            $prompt .= "No hay productos o servicios disponibles en este momento.\n\n";
        }

        $prompt .= "\nInstrucciones:\n";
        $prompt .= "1. Responde de manera profesional y amigable\n";
        $prompt .= "2. Utiliza la información proporcionada para responder preguntas sobre el negocio\n";
        $prompt .= "3. Si no tienes información sobre algo, indícalo claramente\n";
        $prompt .= "4. Mantén las respuestas concisas y relevantes\n";
        $prompt .= "5. Si el cliente pregunta por precios o servicios, proporciona la información específica de la lista\n";
        $prompt .= "6. Si el cliente quiere realizar una acción específica, guíalo sobre cómo hacerlo\n";
        $prompt .= "7. Cuando menciones precios, siempre indica la moneda\n";
        $prompt .= "8. Si hay precios promocionales, asegúrate de mencionarlos\n";

        return $prompt;
    }
}
