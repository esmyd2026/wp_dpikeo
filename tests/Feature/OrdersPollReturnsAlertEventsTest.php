<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OrderAlertEvent;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: el sondeo de "pedidos nuevos" del panel
 * (AdminController::pollNewOrders) también debe devolver los eventos de
 * pedidos YA conocidos (comprobante, factura, asesor) para que la pantalla
 * suene/actualice ante esos cambios -- con su propio cursor
 * (since_event_id), escopados a la empresa activa igual que los pedidos.
 */
class OrdersPollReturnsAlertEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function fixture(string $slug): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => $slug, 'slug' => $slug, 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => $slug, 'display_name' => $slug,
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $profile, $user];
    }

    public function test_poll_returns_events_since_the_given_cursor(): void
    {
        [$company, $profile, $user] = $this->fixture('empresa-eventos');
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $event1 = OrderAlertEvent::create([
            'business_profile_id' => $profile->id, 'whatsapp_cart_id' => $cart->id,
            'event_type' => OrderAlertEvent::TYPE_PAYMENT_PROOF, 'payload' => ['order_number' => 'ORD-001', 'contact_name' => 'Cliente'],
        ]);
        $event2 = OrderAlertEvent::create([
            'business_profile_id' => $profile->id, 'whatsapp_cart_id' => $cart->id,
            'event_type' => OrderAlertEvent::TYPE_AGENT_REQUEST, 'payload' => ['order_number' => 'ORD-001', 'contact_name' => 'Cliente'],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.orders.poll').'?since_event_id='.($event1->id - 1));

        $response->assertOk();
        $ids = collect($response->json('events'))->pluck('id');
        $this->assertTrue($ids->contains($event1->id));
        $this->assertTrue($ids->contains($event2->id));
        $this->assertSame($event2->id, $response->json('latest_event_id'));
    }

    public function test_poll_does_not_return_events_already_seen_or_from_another_company(): void
    {
        [$companyA, $profileA, $userA] = $this->fixture('empresa-a-eventos');
        [, $profileB] = $this->fixture('empresa-b-eventos');

        $contactA = WhatsappContact::create(['business_profile_id' => $profileA->id, 'phone_number' => '593990000003', 'name' => 'Cliente A']);
        $cartA = WhatsappCart::create(['contact_id' => $contactA->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593990000004', 'name' => 'Cliente B']);
        $cartB = WhatsappCart::create(['contact_id' => $contactB->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $oldEvent = OrderAlertEvent::create([
            'business_profile_id' => $profileA->id, 'whatsapp_cart_id' => $cartA->id,
            'event_type' => OrderAlertEvent::TYPE_PAYMENT_PROOF, 'payload' => ['order_number' => 'ORD-A'],
        ]);
        OrderAlertEvent::create([
            'business_profile_id' => $profileB->id, 'whatsapp_cart_id' => $cartB->id,
            'event_type' => OrderAlertEvent::TYPE_PAYMENT_PROOF, 'payload' => ['order_number' => 'ORD-B'],
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['active_company_id' => $companyA->id])
            ->getJson(route('admin.orders.poll').'?since_event_id='.$oldEvent->id);

        $response->assertOk();
        $this->assertSame([], $response->json('events'));
    }
}
