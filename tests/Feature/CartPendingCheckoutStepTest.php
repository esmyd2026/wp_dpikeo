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
 * Bug real reportado en vivo: un cliente que apenas estaba navegando el
 * catálogo (carrito activo pero todavía sin ningún producto agregado) escribía
 * "hola" y el bot respondía "¡Hola! ... solo falta este paso de tu pedido"
 * pegado justo antes de "tu carrito está vacío" -- un mensaje contradictorio.
 * cartHasPendingCheckoutStep() consideraba "pendiente" cualquier carrito sin
 * branch_confirmed, sin importar si tenía productos o ni siquiera existía
 * intención real de comprar todavía.
 */
class CartPendingCheckoutStepTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappPrice, WhatsappContact} */
    private function catalogProduct(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);
        $contact = WhatsappContact::create(['phone_number' => '593990000002', 'name' => 'Cliente', 'business_profile_id' => $profile->id]);

        return [$product, $contact];
    }

    public function test_an_empty_active_cart_never_has_a_pending_checkout_step(): void
    {
        [, $contact] = $this->catalogProduct();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        $service = new WhatsappService();

        $this->assertFalse($this->invoke($service, 'cartHasPendingCheckoutStep', [$cart]));
    }

    public function test_a_cart_with_items_but_no_branch_confirmed_still_has_a_pending_checkout_step(): void
    {
        [$product, $contact] = $this->catalogProduct();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => $product->price]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        $service = new WhatsappService();

        $this->assertTrue($this->invoke($service, 'cartHasPendingCheckoutStep', [$cart->fresh()]));
    }

    public function test_greeting_while_browsing_an_empty_cart_does_not_produce_the_contradictory_combined_message(): void
    {
        [, $contact] = $this->catalogProduct();
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        $service = new WhatsappService();

        $response = $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.greeting-test',
            'text' => ['body' => 'hola'],
        ]]);

        $body = json_encode($response);
        $this->assertStringNotContainsString('solo falta este paso de tu pedido', $body);
        $this->assertStringNotContainsString('carrito está vacío en este momento', $body);
    }
}
