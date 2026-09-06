<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: "Ver carrito" y "Finalizar compra" con el
 * carrito vacío respondían siempre con un texto fijo en el código
 * ("Tu carrito está vacío. ¿Qué te gustaría comprar?"), sin importar lo que
 * el admin configuró en el paso "Resumen del carrito" del editor de flujo --
 * daba la sensación de que el flujo armado no se estaba usando de verdad.
 */
class MarketingFlowCartSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function makeProfileAndContact(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        return [$profile, $contact];
    }

    public function test_empty_cart_uses_the_configured_cart_summary_step_when_available(): void
    {
        [$profile, $contact] = $this->makeProfileAndContact();

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::CART_SUMMARY,
            'name' => 'Resumen del carrito',
            'message_template' => 'Todavía no agregaste nada, {{nombre}} 🐔',
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'button',
                'buttons' => [['id' => 'menu_productos', 'title' => 'Ver menú']],
            ],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $cartResponse = $this->invoke($service, 'getCartContents', [$contact]);
        $this->assertStringContainsString('Todavía no agregaste nada', $cartResponse['interactive']['body']['text']);

        $checkoutResponse = $this->invoke($service, 'finalizarCompra', [$contact]);
        $this->assertStringContainsString('Todavía no agregaste nada', $checkoutResponse['interactive']['body']['text']);
    }

    public function test_empty_cart_falls_back_to_the_hardcoded_message_when_no_flow_is_configured(): void
    {
        [, $contact] = $this->makeProfileAndContact();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $cartResponse = $this->invoke($service, 'getCartContents', [$contact]);
        $this->assertSame('Tu carrito está vacío. ¿Qué te gustaría comprar?', $cartResponse['interactive']['body']['text']);
    }
}
