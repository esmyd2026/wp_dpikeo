<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\AbandonedCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: si un pedido en "payment_pending" quedó esperando por
 * culpa del NEGOCIO (todavía no se confirma el costo de envío, o el cliente
 * ya mandó el comprobante y falta que caja lo verifique), el timeout de
 * carritos abandonados no debe cancelarlo ni mandarle al cliente el aviso de
 * "parece que no continuarás" -- el cliente ya hizo lo que le tocaba.
 */
class AbandonedCartWaitingOnBusinessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function makeCompany(int $timeoutMinutes): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => 'PHONE-' . random_int(1000, 9999), 'whatsapp_business_id' => 'WABA-TEST',
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['abandoned_cart_timeout_minutes' => $timeoutMinutes],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593' . random_int(100000000, 999999999), 'name' => 'Cliente']);

        return [$profile, $contact];
    }

    public function test_a_cart_with_payment_proof_already_submitted_is_never_auto_cancelled(): void
    {
        [, $contact] = $this->makeCompany(30);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 10,
            'payment_method' => 'transferencia',
        ]);
        $cart->attachPaymentProof(['message_id' => 'wamid.test', 'type' => 'image']);
        $cart->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

        $closed = app(AbandonedCartService::class)->cancelTimedOut();

        $this->assertSame(0, $closed);
        $this->assertSame(WhatsappCart::STATUS_PAYMENT_PENDING, $cart->fresh()->status);
    }

    public function test_a_cart_still_waiting_for_the_business_to_confirm_delivery_cost_is_never_auto_cancelled(): void
    {
        [, $contact] = $this->makeCompany(30);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 10,
            'payment_method' => 'transferencia',
            'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_pending_review' => true],
        ]);
        $cart->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

        $closed = app(AbandonedCartService::class)->cancelTimedOut();

        $this->assertSame(0, $closed);
        $this->assertSame(WhatsappCart::STATUS_PAYMENT_PENDING, $cart->fresh()->status);
    }

    public function test_a_cart_genuinely_still_waiting_on_the_customer_for_the_proof_is_still_cancelled(): void
    {
        [, $contact] = $this->makeCompany(30);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 10,
            'payment_method' => 'transferencia',
        ]);
        $cart->markAwaitingPaymentProof();
        $cart->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

        $closed = app(AbandonedCartService::class)->cancelTimedOut();

        $this->assertSame(1, $closed);
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->fresh()->status);
    }
}
