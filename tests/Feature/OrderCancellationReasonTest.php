<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\AbandonedCartService;
use App\Services\OrderLifecycleService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: distinguir por qué se canceló un pedido -- el cliente
 * lo canceló él mismo, se cerró solo por el timeout de carritos
 * abandonados, o lo canceló un operador desde el panel.
 */
class OrderCancellationReasonTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        return [$profile, $contact];
    }

    public function test_customer_self_cancel_is_labeled_accordingly(): void
    {
        [$profile, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 5.99]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $ref = new \ReflectionMethod($service, 'cancelarPedido');
        $ref->setAccessible(true);
        $ref->invoke($service, $contact, $cart->id);

        $this->assertSame(WhatsappCart::CANCEL_REASON_CUSTOMER, $cart->fresh()->cancellationReason());
        $this->assertSame('Cancelado por el cliente', $cart->fresh()->cancellationReasonLabel());
    }

    public function test_timeout_auto_cancel_is_labeled_accordingly(): void
    {
        [$profile, $contact] = $this->fixture();
        WhatsappChatbotConfig::create(['business_profile_id' => $profile->id, 'metadata' => ['abandoned_cart_timeout_minutes' => 30]]);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);
        $cart->forceFill(['updated_at' => now()->subHours(2)])->saveQuietly();

        app(AbandonedCartService::class)->cancelTimedOut();

        $this->assertSame(WhatsappCart::CANCEL_REASON_TIMEOUT, $cart->fresh()->cancellationReason());
        $this->assertSame('Cancelado por tiempo', $cart->fresh()->cancellationReasonLabel());
    }

    public function test_operator_cancel_from_the_panel_is_labeled_accordingly(): void
    {
        [, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 5.99]);

        app(OrderLifecycleService::class)->transition($cart, WhatsappCart::STATUS_CANCELLED, 42, WhatsappCart::CANCEL_REASON_OPERATOR);

        $this->assertSame(WhatsappCart::CANCEL_REASON_OPERATOR, $cart->fresh()->cancellationReason());
        $this->assertSame('Cancelado por el operador', $cart->fresh()->cancellationReasonLabel());
    }

    public function test_historical_cancellations_without_an_explicit_reason_still_infer_one(): void
    {
        [, $contact] = $this->fixture();

        $operatorCancelled = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CANCELLED, 'total' => 5.99,
            'metadata' => ['status_changed_by' => 7],
        ]);
        $this->assertSame('Cancelado por el operador', $operatorCancelled->cancellationReasonLabel());

        $customerCancelled = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CANCELLED, 'total' => 5.99,
            'metadata' => ['cancelled_via' => 'whatsapp'],
        ]);
        $this->assertSame('Cancelado por el cliente', $customerCancelled->cancellationReasonLabel());

        $unknown = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CANCELLED, 'total' => 5.99]);
        $this->assertSame('Cancelado', $unknown->cancellationReasonLabel());
    }

    public function test_a_non_cancelled_order_has_no_cancellation_reason(): void
    {
        [, $contact] = $this->fixture();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 5.99]);

        $this->assertNull($cart->cancellationReason());
        $this->assertNull($cart->cancellationReasonLabel());
    }
}
