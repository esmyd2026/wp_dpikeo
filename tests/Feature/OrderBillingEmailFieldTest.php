<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\OrderAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "falta el correo aquí" -- la sección de Factura
 * del panel de Pedidos no tenía campo de correo, aunque el bot ya lo pide y
 * lo guarda (WhatsappCart.invoice_data['email']) desde antes. Se agregó el
 * campo al formulario y al servicio que lo lee/guarda/sincroniza.
 */
class OrderBillingEmailFieldTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'confirmed', 'total' => 10]);

        return [$profile, $contact, $cart];
    }

    public function test_resolve_billing_data_reads_the_email_the_bot_already_stored(): void
    {
        [, , $cart] = $this->fixture();
        $cart->invoice_data = ['billing_legal_name' => 'Juan Pérez', 'billing_id' => '0912345678', 'address' => 'Av. Siempre Viva 123', 'email' => 'juan@example.com'];
        $cart->save();

        $billing = app(OrderAdminService::class)->resolveBillingData($cart, $cart->contact);

        $this->assertSame('juan@example.com', $billing['email']);
    }

    public function test_updating_the_order_with_an_email_saves_it_and_syncs_it_to_the_contact(): void
    {
        [, $contact, $cart] = $this->fixture();

        app(OrderAdminService::class)->updateOrder($cart, [
            'billing_type' => 'cedula',
            'billing_id' => '0912345678',
            'billing_legal_name' => 'Juan Pérez',
            'address' => 'Av. Siempre Viva 123',
            'email' => 'juan@example.com',
        ], true);

        $this->assertSame('juan@example.com', $cart->fresh()->invoice_data['email']);
        $this->assertSame('juan@example.com', $contact->fresh()->billing_email);
    }
}
