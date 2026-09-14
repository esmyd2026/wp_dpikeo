<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: el botón flotante del carrito solo aparecía al
 * entrar a una categoría porque vive dentro de #storefrontApp, oculto
 * (display:none) mientras el cliente está en la pantalla de inicio. Se
 * agregó un indicador propio, fuera de ese contenedor, sincronizado desde el
 * mismo renderCart() del carrito.
 */
class StorefrontHomeCartIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_screen_has_its_own_cart_indicator_outside_storefront_app(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593997100002', 'phone_number_id' => 'DPIKEOS-HOME-CART',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();

        // El botón debe existir y estar fuera de #storefrontApp (display:none en inicio).
        $this->assertMatchesRegularExpression(
            '/<\/main>\s*<button[^>]*id="storefrontHomeCartFab"/',
            $html
        );
        $response->assertSee('id="storefrontHomeCartFabCount"', false);
        $response->assertSee('id="storefrontHomeCartFabTotal"', false);
        // Se sincroniza desde el mismo renderCart() del carrito compartido.
        $response->assertSee('window.renderStorefrontCartFab', false);
        $response->assertSee("document.getElementById('storefrontHomeCartFab')?.addEventListener('click'", false);
    }
}
