<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: si el cliente ya llenó el carrito y recién ahí
 * cambia a "Tarjeta", no tiene sentido hacerlo pasar por todo el formulario
 * de entrega/facturación para terminar mandándolo a la página externa -- el
 * botón del carrito debe llevarlo directo al enlace de cobro en vez de
 * avanzar al checkout. Por eso es importante que la forma de pago se
 * pregunte al inicio (ver StorefrontCardPaymentRedirectTest para el caso del
 * checkout normal).
 */
class StorefrontCardPaymentSkipsCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $suffix): Company
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593998300'.$suffix, 'phone_number_id' => 'DPIKEOS-CARDSKIP-'.$suffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/pagar'],
        ]);

        return $company;
    }

    public function test_the_cart_button_goes_to_the_card_payment_link_instead_of_the_checkout_form(): void
    {
        $this->fixture('01');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('function goToCardPayment()', false);
        $response->assertSee("window.open(cardPaymentUrl, '_blank', 'noopener')", false);
        $response->assertSee("if (isStorefront && window.storefrontOrder?.payment_method === 'tarjeta')", false);
        $response->assertSee('Continuar en dpikeos.ec', false);
        $response->assertSee('function applyCardPaymentButtonLabel()', false);
    }

    public function test_without_a_configured_card_link_the_cart_button_falls_back_to_a_message(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593998300'.'02', 'phone_number_id' => 'DPIKEOS-CARDSKIP-02',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('const cardPaymentUrl = null', false);
        $response->assertSee('El pago con tarjeta no está disponible en este momento.', false);
    }
}
