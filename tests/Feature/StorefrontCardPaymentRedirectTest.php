<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "si el método de pago seleccionado es tarjeta
 * deberíamos mandarle un mensaje con el enlace a nuestra página web
 * dpikeos.ec para que realice de manera segura su pago, que por el momento
 * en este micrositio no contamos con ese método de pago" -- el micrositio no
 * procesa tarjeta, así que debe redirigir al link de cobro configurado
 * (el mismo que ya usa el bot, WhatsappChatbotConfig metadata.card_payment_url)
 * en vez de dejar el pedido pendiente sin ninguna forma real de pagarlo.
 */
class StorefrontCardPaymentRedirectTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, BusinessBranch, WhatsappPrice} */
    private function storefrontFixture(string $suffix): array
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593991'.$suffix, 'phone_number_id' => 'DPIKEOS-CARD-'.$suffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URD',
            'is_active' => true, 'orders_enabled' => true, 'is_default' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id,
            'title' => 'Boxes', 'action_id' => 'boxes', 'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id,
            'category' => 'Boxes', 'sku' => 'BOX-CARD-'.$suffix, 'name' => 'Box Tender',
            'price' => 4.5, 'currency' => 'USD', 'is_active' => true, 'stock' => 5,
        ]);

        return [$company, $profile, $branch, $product];
    }

    private function registerStorefrontCustomer(Company $company, string $phoneSuffix): void
    {
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Ana Torres', 'phone' => '099111'.$phoneSuffix,
            'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();
    }

    private function orderPayload(BusinessBranch $branch, WhatsappPrice $product, string $paymentMethod, string $phoneSuffix): array
    {
        return [
            // Delivery, no pickup: "pide y retira" solo admite transferencia
            // (ver StorefrontPickupOnlyTransferPaymentTest) y estos tests
            // prueban específicamente tarjeta/efectivo, no el modo de entrega.
            'name' => 'Ana Torres', 'phone' => '099111'.$phoneSuffix, 'service_type' => 'delivery', 'address' => 'Av. Siempre Viva 123',
            'branch_id' => $branch->id, 'payment_method' => $paymentMethod, 'requires_invoice' => false,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];
    }

    public function test_choosing_tarjeta_without_a_configured_payment_link_is_rejected_before_creating_the_order(): void
    {
        [$company, , $branch, $product] = $this->storefrontFixture('100001');

        $response = $this->postJson("/tienda/{$company->slug}/pedido", $this->orderPayload($branch, $product, 'tarjeta', '0001'));

        $response->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseMissing('whatsapp_carts', ['payment_method' => 'tarjeta']);
    }

    public function test_choosing_tarjeta_with_a_configured_link_redirects_and_cancels_the_order(): void
    {
        [$company, $profile, $branch, $product] = $this->storefrontFixture('100002');
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/pagar'],
        ]);

        $this->get("/tienda/{$company->slug}")
            ->assertOk()
            ->assertSee('id="storefrontCardFields"', false)
            ->assertSee('id="storefrontCheckoutCardPaymentLink"', false)
            ->assertSee('https://dpikeos.ec/pagar', false)
            ->assertSee('Por el momento este micrositio no procesa pagos con tarjeta')
            ->assertSee("el('storefrontCardFields')?.classList.toggle('is-open', isCard)", false);

        $this->registerStorefrontCustomer($company, '0002');
        $response = $this->postJson("/tienda/{$company->slug}/pedido", $this->orderPayload($branch, $product, 'tarjeta', '0002'));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('card_payment_redirect', true)
            ->assertJsonPath('payment_url', 'https://dpikeos.ec/pagar');

        $cart = WhatsappCart::where('payment_method', 'tarjeta')->firstOrFail();
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->status);
        $this->assertSame(WhatsappCart::CANCEL_REASON_CARD_PAYMENT, $cart->cancellationReason());
        // El pedido se cierra de inmediato -- el stock reservado se libera.
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_efectivo_still_works_normally_when_a_card_payment_link_is_configured(): void
    {
        [$company, $profile, $branch, $product] = $this->storefrontFixture('100003');
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/pagar'],
        ]);

        $this->registerStorefrontCustomer($company, '0003');
        $response = $this->postJson("/tienda/{$company->slug}/pedido", $this->orderPayload($branch, $product, 'efectivo', '0003'));

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonMissingPath('card_payment_redirect');
        $cart = WhatsappCart::where('payment_method', 'efectivo')->firstOrFail();
        $this->assertNotSame(WhatsappCart::STATUS_CANCELLED, $cart->status);
    }
}
