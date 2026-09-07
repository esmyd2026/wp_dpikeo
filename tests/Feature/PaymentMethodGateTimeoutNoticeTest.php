<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: avisarle al cliente, justo al arrancar un pedido nuevo,
 * cuánto tiempo tiene para completarlo antes de que la sesión se cancele
 * automáticamente (ver AbandonedCartService).
 */
class PaymentMethodGateTimeoutNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    private function makeProfileAndContact(?int $timeoutMinutes): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => $timeoutMinutes ? ['abandoned_cart_timeout_minutes' => $timeoutMinutes] : [],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$profile, $contact];
    }

    public function test_payment_gate_mentions_the_configured_timeout(): void
    {
        [$profile, $contact] = $this->makeProfileAndContact(30);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $gate = $this->invoke($service, 'interceptForPaymentMethod', [$contact, ['action' => 'add', 'product_id' => 1, 'quantity' => 1, 'variation_index' => null]]);

        $this->assertStringContainsString('30 minutos', $gate['interactive']['body']['text']);
    }

    public function test_payment_gate_says_nothing_about_a_timeout_when_none_is_configured(): void
    {
        [$profile, $contact] = $this->makeProfileAndContact(null);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $gate = $this->invoke($service, 'interceptForPaymentMethod', [$contact, ['action' => 'add', 'product_id' => 1, 'quantity' => 1, 'variation_index' => null]]);

        $this->assertStringNotContainsString('minutos', $gate['interactive']['body']['text']);
    }
}
