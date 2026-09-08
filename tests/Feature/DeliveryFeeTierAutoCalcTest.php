<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchDeliveryFeeTier;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: la tabla de precios de delivery (desde-hasta km = $, por
 * sucursal) debe calcularse sola cuando el cliente comparte su ubicación --
 * ya no lo confirma un vendedor a mano en ese caso. Si no se puede calcular
 * (dirección a mano sin coordenadas, sucursal sin tabla, o distancia fuera
 * de todos los tramos configurados), sigue el comportamiento anterior:
 * mínimo referencial + pendiente de revisión.
 */
class DeliveryFeeTierAutoCalcTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{BusinessBranch, WhatsappContact} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        // Guayaquil aprox., para tener coordenadas reales de sucursal.
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'M1',
            'is_default' => true, 'is_active' => true, 'latitude' => -2.1500, 'longitude' => -79.9000,
            'delivery_fee_minimum' => 2.00,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$branch, $contact];
    }

    private function cartAwaitingRecipientName(WhatsappContact $contact, BusinessBranch $branch, array $location): WhatsappCart
    {
        return WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => 'active', 'total' => 10.00,
            'metadata' => [
                'pickup_mode' => 'delivery',
                'awaiting_delivery_recipient_name' => true,
                'delivery_location' => $location,
            ],
        ]);
    }

    public function test_fee_is_calculated_automatically_when_the_customer_shared_real_coordinates_and_a_tier_matches(): void
    {
        [$branch, $contact] = $this->fixture();
        BusinessBranchDeliveryFeeTier::create(['business_branch_id' => $branch->id, 'from_km' => 0, 'to_km' => 999, 'price' => 3.50]);
        $cart = $this->cartAwaitingRecipientName($contact, $branch, ['latitude' => -2.1600, 'longitude' => -79.9100]);

        $service = new WhatsappService();
        $this->invoke($service, 'applyDeliveryRecipientName', [$contact, $cart, 'Juan Pérez']);

        $cart->refresh();
        $this->assertSame(3.50, (float) $cart->metadata['delivery_fee']);
        $this->assertFalse($cart->metadata['delivery_fee_pending_review']);
        $this->assertArrayNotHasKey('delivery_fee_pending_since', $cart->metadata);
        $this->assertEqualsWithDelta(13.50, (float) $cart->total, 0.01, 'El fee calculado debe sumarse al total de una vez.');
        $this->assertNotNull($cart->metadata['delivery_distance_km']);
    }

    public function test_falls_back_to_manual_review_when_the_distance_is_outside_every_configured_tier(): void
    {
        [$branch, $contact] = $this->fixture();
        BusinessBranchDeliveryFeeTier::create(['business_branch_id' => $branch->id, 'from_km' => 0, 'to_km' => 0.01, 'price' => 3.50]);
        $cart = $this->cartAwaitingRecipientName($contact, $branch, ['latitude' => -2.1600, 'longitude' => -79.9100]);

        $service = new WhatsappService();
        $this->invoke($service, 'applyDeliveryRecipientName', [$contact, $cart, 'Juan Pérez']);

        $cart->refresh();
        $this->assertSame(2.00, (float) $cart->metadata['delivery_fee']);
        $this->assertTrue($cart->metadata['delivery_fee_pending_review']);
        $this->assertEqualsWithDelta(10.00, (float) $cart->total, 0.01, 'Sin cálculo automático, el fee no se suma al total todavía.');
    }

    public function test_falls_back_to_manual_review_when_the_branch_has_no_tiers_configured(): void
    {
        [$branch, $contact] = $this->fixture();
        $cart = $this->cartAwaitingRecipientName($contact, $branch, ['latitude' => -2.1600, 'longitude' => -79.9100]);

        $service = new WhatsappService();
        $this->invoke($service, 'applyDeliveryRecipientName', [$contact, $cart, 'Juan Pérez']);

        $cart->refresh();
        $this->assertTrue($cart->metadata['delivery_fee_pending_review']);
    }

    public function test_falls_back_to_manual_review_when_the_customer_typed_a_manual_address_without_coordinates(): void
    {
        [$branch, $contact] = $this->fixture();
        BusinessBranchDeliveryFeeTier::create(['business_branch_id' => $branch->id, 'from_km' => 0, 'to_km' => 999, 'price' => 3.50]);
        $cart = $this->cartAwaitingRecipientName($contact, $branch, ['manual_address' => 'Av. Siempre Viva 123']);

        $service = new WhatsappService();
        $this->invoke($service, 'applyDeliveryRecipientName', [$contact, $cart, 'Juan Pérez']);

        $cart->refresh();
        $this->assertTrue($cart->metadata['delivery_fee_pending_review']);
    }
}
