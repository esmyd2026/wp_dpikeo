<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: "Elige una opción para personalizar tu pedido." y el
 * botón "Ver productos" (al abrir una categoría del catálogo) estaban fijos
 * en el código, sin ningún campo en el panel para cambiarlos. Ahora son
 * configurables desde el paso "Catálogo de productos" del editor de flujo
 * (category_products_intro / category_products_button), con el texto de
 * siempre como valor por defecto si el admin no puso nada.
 */
class MarketingCatalogCustomTextTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappMenuItem} */
    private function catalogFixture(array $stepConfigOverrides = []): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Hamburguesas',
            'description' => 'Hamburguesas de pollo', 'action_id' => 'cat_burgers', 'is_active' => true,
        ]);
        WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Hamburguesas', 'sku' => 'BURG-1',
            'name' => 'Salchi Pollo', 'price' => 4.25, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::PRODUCTS_MENU,
            'name' => 'Catálogo de productos',
            'is_enabled' => true,
            'config' => array_merge([
                'interactive_type' => 'list',
                'catalog_source' => 'categories',
            ], $stepConfigOverrides),
        ]);

        return [$profile, $contact, $category];
    }

    public function test_default_intro_and_button_are_used_when_nothing_is_configured(): void
    {
        [$profile, $contact, $category] = $this->catalogFixture();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $response = $this->invoke($service, 'getProductsMenu', [$contact, $category->id]);

        $this->assertStringContainsString('Elige una opción para personalizar tu pedido.', $response['interactive']['body']['text']);
        $this->assertSame('Ver productos', $response['interactive']['action']['button']);
    }

    public function test_custom_intro_and_button_override_the_defaults(): void
    {
        [$profile, $contact, $category] = $this->catalogFixture([
            'category_products_intro' => 'Toca para agregarlo al pedido 👇',
            'category_products_button' => 'Agregar',
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $response = $this->invoke($service, 'getProductsMenu', [$contact, $category->id]);

        $this->assertStringContainsString('Toca para agregarlo al pedido 👇', $response['interactive']['body']['text']);
        $this->assertStringNotContainsString('Elige una opción para personalizar tu pedido.', $response['interactive']['body']['text']);
        $this->assertSame('Agregar', $response['interactive']['action']['button']);
    }
}
