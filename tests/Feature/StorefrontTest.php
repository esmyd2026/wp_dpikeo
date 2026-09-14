<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_shows_the_default_company_storefront(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $company->storefrontSetting()->updateOrCreate([], [
            'google_maps_api_key' => 'AIzaSyTestMapsKey',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'DPIKEOS',
            'display_name' => 'DPIKEOS',
            'phone_number' => '593999999999',
            'phone_number_id' => 'DPIKEOS-PHONE',
            'access_token' => 'test-token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Productos')
            ->assertSee('data-drawer-nav="branches"', false)
            ->assertSee('data-drawer-nav="account"', false)
            ->assertSee('data-icon="products"', false)
            ->assertSee('data-icon="branches"', false)
            ->assertSee('data-icon="account"', false)
            ->assertSee('data-icon="logout"', false)
            ->assertSee('id="storefrontDrawerLogout"', false)
            ->assertSee("drawerLogout.style.display='flex'", false)
            ->assertDontSee('>Entrar<', false)
            ->assertSee('DPIKEOS')
            ->assertSee('catalogUrl', false)
            ->assertSee('id="bulkAddonAdd"', false)
            ->assertSee('is-cart-preview', false)
            ->assertSee("allCategory.title || 'Todos'", false)
            ->assertSee('Elige una opción para continuar', false)
            ->assertSee('Elegir entrega y comenzar', false)
            ->assertDontSee('Primero elige cómo recibir tu pedido', false)
            ->assertSee('syncStorefrontCustomizerActions', false)
            ->assertSee('.storefront-location{z-index:1600}', false)
            ->assertSee('window.openStorefrontOrderMode', false)
            ->assertSee('id="storefrontCartDeliveryChange"', false)
            ->assertSee("el('storefrontCartDeliveryChange')?.addEventListener", false)
            ->assertSee('storefront_cart_'.$company->id, false)
            ->assertSee('storefront_order_'.$company->id, false)
            ->assertSee('restorePersistedCart', false)
            ->assertSee('restoreStorefrontDraft', false)
            ->assertSee("button.setAttribute('aria-pressed'", false)
            ->assertSee('bulk-order-addon-option.is-selected .bulk-order-addon-option-icon:not(.is-choice)::after', false)
            ->assertSee("'<span aria-hidden=\"true\">＋</span>'", false)
            ->assertSee('bulk-order-addon-copy', false)
            ->assertSee("persistenceKey + '_checkout'", false)
            ->assertSee('window.clearStorefrontOrderState', false)
            ->assertSee('window.storefrontOrder?.confirmed', false)
            ->assertSee('id="storefrontOrderDetail"', false)
            ->assertSee('renderOrderProgress', false)
            ->assertSee('Historial del pedido', false)
            ->assertSee('id="storefrontForgotPassword"', false)
            ->assertSee('id="storefrontRecoveryRequestForm"', false)
            ->assertSee('id="storefrontRecoveryResetForm"', false)
            ->assertSee('id="storefrontDeliveryQuote"', false)
            ->assertSee('id="storefrontNearestBranch"', false)
            ->assertSee('id="storefrontDeliveryDistance"', false)
            ->assertSee('const deliveryQuoteUrl=', false)
            ->assertSee('window.updateStorefrontDeliveryQuote', false)
            ->assertSee('if(!customerAuthenticated)return Promise.resolve()', false)
            ->assertSee('drawer.inert=!open', false)
            ->assertSee('overflow:visible!important;', false)
            ->assertSee("window.addEventListener('storefront:start-order',window.openStorefrontOrderMode)", false);
        $response->assertSee('PlaceAutocompleteElement', false)
            ->assertSee("autocomplete.addEventListener('gmp-select'", false)
            ->assertSee("place.fetchFields({fields:['formattedAddress','location']})", false)
            ->assertSee('class="storefront-address-row"', false)
            ->assertSee("if(mode==='delivery'&&window.storefrontOrder.latitude===null", false)
            ->assertSee("geolocateButton.addEventListener('click',()=>requestStorefrontLocation(true))", false)
            ->assertDontSee('new google.maps.Geocoder(', false)
            ->assertDontSee('new google.maps.places.Autocomplete(', false);
        $this->assertSame($company->id, $profile->company_id);
    }

    public function test_catalog_endpoint_never_returns_another_companys_products(): void
    {
        [$companyA, $profileA] = $this->companyWithProduct('tienda-a', 'Producto A');
        [, $profileB] = $this->companyWithProduct('tienda-b', 'Producto B');
        WhatsappPrice::query()->where('business_profile_id', $profileA->id)->update([
            'metadata' => json_encode([
                'variations' => [[
                    'title' => 'Completa',
                    'description' => 'Incluye bebida y acompañamiento',
                    'price' => 7.50,
                ]],
                'extras' => [[
                    'title' => 'Salsa especial',
                    'description' => 'Porción individual',
                    'price' => 0.50,
                    'image' => 'additional-images/salsa.webp',
                ]],
            ]),
        ]);
        WhatsappMenu::query()
            ->where('business_profile_id', $profileA->id)
            ->where('action_id', 'prices_menu')
            ->firstOrFail()
            ->update(['metadata' => ['storefront_all_category_image' => 'category-images/todos.webp']]);

        $response = $this->getJson(route('storefront.catalog', $companyA));

        $response->assertOk()->assertJsonFragment(['name' => 'Producto A']);
        $response->assertJsonPath('all_category.title', 'Todos');
        $response->assertJsonPath('all_category.image', '/storage/category-images/todos.webp');
        $response->assertJsonFragment([
            'title' => 'Completa',
            'description' => 'Incluye bebida y acompañamiento',
            'price' => 7.5,
        ]);
        $response->assertJsonFragment([
            'title' => 'Salsa especial',
            'image' => '/storage/additional-images/salsa.webp',
        ]);
        $response->assertJsonMissing(['name' => 'Producto B']);

        $promoProduct = WhatsappPrice::query()
            ->where('business_profile_id', $profileA->id)
            ->where('name', 'Producto A')
            ->firstOrFail();
        $promoProduct->update(['is_promo' => true, 'promo_price' => 4.25]);
        WhatsappPrice::create([
            'menu_item_id' => $promoProduct->menu_item_id,
            'business_profile_id' => $profileA->id,
            'category' => 'Combos',
            'sku' => 'TIENDA-A-REGULAR',
            'name' => 'Producto regular A',
            'price' => 6,
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 5,
        ]);

        $promoResponse = $this->getJson(route('storefront.catalog', $companyA).'?promo=1');

        $promoResponse->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Producto A')
            ->assertJsonPath('products.0.price', 4.25)
            ->assertJsonPath('products.0.is_promo', true)
            ->assertJsonMissing(['name' => 'Producto regular A']);
        $this->assertNotSame($profileA->id, $profileB->id);
    }

    private function companyWithProduct(string $slug, string $productName): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => $slug,
            'display_name' => $slug,
            'phone_number' => '593'.random_int(100000000, 999999999),
            'phone_number_id' => strtoupper($slug),
            'access_token' => 'token-'.$slug,
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'is_primary' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id,
            'title' => 'Combos', 'action_id' => 'combos', 'is_active' => true,
        ]);
        WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id,
            'category' => 'Combos', 'sku' => strtoupper($slug).'-1', 'name' => $productName,
            'price' => 5, 'currency' => 'USD', 'is_active' => true, 'stock' => 5,
        ]);

        return [$company, $profile];
    }
}
