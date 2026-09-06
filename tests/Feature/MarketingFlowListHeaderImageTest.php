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
use App\Services\Whatsapp\WhatsappMessagePayload;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real en producción: Meta rechaza (#131009) un mensaje interactivo tipo
 * "list" con encabezado de imagen -- a diferencia de los mensajes de botones,
 * las listas de WhatsApp solo admiten encabezado de texto. Un paso del flujo
 * de marketing con imagen configurada tumbaba el envío completo (categorías
 * de catálogo) en vez de solo mostrar la lista sin imagen incrustada.
 */
class MarketingFlowListHeaderImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_payload_never_includes_a_non_text_header(): void
    {
        $payload = WhatsappMessagePayload::list(
            'Elige una categoría',
            'Ver el menú',
            [['title' => 'Categorías', 'rows' => [['id' => 'cat_1', 'title' => 'Boxes']]]],
            ['type' => 'image', 'image' => ['link' => 'https://example.com/foo.png']]
        );

        $this->assertArrayNotHasKey('header', $payload['interactive']);
    }

    public function test_list_payload_keeps_a_text_header(): void
    {
        $payload = WhatsappMessagePayload::list(
            'Elige una categoría',
            'Ver el menú',
            [['title' => 'Categorías', 'rows' => [['id' => 'cat_1', 'title' => 'Boxes']]]],
            ['type' => 'text', 'text' => 'DPIKEOS']
        );

        $this->assertSame(['type' => 'text', 'text' => 'DPIKEOS'], $payload['interactive']['header']);
    }

    public function test_products_menu_step_with_image_header_sends_the_image_separately_before_the_list(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::PRODUCTS_MENU,
            'name' => 'Catálogo de productos',
            'message_template' => '¿Qué se te antoja?',
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'list',
                'header' => ['type' => 'image', 'image_path' => 'marketing-flow-headers/1/foo.png'],
                'list' => ['button' => 'Ver el menú', 'sections' => [
                    ['title' => 'Categorías', 'rows' => [['id' => 'cat_1', 'title' => 'Boxes']]],
                ]],
            ],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $ref = new \ReflectionMethod($service, 'buildMarketingStepPayload');
        $ref->setAccessible(true);
        $payload = $ref->invoke($service, MarketingStepKey::PRODUCTS_MENU, $contact);

        // La lista en sí nunca lleva el encabezado de imagen incrustado.
        $this->assertArrayNotHasKey('header', $payload['interactive']);
        $this->assertSame('list', $payload['interactive']['type']);

        // Pero la imagen sí se mandó, como mensaje aparte, antes de la lista.
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/messages')
            && ($request['type'] ?? null) === 'image'
            && str_contains((string) ($request['image']['link'] ?? ''), 'marketing-flow-headers/1/foo.png'));
    }

    /**
     * El camino real que sirve "Catálogo de productos" en producción NO pasa
     * por buildMarketingStepPayload() -- pasa por
     * WhatsappService::getProductsMenu() -> MarketingCatalogBuilder, que arma
     * el listado de categorías directo. Sin este fix, el error anterior ya no
     * tumbaba el envío (WhatsappMessagePayload::list() descarta el header no
     * soportado), pero la imagen configurada se perdía en silencio.
     */
    public function test_real_catalog_category_listing_sends_the_configured_header_image_separately(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Boxes', 'action_id' => 'cat_boxes', 'is_active' => true,
        ]);
        WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Boxes', 'sku' => 'BOX-1',
            'name' => 'Box 1', 'price' => 5.99, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::PRODUCTS_MENU,
            'name' => 'Catálogo de productos',
            'message_template' => '¿Qué se te antoja?',
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'list',
                'header' => ['type' => 'image', 'image_path' => 'marketing-flow-headers/4/foo.png'],
                'catalog_source' => 'categories',
            ],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $ref = new \ReflectionMethod($service, 'getProductsMenu');
        $ref->setAccessible(true);
        $payload = $ref->invoke($service, $contact, null);

        $this->assertSame('list', $payload['interactive']['type']);
        $this->assertArrayNotHasKey('header', $payload['interactive']);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/messages')
            && ($request['type'] ?? null) === 'image'
            && str_contains((string) ($request['image']['link'] ?? ''), 'marketing-flow-headers/4/foo.png'));
    }
}
