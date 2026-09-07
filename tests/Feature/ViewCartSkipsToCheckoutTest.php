<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: "Ver carrito" no debe abrir la pantalla intermedia "Tu
 * Carrito" (Seguir comprando/Armar lista/Finalizar compra) -- debe saltar
 * directo a finalizar, porque el resumen final del checkout ya deja
 * confirmar/cancelar el pedido, y esa pantalla intermedia era un paso de más
 * para llegar a lo mismo.
 */
class ViewCartSkipsToCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappPrice, WhatsappContact, BusinessBranch, BusinessBranch} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $branchA = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA', 'is_default' => true, 'is_active' => true]);
        $branchB = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sur', 'code' => 'SUR', 'is_active' => true]);
        $menu = WhatsappMenu::create(['business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'Menú', 'action_id' => 'prices_menu']);
        $category = WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test']);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$product, $contact, $branchA, $branchB];
    }

    private function cartWithItem(WhatsappContact $contact, WhatsappPrice $product): WhatsappCart
    {
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => $product->price]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        return $cart;
    }

    public function test_tapping_ver_carrito_shows_the_same_branch_list_that_finalizar_compra_would(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$product, $contact] = $this->fixture();
        $this->cartWithItem($contact, $product);

        $service = new WhatsappService();

        // handleInteractiveMessage() manda el mensaje directo (no lo
        // retorna) -- se intercepta por Http::fake() y se compara contra lo
        // que finalizarCompra() hubiera armado para el mismo carrito
        // (2 sucursales activas -> lista para elegir).
        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'ver_carrito', 'title' => 'Ver carrito']],
        ]]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['interactive']['type'] ?? null) === 'list'
                && str_contains(json_encode($data), 'sucursal_set_');
        });
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Tu Carrito'));
    }

    public function test_tapping_ver_carrito_never_shows_the_old_intermediate_cart_screen(): void
    {
        [$product, $contact] = $this->fixture();
        $this->cartWithItem($contact, $product);

        $service = new WhatsappService();
        $ref = new \ReflectionMethod($service, 'getCartContents');
        $ref->setAccessible(true);
        $oldScreen = $ref->invoke($service, $contact);

        // getCartContents() (la pantalla vieja) sigue existiendo -- la usa el
        // editor de flujo por grafo (nodo "Carrito") -- pero ya no debe ser
        // lo que dispara el botón clásico "Ver carrito".
        $this->assertStringContainsString('Tu Carrito', $oldScreen['interactive']['body']['text']);

        $newBehaviour = $this->invoke($service, 'finalizarCompra', [$contact]);
        $this->assertStringNotContainsString('Tu Carrito', json_encode($newBehaviour));
    }
}
