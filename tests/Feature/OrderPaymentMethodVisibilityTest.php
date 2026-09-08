<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\OrderAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: un pedido en efectivo no requiere comprobante, así que
 * la tarjeta "Pago del pedido" (que solo aparece para transferencia/tarjeta)
 * nunca se mostraba -- el operador no tenía forma de ver con qué iba a pagar
 * el cliente. El método de pago ahora viaja siempre en el payload, aunque la
 * tarjeta de comprobante siga oculta.
 */
class OrderPaymentMethodVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function cart(string $paymentMethod): WhatsappCart
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'confirmed', 'total' => 18.40,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function test_cash_orders_expose_the_payment_method_without_showing_the_proof_card(): void
    {
        $payload = app(OrderAdminService::class)->orderPayload($this->cart('efectivo'));

        $this->assertFalse($payload['payment']['visible']);
        $this->assertSame('efectivo', $payload['payment']['method']);
        $this->assertSame('Efectivo', $payload['payment']['method_label']);
    }

    public function test_transfer_orders_still_show_the_proof_card_with_the_payment_method(): void
    {
        $cart = $this->cart('transferencia');
        $cart->markAwaitingPaymentProof();

        $payload = app(OrderAdminService::class)->orderPayload($cart->fresh());

        $this->assertTrue($payload['payment']['visible']);
        $this->assertSame('Transferencia bancaria', $payload['payment']['method_label']);
    }
}
