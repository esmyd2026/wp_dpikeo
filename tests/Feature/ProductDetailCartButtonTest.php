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
 * Pedido explícito: en la pantalla de detalle de un producto, si el
 * carrito todavía está vacío no tiene sentido ofrecer "Ver carrito" --
 * finalizarCompra() solo mostraría "tu carrito está vacío". Con nada
 * agregado, ese botón debe invitar a seguir viendo productos en su lugar.
 */
class ProductDetailCartButtonTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{WhatsappContact, WhatsappPrice} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);
        $menu = WhatsappMenu::create(['business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'Menú', 'action_id' => 'prices_menu']);
        $category = WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test']);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);

        return [$contact, $product];
    }

    public function test_shows_ver_mas_productos_when_the_cart_is_still_empty(): void
    {
        [$contact, $product] = $this->fixture();

        $service = app(WhatsappService::class);
        $payload = $this->invoke($service, 'getProductDetails', [$product->id, $contact]);

        $buttons = $payload['interactive']['action']['buttons'];
        $lastButton = end($buttons)['reply'];
        $this->assertSame('productos', $lastButton['id']);
        $this->assertStringContainsString('Ver productos', $lastButton['title']);
    }

    public function test_shows_finalizar_compra_when_the_cart_already_has_items(): void
    {
        [$contact, $product] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 5.99, 'payment_method' => 'efectivo']);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 2]);

        $service = app(WhatsappService::class);
        $payload = $this->invoke($service, 'getProductDetails', [$product->id, $contact]);

        $buttons = $payload['interactive']['action']['buttons'];
        $lastButton = end($buttons)['reply'];
        $this->assertSame('ver_carrito', $lastButton['id']);
        $this->assertStringContainsString('Finalizar compra', $lastButton['title']);
    }
}
