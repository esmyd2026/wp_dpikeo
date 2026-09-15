<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchDeliveryFeeTier;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontDeliveryQuoteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, BusinessBranch, BusinessBranch, WhatsappPrice} */
    private function fixture(): array
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'DPIKEOS',
            'display_name' => 'DPIKEOS',
            'phone_number' => '593990001234',
            'phone_number_id' => 'DPIKEOS-DELIVERY-QUOTE',
            'access_token' => 'test-token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'is_primary' => true,
        ]);
        $nearest = BusinessBranch::create([
            'business_profile_id' => $profile->id,
            'name' => 'Urdesa',
            'code' => 'URDESA-QUOTE',
            'address' => 'Av. Víctor Emilio Estrada',
            'is_active' => true,
            'orders_enabled' => true,
            'is_default' => true,
            'latitude' => -2.1700,
            'longitude' => -79.9200,
            'delivery_fee_minimum' => 2,
        ]);
        $farther = BusinessBranch::create([
            'business_profile_id' => $profile->id,
            'name' => 'Sucursal lejana',
            'code' => 'FAR-QUOTE',
            'address' => 'Otra zona',
            'is_active' => true,
            'orders_enabled' => true,
            'latitude' => -2.3000,
            'longitude' => -79.8000,
            'delivery_fee_minimum' => 2,
        ]);
        BusinessBranchDeliveryFeeTier::create([
            'business_branch_id' => $nearest->id,
            'from_km' => 0,
            'to_km' => 5,
            'price' => 3.50,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => 'Menú',
            'type' => 'list',
            'content' => 'Productos',
            'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'business_profile_id' => $profile->id,
            'title' => 'Boxes',
            'action_id' => 'boxes-quote',
            'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'business_profile_id' => $profile->id,
            'category' => 'Boxes',
            'sku' => 'BOX-QUOTE',
            'name' => 'Box de prueba',
            'price' => 5,
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 10,
        ]);

        return [$company, $nearest, $farther, $product];
    }

    private function fakeRoadDistance(float $kilometers = 2.4): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => $kilometers * 1000]],
            ]),
        ]);
    }

    public function test_quote_returns_nearest_branch_distance_and_its_configured_tier(): void
    {
        $this->fakeRoadDistance();
        [$company, $nearest] = $this->fixture();

        $response = $this->postJson("/tienda/{$company->slug}/delivery/cotizar", [
            'latitude' => -2.1710,
            'longitude' => -79.9210,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('branch.id', $nearest->id)
            ->assertJsonPath('branch.name', 'Urdesa')
            ->assertJsonPath('distance_km', 2.7)
            ->assertJsonPath('delivery_fee', 3.5)
            ->assertJsonPath('pending_review', false);
    }

    public function test_submit_recalculates_and_applies_delivery_quote_on_the_server(): void
    {
        $this->fakeRoadDistance();
        [$company, $nearest, $farther, $product] = $this->fixture();

        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Cliente Delivery', 'phone' => '0991234567',
            'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $response = $this->postJson("/tienda/{$company->slug}/pedido", [
            'name' => 'Cliente Delivery',
            'phone' => '0991234567',
            'service_type' => 'delivery',
            // Se envía una sucursal distinta para comprobar que el servidor
            // no confía en el navegador y elige nuevamente la más cercana.
            'branch_id' => $farther->id,
            'address' => 'Av. Víctor Emilio Estrada, Guayaquil',
            'reference' => 'Casa azul',
            'latitude' => -2.1710,
            'longitude' => -79.9210,
            'payment_method' => 'efectivo',
            'requires_invoice' => false,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertOk()->assertJsonPath('total', 8.5);
        $cart = WhatsappCart::latest('id')->firstOrFail();
        $this->assertSame($nearest->id, $cart->branch_id);
        $this->assertSame(3.5, (float) $cart->metadata['delivery_fee']);
        $this->assertSame(3.5, (float) $cart->metadata['delivery_fee_applied']);
        $this->assertSame(2.7, (float) $cart->metadata['delivery_distance_km']);
        $this->assertFalse($cart->metadata['delivery_fee_pending_review']);
        $this->assertSame(8.5, (float) $cart->total);
    }
}
