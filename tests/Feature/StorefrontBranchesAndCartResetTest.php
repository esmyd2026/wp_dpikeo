<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchHour;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: (1) "cuando termino la compra no se resetea el
 * carrito y no me permite ir a ningún lado" -- al enviar el pedido nunca se
 * llamaba renderCart() (el contador flotante del carrito seguía mostrando
 * lo de antes) ni se restauraba el scroll del body, y no había ningún botón
 * para salir de la pantalla de éxito. (2) "cámbiale [el nav] por sucursales
 * ... que liste las sucursales que tenemos declaradas en el admin con sus
 * teléfonos y horarios y la ubicación con el mapa".
 */
class StorefrontBranchesAndCartResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bottom_nav_says_sucursales_and_lists_branch_details_in_an_accordion(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593999999998', 'phone_number_id' => 'DPIKEOS-PHONE-2',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'URDESA', 'code' => 'URD',
            'phone' => '0991234567', 'address' => 'Av. Principal 123', 'is_active' => true,
            'orders_enabled' => true, 'is_default' => true, 'latitude' => -2.15, 'longitude' => -79.9,
        ]);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 1, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Restaurantes');
        $response->assertSee('Sucursales');
        $response->assertSee('data-storefront-nav="branches"', false);
        $response->assertSee('0991234567');
        $response->assertSee('Av. Principal 123');
        $response->assertSee('Ver ubicación en el mapa');
        $response->assertSee('https://maps.google.com/?q=-2.15,-79.9', false);
        $response->assertSee('10:00 - 20:00');
    }

    public function test_a_branch_not_accepting_orders_still_appears_in_the_informational_list(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593999999997', 'phone_number_id' => 'DPIKEOS-PHONE-3',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'CENTRO (temporalmente sin pedidos)', 'code' => 'CTR',
            'is_active' => true, 'orders_enabled' => false, 'is_default' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        // Puede seguir apareciendo en la pantalla informativa de sucursales,
        // pero nunca como opción para retiro o delivery en el checkout.
        $response->assertSee('CENTRO (temporalmente sin pedidos)');
        $response->assertDontSee('data-branch-option="'.$branch->id.'"', false);
        $response->assertViewHas('branches', fn ($branches) => ! $branches->contains('id', $branch->id));
    }

    public function test_finishing_a_storefront_order_resets_the_cart_badge_and_unblocks_navigation(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593999999996', 'phone_number_id' => 'DPIKEOS-PHONE-4',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        // cart=[] nunca actualizaba el badge flotante del carrito ni
        // restauraba el scroll -- renderCart() ya hace lo primero solo
        // (toggle 'is-visible' según count), pero nadie la llamaba.
        $response->assertSee("cart = [];\n                // renderCart()", false);
        $response->assertSee('document.body.style.overflow = \'\';', false);
        $response->assertSee('id="storefrontSuccessHomeBtn"', false);
        $response->assertSee('window.clearStorefrontOrderState?.()', false);
        $response->assertSee('window.location.reload()', false);
        $response->assertSee("localStorage.removeItem(persistenceKey + '_checkout')", false);
    }
}
