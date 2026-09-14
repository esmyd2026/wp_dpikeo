<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: al calcular el envío por dirección, el recuadro
 * mostraba "Tu ubicación" (coordenadas crudas) y una nota extra de cómo se
 * calculó el costo -- el cliente solo quiere ver la sucursal más cercana y
 * la distancia/envío estimado. Los demás campos siguen existiendo en el DOM
 * (el JS los sigue completando sin errores) pero quedan ocultos por CSS.
 */
class StorefrontDeliveryQuoteDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_nearest_branch_and_distance_fee_are_visible_in_the_delivery_quote_box(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593997100003', 'phone_number_id' => 'DPIKEOS-DELIVERY-QUOTE',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        // El bloque "Tu ubicación" y la nota siguen en el DOM (el JS los llena
        // sin romperse) pero ocultos por CSS.
        $response->assertSee('#storefrontDeliveryQuoteLocationItem,#storefrontDeliveryQuoteNote{display:none}', false);
        $response->assertSee('id="storefrontDeliveryQuoteLocationItem"', false);
        $response->assertSee('id="storefrontNearestBranch"', false);
        $response->assertSee('id="storefrontDeliveryDistance"', false);
    }
}
