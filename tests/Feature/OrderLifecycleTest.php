<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\BulkOrderService;
use App\Services\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_reserves_stock_and_cancellation_releases_it(): void
    {
        [$product, $contact] = $this->catalogProduct(stock: 4);
        $order = $this->orderWithLine($contact, $product, 3);

        app(OrderLifecycleService::class)->transition($order, WhatsappCart::STATUS_CONFIRMED);

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'whatsapp_cart_id' => $order->id,
            'whatsapp_price_id' => $product->id,
            'type' => InventoryMovement::TYPE_SALE_RESERVATION,
            'quantity' => -3,
        ]);

        app(OrderLifecycleService::class)->transition($order->fresh(), WhatsappCart::STATUS_CANCELLED);

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'whatsapp_cart_id' => $order->id,
            'type' => InventoryMovement::TYPE_SALE_RELEASE,
            'quantity' => 3,
        ]);
    }

    public function test_confirmation_is_rejected_when_stock_is_insufficient(): void
    {
        [$product, $contact] = $this->catalogProduct(stock: 2);
        $order = $this->orderWithLine($contact, $product, 3);

        $this->expectExceptionMessage('Stock insuficiente para Combo de prueba.');
        app(OrderLifecycleService::class)->transition($order, WhatsappCart::STATUS_CONFIRMED);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_order_recalculates_variation_and_extras_on_the_server(): void
    {
        [$product, $contact] = $this->catalogProduct(stock: 10);
        $product->update([
            'metadata' => [
                'variations' => [['title' => 'Sin gaseosa', 'price' => 5.40]],
                'extras' => [['title' => 'Salsa especial', 'price' => 0.50]],
            ],
        ]);

        $order = app(BulkOrderService::class)->submitForContact($contact, [[
            'product_id' => $product->id,
            'quantity' => 2,
            'variation' => 'Sin gaseosa',
            'extras' => ['Salsa especial', 'Extra inventado'],
        ]]);

        $line = $order->items->first();
        $this->assertSame('Opción: Sin gaseosa · Extras: Salsa especial', $line->line_note);
        $this->assertEquals(5.90, (float) $line->price);
        $this->assertEquals(11.80, (float) $order->total);
    }

    /** @return array{WhatsappPrice, WhatsappContact} */
    private function catalogProduct(int $stock): array
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
            'menu_id' => $menu->id, 'title' => 'Combos', 'action_id' => 'cat_test',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD',
            'is_active' => true, 'stock' => $stock, 'allow_quantity_selection' => true,
            'min_quantity' => 1, 'max_quantity' => 20,
        ]);
        $contact = WhatsappContact::create(['phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$product, $contact];
    }

    private function orderWithLine(WhatsappContact $contact, WhatsappPrice $product, int $quantity): WhatsappCart
    {
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING,
            'total' => $product->price * $quantity, 'metadata' => ['order_details' => ['order_number' => 'ORD-TEST']],
        ]);
        $order->items()->create([
            'whatsapp_price_id' => $product->id, 'name' => $product->name,
            'price' => $product->price, 'quantity' => $quantity,
        ]);

        return $order;
    }
}
