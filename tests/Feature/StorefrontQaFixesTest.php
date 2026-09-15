<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\BulkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcciones a partir del informe de QA externo del 14/09/2026 sobre
 * app.dpikeos.com: dirección de entrega corrupta al combinar geolocalización
 * + texto manual, falta de zoom táctil, falta de alt descriptivo en
 * categorías, falta de validación visible de teléfono/correo, y carga
 * innecesaria de Google Maps para quien no elige delivery.
 */
class StorefrontQaFixesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $suffix): Company
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        CompanyStorefrontSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            ['google_maps_api_key' => 'AIzaSyTestQaFixesKey']
        );
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593997200'.$suffix, 'phone_number_id' => 'DPIKEOS-QA-'.$suffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        return $company;
    }

    public function test_geolocation_no_longer_writes_raw_coordinates_into_the_editable_address_field(): void
    {
        $this->fixture('01');

        $response = $this->get('/');

        $response->assertOk();
        // Ya no se escribe "Ubicación detectada: lat, lon" dentro del campo
        // editable -- eso era lo que se concatenaba con lo que el cliente
        // tecleaba encima, sin separador.
        $response->assertDontSee('address=`Ubicación detectada:', false);
        // Las coordenadas se traducen a una dirección legible con Google.
        $response->assertSee('reverseGeocodeStorefrontAddress', false);
        // Si Google no logra resolver la calle, se deja un link editable a la
        // ubicación exacta (nunca un mensaje de error con el campo vacío) y
        // el texto queda seleccionado para que escribir encima lo reemplace.
        $response->assertSee('Ubicación compartida: https://maps.google.com', false);
        $response->assertSee('addressField?.select?.()', false);
        $response->assertDontSee('no pudimos obtener el nombre de la calle', false);
    }

    public function test_google_maps_script_is_not_loaded_upfront_only_lazily_when_delivery_is_selected(): void
    {
        $this->fixture('02');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('<script async defer src="https://maps.googleapis.com', false);
        $response->assertSee('window.loadStorefrontMapsScript=function()', false);
        $response->assertSee('window.loadStorefrontMapsScript?.()', false);
    }

    public function test_checkout_validates_phone_and_email_format_with_visible_feedback(): void
    {
        $this->fixture('03');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="storefrontCustomerPhoneError"', false);
        $response->assertSee('id="storefrontCustomerEmailError"', false);
        $response->assertSee('isValidStorefrontPhone', false);
        $response->assertSee('isValidStorefrontEmail', false);
        $response->assertSee('Revisa los datos resaltados en rojo', false);
    }

    public function test_checkout_requires_the_customer_to_create_an_account_before_confirming(): void
    {
        $this->fixture('05');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="storefrontCheckoutAccountBenefit"', false);
        $response->assertSee('Necesitas una cuenta para confirmar');
        $response->assertSee('id="storefrontCheckoutAccountButton"', false);
    }

    public function test_touch_zoom_is_not_disabled_on_the_storefront(): void
    {
        $this->fixture('04');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('maximum-scale=1', false);
    }

    public function test_category_images_have_a_descriptive_alt_text(): void
    {
        // Verificado sobre la fuente del Blade: no depende de que la
        // empresa de prueba tenga categorías con imagen ya cargada.
        $source = file_get_contents(resource_path('views/storefront/show.blade.php'));

        $this->assertStringContainsString(
            "data-catalog-image src=\"{{ \$category['image'] }}\" alt=\"{{ \$category['title'] }}\">",
            $source
        );
    }

    public function test_loaded_category_image_containers_are_white_without_gray_bands(): void
    {
        $source = file_get_contents(resource_path('views/storefront/show.blade.php'));
        $catalogSource = file_get_contents(resource_path('views/bulk-order/partials/form-app.blade.php'));

        $this->assertStringContainsString(
            '.storefront-category>.storefront-category-icon{display:flex;width:120px;height:82px;margin:0 auto 16px;align-items:center;justify-content:center;background:#fff!important;',
            $source
        );
        $this->assertStringContainsString(
            '.bulk-order-app[data-channel="storefront"] .bulk-order-category-icon.catalog-image-shell{background:#fff!important}',
            $source
        );
        $this->assertStringContainsString(
            '.bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button:hover{background:#fff;transform:none}',
            $catalogSource
        );
    }

    public function test_storefront_catalog_has_a_responsive_product_search(): void
    {
        $this->fixture('07');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="storefrontCatalogSearch"', false);
        $response->assertSee('placeholder="Busca tu producto favorito"', false);
        $response->assertSee("el('storefrontCatalogSearch')?.addEventListener('input'", false);
        $response->assertSee("el('bulkSearch').value = event.target.value", false);
        $response->assertSee('.bulk-order-app[data-channel="storefront"] .bulk-order-filters{display:block!important;', false);
    }

    /**
     * Pedido explícito en vivo: si el cliente había buscado algo y después
     * toca una categoría distinta, ese texto seguía aplicándose como filtro
     * sobre la categoría nueva -- podía parecer que esa categoría no tenía
     * productos, cuando en realidad la búsqueda vieja los estaba tapando.
     */
    public function test_switching_category_clears_any_leftover_search_text(): void
    {
        $this->fixture('08');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('function clearCatalogSearch()', false);
        $response->assertSee("el('bulkCategory').value = btn.dataset.category;", false);
        $response->assertSee("clearCatalogSearch();\n            await loadCatalog();", false);
        $response->assertSee("() => { showingPromotions = false; clearCatalogSearch(); loadCatalog(); }", false);
    }

    /**
     * Pedido explícito en vivo: antes de tocar "Elegir entrega y comenzar"
     * se ocultaban precio, variaciones, cantidad y "Pagar ahora" en la ficha
     * del producto -- el cliente no podía ni ver cuánto costaba sin
     * comprometerse primero a empezar el pedido. Ahora se ven siempre; el
     * botón principal sigue abriendo el selector de pago/entrega primero si
     * todavía no se eligió.
     */
    public function test_product_price_and_variations_are_visible_before_starting_an_order(): void
    {
        $source = file_get_contents(resource_path('views/bulk-order/partials/form-app.blade.php'));

        $this->assertStringNotContainsString(
            ':not(.is-order-started) .storefront-product-base-price',
            $source
        );
        $this->assertStringNotContainsString(
            ':not(.is-order-started) .storefront-variation-section',
            $source
        );
        $this->assertStringContainsString('storefrontMustStartOrder()', $source);
    }

    public function test_saved_addresses_are_normalized_before_using_array_methods(): void
    {
        $this->fixture('09');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('window.getStorefrontSavedAddresses=()=>normalizeSavedAddresses(window.storefrontSavedAddresses);', false);
        $response->assertSee('window.getStorefrontSavedAddresses().find(item=>item.is_default)', false);
        $response->assertDontSee('(window.storefrontSavedAddresses || []).find', false);
        $response->assertDontSee('(window.storefrontSavedAddresses||[]).find', false);
    }

    public function test_product_search_also_matches_the_category_name(): void
    {
        $company = $this->fixture('08');
        $profile = WhatsappBusinessProfile::query()->where('company_id', $company->id)->where('phone_number_id', 'DPIKEOS-QA-08')->firstOrFail();
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => 'Menú',
            'type' => 'list',
            'content' => 'Catálogo',
            'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'business_profile_id' => $profile->id,
            'title' => 'Postres especiales',
            'action_id' => 'postres-especiales',
            'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'business_profile_id' => $profile->id,
            'category' => 'Postres especiales',
            'sku' => 'HELADO-01',
            'name' => 'Helado de vainilla',
            'description' => "• Incluye helado\n• Incluye cobertura",
            'price' => 2.50,
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 10,
        ]);

        $payload = app(BulkOrderService::class)->catalogPayload(null, 'Postres especiales', $profile->id);

        $this->assertSame([$product->id], collect($payload['products'])->pluck('id')->all());
        $this->assertSame("• Incluye helado\n• Incluye cobertura", $payload['products'][0]['description']);
    }

    public function test_security_headers_are_present_on_the_storefront_response(): void
    {
        $this->fixture('06');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        // Modo "solo reporte" por ahora (ver comentario en SecurityHeaders):
        // avisa en consola sin bloquear nada hasta confirmar en un navegador
        // real que la lista blanca de dominios está completa.
        $response->assertHeader('Content-Security-Policy-Report-Only');
        // Nunca se manda HSTS sobre HTTP -- el request de prueba no es HTTPS.
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
