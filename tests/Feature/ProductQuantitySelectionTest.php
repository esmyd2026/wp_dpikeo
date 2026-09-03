<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * allow_quantity_selection es true por defecto en la base de datos (ver
 * migración 2025_05_25_000000_...), así que por defecto TODOS los productos
 * deben preguntar cantidad en WhatsApp antes de agregar al carrito, igual
 * que ya hace el formulario web. Antes de este fix, getProductDetails()
 * ignoraba esa columna y siempre agregaba 1 unidad de un toque.
 */
class ProductQuantitySelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_with_quantity_selection_asks_quantity_before_adding(): void
    {
        [$product, $contact] = $this->catalogProduct(allowQuantity: true, min: 1, max: 8);
        $service = new WhatsappService();

        $details = $this->invoke($service, 'getProductDetails', [$product->id]);
        $buttonIds = collect($details['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('pedir_cantidad_' . $product->id . '_base', $buttonIds);
        $this->assertNotContains('quick_add_' . $product->id . '_base', $buttonIds);

        $qtyStep = $this->invoke($service, 'showQuantitySelection', [$contact, $product->id, null]);
        $this->assertSame('list', $qtyStep['interactive']['type']);
        $rowIds = collect($qtyStep['interactive']['action']['sections'][0]['rows'])->pluck('id')->all();
        $this->assertContains('cantidad_1_' . $product->id, $rowIds);
        $this->assertContains('cantidad_8_' . $product->id, $rowIds);
        $this->assertNotContains('cantidad_9_' . $product->id, $rowIds, 'No debe superar max_quantity.');

        $this->invoke($service, 'addToCart', [$contact, $product->id, 4, null]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->first();
        $this->assertSame(4, $cart->items->first()->quantity);
    }

    public function test_product_without_quantity_selection_adds_one_unit_directly(): void
    {
        [$product] = $this->catalogProduct(allowQuantity: false);
        $service = new WhatsappService();

        $details = $this->invoke($service, 'getProductDetails', [$product->id]);
        $buttonIds = collect($details['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('quick_add_' . $product->id . '_base', $buttonIds);
        $this->assertNotContains('pedir_cantidad_' . $product->id . '_base', $buttonIds);
    }

    /** @return array{WhatsappPrice, WhatsappContact} */
    private function catalogProduct(bool $allowQuantity, int $min = 1, int $max = 99): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'QTY-TEST',
            'name' => 'Combo de prueba', 'price' => 9.99, 'currency' => 'USD',
            'is_active' => true, 'stock' => 20,
            'allow_quantity_selection' => $allowQuantity, 'min_quantity' => $min, 'max_quantity' => $max,
        ]);
        $contact = WhatsappContact::create(['phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$product, $contact];
    }

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }
}
