<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\WhatsappBusinessProfile;
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
        $response->assertDontSee("address=`Ubicación detectada:", false);
        // El respaldo (link de mapa) solo se arma al confirmar, si comparte
        // ubicación y no escribió nada -- mismo criterio que usa el bot.
        $response->assertSee('Ubicación compartida: https://maps.google.com', false);
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
            "<img src=\"{{ \$category['image'] }}\" alt=\"{{ \$category['title'] }}\">",
            $source
        );
    }

    public function test_security_headers_are_present_on_the_storefront_response(): void
    {
        $this->fixture('06');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
        // Nunca se manda HSTS sobre HTTP -- el request de prueba no es HTTPS.
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
