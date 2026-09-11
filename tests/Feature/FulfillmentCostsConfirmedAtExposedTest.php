<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\OrderAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "Confirmar y avisar al cliente" reenvía el
 * mensaje de costo de envío cada vez que se aprieta, aunque ya se le haya
 * avisado antes (o ya haya pagado) -- el frontend necesita saber CUÁNDO se
 * confirmó la última vez para poder advertir al operador antes de reenviar.
 * No hace falta un campo nuevo que llenar a mano: delivery_fee_confirmed_at
 * ya lo guarda el propio sistema (OrderLifecycleService::sendFulfillmentCostsMessage),
 * solo faltaba exponerlo en el payload que consume el panel.
 */
class FulfillmentCostsConfirmedAtExposedTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_payload_exposes_when_the_delivery_fee_was_confirmed(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente']);
        $confirmedAt = now()->subHour()->toIso8601String();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'payment_pending', 'total' => 8.5,
            'metadata' => [
                'service_type' => 'llevar', 'pickup_mode' => 'delivery',
                'delivery_fee' => 4.0, 'delivery_fee_pending_review' => false,
                'delivery_fee_confirmed_at' => $confirmedAt,
            ],
        ]);

        $payload = app(OrderAdminService::class)->orderPayload($cart);

        $this->assertSame($confirmedAt, $payload['fulfillment']['delivery_fee_confirmed_at']);
    }

    public function test_order_payload_has_a_null_confirmed_at_before_the_first_confirmation(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => 'payment_pending', 'total' => 8.5,
            'metadata' => ['service_type' => 'llevar', 'pickup_mode' => 'delivery', 'delivery_fee_pending_review' => true],
        ]);

        $payload = app(OrderAdminService::class)->orderPayload($cart);

        $this->assertNull($payload['fulfillment']['delivery_fee_confirmed_at']);
    }
}
