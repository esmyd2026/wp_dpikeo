<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\OrderLifecycleService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: al confirmar un pedido de delivery pagado en efectivo,
 * el mensaje de confirmación siempre traía botones ("Mis pedidos"/"Menú
 * principal") y un genérico "Te contactaremos pronto", aunque el costo de
 * envío todavía estuviera sin confirmar por caja (porque no se pudo calcular
 * solo con la tabla de tramos km->$) -- daba a entender que ya no faltaba
 * nada, cuando en realidad el total ni siquiera era el final todavía. Y el
 * mensaje donde caja SÍ confirma el costo saltaba directo al total, sin
 * mostrar el subtotal de productos.
 */
class OrderConfirmationClarityTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappPrice, WhatsappContact, BusinessBranch} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'M1',
            'is_default' => true, 'is_active' => true,
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
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD', 'is_active' => true, 'stock' => 20,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$product, $contact, $branch];
    }

    private function cartReadyToConfirm(WhatsappContact $contact, WhatsappPrice $product, BusinessBranch $branch, array $metadataOverrides = []): WhatsappCart
    {
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'active', 'branch_id' => $branch->id, 'total' => $product->price,
            'payment_method' => 'efectivo',
            'metadata' => array_merge([
                'branch_confirmed' => true,
                'service_type' => 'llevar',
                'pickup_mode' => 'retiro',
                'note' => 'sin nota',
            ], $metadataOverrides),
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        return $cart;
    }

    public function test_confirming_a_cash_order_with_a_pending_delivery_fee_has_no_buttons_and_says_so_clearly(): void
    {
        [$product, $contact, $branch] = $this->fixture();
        $cart = $this->cartReadyToConfirm($contact, $product, $branch, [
            'pickup_mode' => 'delivery',
            'delivery_fee_pending_review' => true,
        ]);

        $service = new WhatsappService();
        $response = $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);

        $this->assertSame('text', $response['type']);
        $this->assertStringContainsString('estamos por confirmarte el total a pagar', mb_strtolower($response['text']['body']));
    }

    public function test_confirming_a_cash_order_with_no_pending_costs_still_shows_the_follow_up_buttons(): void
    {
        [$product, $contact, $branch] = $this->fixture();
        $cart = $this->cartReadyToConfirm($contact, $product, $branch);

        $service = new WhatsappService();
        $response = $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);

        $this->assertSame('interactive', $response['type']);
        $this->assertSame('button', $response['interactive']['type']);
        $buttonIds = collect($response['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('menu_pedido', $buttonIds);
    }

    public function test_fulfillment_costs_message_includes_the_products_subtotal(): void
    {
        [$product, $contact, $branch] = $this->fixture();
        $cart = $this->cartReadyToConfirm($contact, $product, $branch, [
            'pickup_mode' => 'delivery',
            'delivery_location' => ['manual_address' => 'Av. Test 123'],
            'delivery_recipient_name' => 'Juan Pérez',
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 2]);
        $expectedSubtotal = $product->price * 3; // 1 (fixture) + 2 (este segundo item)

        $result = app(OrderLifecycleService::class)->sendFulfillmentCostsMessage($cart->fresh(), 2.00, null);

        $ref = new \ReflectionMethod(OrderLifecycleService::class, 'buildFulfillmentCostsMessageBody');
        $ref->setAccessible(true);
        $body = $ref->invoke(app(OrderLifecycleService::class), $result['order']->fresh(['items']));

        $this->assertStringContainsString('Subtotal productos: $' . number_format($expectedSubtotal, 2), $body);
    }
}
