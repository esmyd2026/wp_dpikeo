<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "Pide y retira" sin pago adelantado dejaba
 * pedidos sin retirar -- ahora, para ese modo, solo se acepta pago por
 * transferencia (ni efectivo ni tarjeta). Delivery conserva las 3 formas de
 * pago de siempre.
 */
class StorefrontPickupOnlyTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, BusinessBranch, WhatsappPrice} */
    private function fixture(string $suffix): array
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593996'.$suffix, 'phone_number_id' => 'DPIKEOS-PICKUP-'.$suffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URD-'.$suffix,
            'is_active' => true, 'orders_enabled' => true, 'is_default' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Boxes', 'action_id' => 'boxes', 'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Boxes',
            'sku' => 'BOX-PICKUP-'.$suffix, 'name' => 'Box Tender', 'price' => 4.5, 'currency' => 'USD', 'is_active' => true, 'stock' => 5,
        ]);

        return [$company, $branch, $product];
    }

    private function orderPayload(BusinessBranch $branch, WhatsappPrice $product, string $serviceType, string $paymentMethod, string $phoneSuffix): array
    {
        return [
            'name' => 'Cliente Prueba', 'phone' => '099222'.$phoneSuffix, 'service_type' => $serviceType,
            'branch_id' => $branch->id, 'payment_method' => $paymentMethod, 'requires_invoice' => false,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];
    }

    public function test_pickup_with_cash_is_rejected(): void
    {
        [$company, $branch, $product] = $this->fixture('300001');

        $response = $this->postJson("/tienda/{$company->slug}/pedido", $this->orderPayload($branch, $product, 'pickup', 'efectivo', '0001'));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.payment_method.0', 'Para "Pide y retira" solo aceptamos pago por transferencia.');
    }

    public function test_pickup_with_card_is_rejected(): void
    {
        [$company, $branch, $product] = $this->fixture('300002');

        $response = $this->postJson("/tienda/{$company->slug}/pedido", $this->orderPayload($branch, $product, 'pickup', 'tarjeta', '0002'));

        $response->assertStatus(422);
    }

    public function test_pickup_with_transfer_is_accepted(): void
    {
        [$company, $branch, $product] = $this->fixture('300003');
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Cliente Prueba', 'phone' => '0992220003',
            'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $response = $this->postJson("/tienda/{$company->slug}/pedido", $this->orderPayload($branch, $product, 'pickup', 'transferencia', '0003'));

        $response->assertOk()->assertJsonPath('ok', true);
    }

    public function test_delivery_still_accepts_cash(): void
    {
        [$company, $branch, $product] = $this->fixture('300004');
        $payload = $this->orderPayload($branch, $product, 'delivery', 'efectivo', '0004');
        $payload['address'] = 'Av. Siempre Viva 123';
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Cliente Prueba', 'phone' => '0992220004',
            'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $response = $this->postJson("/tienda/{$company->slug}/pedido", $payload);

        $response->assertOk()->assertJsonPath('ok', true);
    }

    public function test_the_checkout_disables_cash_and_card_options_when_pickup_is_selected(): void
    {
        [$company] = $this->fixture('300005');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee("['efectivo','tarjeta'].forEach(value=>{", false);
        $response->assertSee("if(isPickup&&paymentSelect.value!=='transferencia')", false);
    }
}
