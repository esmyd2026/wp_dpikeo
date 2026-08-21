<?php

namespace Database\Seeders;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappChatbotResponse;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Models\Franchise;
use App\Services\DemoClienteService;
use App\Services\PlanLimitsService;
use Illuminate\Database\Seeder;

/** Configura DPIKEOS como una demo separada y totalmente guiada, sin IA. */
class DpikeosDemoSeeder extends Seeder
{
    private const DEMO_KEY = 'dpikeos';

    public function run(): void
    {
        $profile = WhatsappBusinessProfile::first();
        if (!$profile) {
            $this->command?->warn('DpikeosDemoSeeder: sin perfil de negocio.');
            return;
        }

        $this->deactivateOtherDemos();
        $this->seedCatalog($profile);
        $this->configureBusiness($profile);
        $this->configureChatbot($profile);
        $this->configureFlow($profile);
        $this->configureResponses();
        $this->activateDemo();

        $this->command?->info('Demo DPIKEOS lista. Catálogo activo: ' . self::DEMO_KEY);
    }

    private function deactivateOtherDemos(): void
    {
        WhatsappMenuItem::query()
            ->whereNotNull('demo_cliente')
            ->where('demo_cliente', '!=', self::DEMO_KEY)
            ->update(['is_active' => false]);

        WhatsappPrice::query()
            ->whereNotNull('demo_cliente')
            ->where('demo_cliente', '!=', self::DEMO_KEY)
            ->update(['is_active' => false]);
    }

    private function seedCatalog(WhatsappBusinessProfile $profile): void
    {
        $franchise = Franchise::query()->firstOrCreate(
            ['slug' => self::DEMO_KEY],
            ['name' => 'DPIKEOS · Club Dpikeolovers', 'description' => 'Franquicia principal de pollo, combos y hamburguesas.', 'is_default' => true, 'is_active' => true]
        );

        $menu = WhatsappMenu::updateOrCreate(
            ['action_id' => 'prices_menu'],
            [
                'business_profile_id' => $profile->id,
                'title' => 'Menú DPIKEOS',
                'description' => 'Pollo, hamburguesas, combos y especiales',
                'type' => 'list',
                'content' => 'Elige tu categoría favorita y personaliza tu pedido.',
                'button_text' => 'Ver el menú',
                'icon' => '🍗',
                'order' => 1,
                'is_active' => true,
            ]
        );

        foreach (require __DIR__ . '/data/dpikeos_catalog.php' as $order => $category) {
            $item = WhatsappMenuItem::updateOrCreate(
                ['menu_id' => $menu->id, 'action_id' => $category['action_id']],
                [
                    'franchise_id' => $franchise->id,
                    'title' => $category['title'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'order' => $order + 1,
                    'is_active' => true,
                    'demo_cliente' => self::DEMO_KEY,
                ]
            );

            foreach ($category['products'] as $product) {
                $metadata = [
                    'variations' => $product['variations'] ?? [],
                    'extras' => [
                        ['title' => 'Salsa Spice Chicken pequeña', 'price' => 0.50],
                        ['title' => 'Salsa Spice Chicken grande', 'price' => 2.99],
                        ['title' => 'Papas extra', 'price' => null],
                        ['title' => 'Queso extra', 'price' => null],
                        ['title' => 'Tocino extra', 'price' => null],
                        ['title' => 'Gaseosa adicional', 'price' => null],
                    ],
                    'preparation_time_minutes' => null,
                    'featured' => in_array($product['sku'], ['DP001', 'DP006', 'DP018'], true),
                ];

                WhatsappPrice::updateOrCreate(
                    ['sku' => $product['sku']],
                    [
                        'menu_item_id' => $item->id,
                        'franchise_id' => $franchise->id,
                        'category' => $category['title'],
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'price' => $product['price'],
                        'promo_price' => null,
                        'is_promo' => false,
                        'currency' => 'USD',
                        'is_active' => true,
                        'demo_cliente' => self::DEMO_KEY,
                        'stock' => 999,
                        'allow_quantity_selection' => true,
                        'min_quantity' => 1,
                        'max_quantity' => 8,
                        'metadata' => $metadata,
                    ]
                );
            }
        }
    }

    private function configureBusiness(WhatsappBusinessProfile $profile): void
    {
        $metadata = array_merge(is_array($profile->metadata) ? $profile->metadata : [], [
            'trade_name' => 'DPIKEOS',
            'instagram' => 'https://www.instagram.com/dpikeos_/',
            'community_name' => 'Club Dpikeolovers',
            // Dirección, horario, cobertura y teléfonos se configuran desde el panel.
        ]);

        $profile->update([
            'business_name' => 'DPIKEOS',
            'display_name' => 'DPIKEOS',
            'metadata' => $metadata,
        ]);
    }

    private function configureChatbot(WhatsappBusinessProfile $profile): void
    {
        $config = WhatsappChatbotConfig::firstOrCreate(
            ['business_profile_id' => $profile->id],
            ['is_active' => true]
        );

        $metadata = array_merge(is_array($config->metadata) ? $config->metadata : [], [
            'bot_name' => 'DPIKEOS',
            'primary_color' => '#e76f00',
            'secondary_color' => '#007f68',
            'community_name' => 'Dpikeolovers',
            'response_delay' => 800,
        ]);

        $config->update([
            'welcome_message' => '¡Hola {{nombre}}! 👋 Bienvenido a *DPIKEOS*. Elige tu antojo y arma tu pedido en pocos pasos. 🍗',
            'default_response' => 'Usa los botones para ver el menú o escribe *hola* para volver al inicio.',
            'greetings' => ['hola', 'buenas', 'menu', 'menú', 'inicio'],
            'menu_commands' => ['menu', 'menú', 'inicio'],
            'metadata' => $metadata,
            // El flujo DPIKEOS es determinista: nunca consulta IA aunque exista una API key.
            'chatgpt_enabled' => false,
        ]);
    }

    private function configureFlow(WhatsappBusinessProfile $profile): void
    {
        $flow = MarketingFlow::updateOrCreate(
            ['business_profile_id' => $profile->id, 'is_default' => true],
            ['name' => 'Flujo guiado DPIKEOS', 'is_active' => true]
        );

        $steps = require __DIR__ . '/data/dpikeos_flow.php';
        foreach (MarketingStepKey::defaultOrder() as $order => $key) {
            $data = $steps[$key] ?? ['message' => '', 'type' => 'text'];
            $config = ['interactive_type' => $data['type']];

            foreach (['buttons', 'list', 'catalog_source', 'max_product_rows', 'include_navigation', 'quick_order_flow', 'success_message', 'require_for_methods'] as $field) {
                if (array_key_exists($field, $data)) {
                    $config[$field] = $data[$field];
                }
            }
            if (!empty($data['require_proof'])) {
                $config['require_proof'] = true;
            }

            MarketingFlowStep::updateOrCreate(
                ['flow_id' => $flow->id, 'step_key' => $key],
                [
                    'name' => MarketingStepKey::all()[$key],
                    'message_template' => $data['message'],
                    'sort_order' => $order + 1,
                    'is_enabled' => true,
                    'config' => $config,
                ]
            );
        }
    }

    private function configureResponses(): void
    {
        foreach ([
            'redes' => "*Síguenos en Instagram* 📸\n\nhttps://www.instagram.com/dpikeos_/\n\nÚnete al *Club Dpikeolovers* para enterarte de novedades y promociones.",
            'promociones' => "*Promociones DPIKEOS* 🔥\n\nLas promociones activas aparecerán aquí. Usa *Pedir ahora* para ver el menú y arma tu combo favorito.",
        ] as $keyword => $response) {
            WhatsappChatbotResponse::updateOrCreate(
                ['keyword' => $keyword],
                ['response' => $response, 'type' => 'text', 'show_menu' => false, 'is_active' => true]
            );
        }
    }

    private function activateDemo(): void
    {
        app(PlanLimitsService::class)->savePlatformLimits([
            'active_demo_cliente' => self::DEMO_KEY,
            'subscription_plan' => 'pro',
            'max_products_limit' => 500,
            'max_categories_limit' => 60,
            'bulk_web_order_enabled' => true,
        ]);

        app(DemoClienteService::class)->saveActiveKey(self::DEMO_KEY);
    }
}
