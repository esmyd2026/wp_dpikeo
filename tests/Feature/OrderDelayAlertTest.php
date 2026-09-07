<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\OrderDelayAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido no proactivo real reportado: un pedido "para llevar" quedaba
 * esperando que caja confirme el costo de empaque, y si tardaban, el cliente
 * se quedaba sin ninguna novedad y el staff sin ninguna alerta. Este servicio
 * corre cada 5 minutos (ver Kernel) y, pasados 15 minutos sin que caja
 * confirme, le avisa a ambos -- una sola vez por cada vez que el pedido queda
 * "atascado" (dedupe por Cache, no en cada corrida del cron).
 */
class OrderDelayAlertTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart} */
    private function overdueCartSetup(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['delivery_dispatch_numbers' => '593987000001, 593987000002'],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente', 'status' => 'active']);

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10,
            'metadata' => [
                'service_type' => 'llevar',
                'confirmed_at' => now()->subMinutes(20)->toIso8601String(),
                'pickup_fee_pending_since' => now()->subMinutes(20)->toIso8601String(),
            ],
        ]);

        return [$profile, $contact, $cart];
    }

    public function test_alerts_customer_and_staff_when_overdue_past_the_threshold(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, , $cart] = $this->overdueCartSetup();

        $alerted = app(OrderDelayAlertService::class)->alertOverdue(15);

        $this->assertSame(1, $alerted);
        Http::assertSentCount(3); // 1 al cliente + 2 al staff (dos números configurados)
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), $cart->getOrderNumber())
            && str_contains(json_encode($r->data()), 'Seguimos procesando'));
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'Pedido demorado'));
    }

    public function test_does_not_alert_before_the_threshold_is_reached(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, , $cart] = $this->overdueCartSetup();
        $cart->metadata = array_merge($cart->metadata, ['pickup_fee_pending_since' => now()->subMinutes(5)->toIso8601String()]);
        $cart->save();

        $alerted = app(OrderDelayAlertService::class)->alertOverdue(15);

        $this->assertSame(0, $alerted);
        Http::assertNothingSent();
    }

    public function test_does_not_re_alert_the_same_stuck_order_on_a_second_run(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        $this->overdueCartSetup();

        $service = app(OrderDelayAlertService::class);
        $service->alertOverdue(15);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]); // reset counter
        $second = $service->alertOverdue(15);

        $this->assertSame(0, $second, 'La segunda corrida no debe volver a avisar sobre el mismo pedido.');
        Http::assertNothingSent();
    }

    public function test_stops_counting_as_overdue_once_staff_confirms_the_pending_cost(): void
    {
        [, , $cart] = $this->overdueCartSetup();

        $metadata = $cart->metadata;
        unset($metadata['pickup_fee_pending_since']);
        $metadata['pickup_fee'] = 1.0;
        $cart->metadata = $metadata;
        $cart->save();

        $this->assertFalse($cart->fresh()->hasPendingFulfillmentCosts());
    }
}
