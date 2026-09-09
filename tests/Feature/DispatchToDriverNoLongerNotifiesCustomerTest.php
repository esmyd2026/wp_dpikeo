<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: al despachar un pedido al repartidor ya no se le avisa
 * nada al cliente por WhatsApp -- ahora es el propio repartidor (con el
 * enlace que ya recibe, ver DeliveryConfirmationService) o la operadora
 * desde el panel de delivery quienes confirman la entrega. Antes,
 * DeliveryController::dispatchToDriver llamaba a
 * WhatsappService::notifyCustomerOrderOnTheWay(), que sí le mandaba un
 * mensaje al cliente.
 */
class DispatchToDriverNoLongerNotifiesCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    public function test_dispatching_an_order_to_a_driver_sends_nothing_to_the_customer_over_whatsapp(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Delivery', 'slug' => 'empresa-delivery-'.Str::random(6), 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Delivery', 'display_name' => 'Empresa Delivery',
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA',
            'is_default' => true, 'is_active' => true, 'orders_enabled' => true, 'latitude' => -2.15, 'longitude' => -79.90,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente',
            'last_inbound_at' => now(),
        ]);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id,
            'status' => WhatsappCart::STATUS_READY, 'total' => 12.5, 'payment_method' => 'efectivo',
            'metadata' => [
                'pickup_mode' => 'delivery',
                'delivery_location' => ['latitude' => -2.138, 'longitude' => -79.893],
                'order_details' => ['order_number' => 'ORD-700'],
            ],
        ]);

        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->postJson(route('admin.delivery.dispatch', ['id' => $cart->id]), [
                'first_name' => 'Pedro', 'phone_number' => '593991234567',
                'branch_id' => $branch->id,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertArrayNotHasKey('customer_notified', $response->json());
        Http::assertNothingSent();
        $this->assertNotNull($cart->fresh()->metadata['last_dispatch_driver_id'] ?? null);
    }
}
