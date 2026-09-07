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
 * Pedidos explícitos: 1) el botón que aparece tras agregar un producto debía
 * decir "Finalizar compra (N)" en vez de "Ver carrito (N)", ya que desde el
 * fix anterior ese botón salta directo al checkout, no a la pantalla vieja
 * del carrito. 2) la pantalla final "¿Confirmas tu pedido?" debía incluir
 * también la opción de "Seguir comprando", no solo Confirmar/Cancelar.
 */
class CartAndCheckoutButtonsTest extends TestCase
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

    public function test_adding_a_product_shows_a_finalizar_compra_button_with_the_item_count(): void
    {
        [$contact, $product] = $this->fixture();
        // Con el método de pago ya elegido, addToCart() agrega el producto
        // directo en vez de mostrar primero la pantalla de "elige cómo
        // pagar" (interceptForPaymentMethod).
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0, 'payment_method' => 'efectivo']);

        $service = new WhatsappService();
        $response = $this->invoke($service, 'addToCart', [$contact, $product->id, 3]);

        $buttons = $response['interactive']['action']['buttons'];
        $cartButton = collect($buttons)->firstWhere('reply.id', 'ver_carrito');

        $this->assertNotNull($cartButton);
        $this->assertSame('Finalizar compra(3)', $cartButton['reply']['title']);
        $this->assertStringNotContainsString('Ver carrito', json_encode($buttons));
    }

    public function test_the_final_order_confirmation_screen_offers_seguir_comprando(): void
    {
        [$contact, $product] = $this->fixture();

        // Se saltan a mano los pasos previos del checkout (sucursal, tipo de
        // servicio, pago, nota) para llegar directo al resumen final --
        // finalizarCompra() es la misma función en sus dos puntos: hace de
        // gate de esos pasos Y arma la pantalla "¿Confirmas tu pedido?" al
        // final, una vez que ya no falta nada.
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'active', 'total' => $product->price,
            'payment_method' => 'efectivo', 'note' => 'Sin nota',
            'metadata' => ['branch_confirmed' => true, 'service_type' => 'llevar', 'pickup_mode' => 'retiro'],
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        $service = new WhatsappService();
        $summary = $this->invoke($service, 'finalizarCompra', [$contact]);

        $buttonIds = collect($summary['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();
        $this->assertContains('seguir_comprando', $buttonIds);
        $this->assertContains('confirmar_pedido_'.$cart->id, $buttonIds);
        $this->assertContains('cancelar_pedido_'.$cart->id, $buttonIds);
    }
}
