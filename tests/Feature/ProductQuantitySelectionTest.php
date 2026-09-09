<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
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
        $service = new WhatsappService;

        $details = $this->invoke($service, 'getProductDetails', [$product->id]);
        $buttonIds = collect($details['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('pedir_cantidad_'.$product->id.'_base', $buttonIds);
        $this->assertNotContains('quick_add_'.$product->id.'_base', $buttonIds);

        // Antes de la cantidad, el bot pregunta el método de pago (una vez por pedido).
        $paymentGate = $this->invoke($service, 'showQuantitySelection', [$contact, $product->id, null]);
        $this->assertSame('list', $paymentGate['interactive']['type']);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $gateRowIds = collect($paymentGate['interactive']['action']['sections'][0]['rows'])->pluck('id')->all();
        $this->assertContains('pago_efectivo_'.$cart->id, $gateRowIds);
        $this->assertSame(['action' => 'quantity', 'product_id' => $product->id, 'variation_index' => null], $cart->fresh()->metadata['pending_first_action']);

        // Elige efectivo -> retoma automáticamente y muestra la cantidad pendiente.
        $qtyStep = $this->invoke($service, 'procesarPagoEfectivo', [$contact, $cart->id]);
        $this->assertSame('list', $qtyStep['interactive']['type']);
        $rowIds = collect($qtyStep['interactive']['action']['sections'][0]['rows'])->pluck('id')->all();
        $this->assertSame([
            'cantidad_1_'.$product->id,
            'cantidad_2_'.$product->id,
            'cantidad_3_'.$product->id,
            'otra_cantidad_'.$product->id,
            'volver_productos',
        ], $rowIds);
        $this->assertArrayNotHasKey('pending_first_action', $cart->fresh()->metadata);

        $this->invoke($service, 'addToCart', [$contact, $product->id, 4, null]);
        $cart->refresh();
        $this->assertSame(4, $cart->items->first()->quantity);
        $this->assertSame('efectivo', $cart->payment_method);
    }

    /**
     * Pedido explícito: el pago con tarjeta se resuelve fuera del chat (web
     * externa), y no hay forma de saber si el cliente llegó a pagar ahí --
     * dejar el carrito "active" indefinidamente hacía que cualquier intento
     * nuevo del cliente quedara bloqueado repitiendo el link para siempre
     * (y, si nunca volvía a escribir, el timeout de carritos abandonados lo
     * cerraba solo con el aviso de "no continuarás"). Un intento nuevo debe
     * arrancar de cero, no quedar atascado.
     */
    public function test_choosing_card_payment_then_a_new_attempt_starts_a_fresh_cart_instead_of_blocking(): void
    {
        [$product, $contact] = $this->catalogProduct(allowQuantity: true, min: 1, max: 8);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $product->business_profile_id,
            'metadata' => ['card_payment_url' => 'https://pagar.example.com'],
        ]);
        $service = new WhatsappService;

        $paymentGate = $this->invoke($service, 'showQuantitySelection', [$contact, $product->id, null]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $gateRowIds = collect($paymentGate['interactive']['action']['sections'][0]['rows'])->pluck('id')->all();
        $this->assertContains('pago_tarjeta_'.$cart->id, $gateRowIds);

        $linkResponse = $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);
        $this->assertStringContainsString('pagar.example.com', json_encode($linkResponse));
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->fresh()->status);

        // Un intento nuevo arma OTRO carrito y vuelve a preguntar el método
        // de pago -- no repite el link del pedido ya cerrado.
        $freshAttempt = $this->invoke($service, 'showQuantitySelection', [$contact, $product->id, null]);
        $this->assertSame('list', $freshAttempt['interactive']['type']);
        $this->assertStringNotContainsString('pagar.example.com', json_encode($freshAttempt));
        $newCart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $this->assertNotEquals($cart->id, $newCart->id);
    }

    public function test_product_without_quantity_selection_adds_one_unit_directly(): void
    {
        [$product] = $this->catalogProduct(allowQuantity: false);
        $service = new WhatsappService;

        $details = $this->invoke($service, 'getProductDetails', [$product->id]);
        $buttonIds = collect($details['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('quick_add_'.$product->id.'_base', $buttonIds);
        $this->assertNotContains('pedir_cantidad_'.$product->id.'_base', $buttonIds);
    }

    /** @return array{WhatsappPrice, WhatsappContact} */
    private function catalogProduct(bool $allowQuantity, int $min = 1, int $max = 99): array
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
