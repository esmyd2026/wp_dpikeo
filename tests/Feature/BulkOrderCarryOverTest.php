<?php

namespace Tests\Feature;

use App\Models\BulkOrderToken;
use App\Models\BusinessBranch;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\BulkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: un cliente elegía método de pago por chat (ver
 * WhatsappService::interceptForPaymentMethod), y después usaba "Armar lista"
 * (el micrositio web) para agregar más productos. submitForContact() arma un
 * carrito NUEVO para mezclar los productos del chat con los del formulario,
 * pero no copiaba el payment_method ya elegido -- al confirmar el pedido, se
 * lo volvía a preguntar como si nunca lo hubiera elegido.
 *
 * Además, ese mismo submit dejaba un atributo sintético "order_number" (no es
 * una columna real, ver WhatsappCart::getOrderNumber()) sucio en el carrito
 * devuelto; el primer ->save() posterior (en
 * OrderConfirmationService::sendToClient(), al mandar la confirmación)
 * crasheaba con "Unknown column 'order_number'" y el cliente terminaba
 * viendo solo el mensaje de respaldo en vez de la confirmación completa.
 */
class BulkOrderCarryOverTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappPrice, WhatsappContact} */
    private function catalogProductAndContact(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Principal', 'code' => 'PRINCIPAL',
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
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'BULK-TEST',
            'name' => 'Box Tender', 'price' => 4.50, 'currency' => 'USD', 'is_active' => true, 'stock' => 20,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$product, $contact];
    }

    public function test_submitting_the_web_form_carries_over_the_payment_method_already_chosen_by_chat(): void
    {
        [$product, $contact] = $this->catalogProductAndContact();

        $previousCart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'active', 'total' => $product->price,
            'payment_method' => 'transferencia', 'payment_status' => 'pending',
        ]);
        $previousCart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        $token = BulkOrderToken::create([
            'contact_id' => $contact->id, 'token' => BulkOrderToken::generateToken(), 'expires_at' => now()->addHour(),
        ]);

        $cart = app(BulkOrderService::class)->submitFromForm($token, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->assertSame('transferencia', $cart->payment_method, 'El método de pago ya elegido por chat no debe perderse al usar el micrositio.');
        $this->assertNotEquals($previousCart->id, $cart->id, 'Debe ser un carrito nuevo (mezcla líneas), no el mismo.');
    }

    public function test_the_returned_cart_can_be_saved_again_without_crashing_on_a_synthetic_order_number_attribute(): void
    {
        [$product, $contact] = $this->catalogProductAndContact();

        $token = BulkOrderToken::create([
            'contact_id' => $contact->id, 'token' => BulkOrderToken::generateToken(), 'expires_at' => now()->addHour(),
        ]);

        $cart = app(BulkOrderService::class)->submitFromForm($token, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        // Simula exactamente lo que hace OrderConfirmationService::sendToClient()
        // justo después: agregar campos a metadata y guardar de nuevo.
        $metadata = $cart->metadata ?? [];
        $metadata['awaiting_client_confirmation'] = true;
        $cart->metadata = $metadata;
        $cart->save();

        $this->assertTrue($cart->fresh()->metadata['awaiting_client_confirmation']);
    }
}
