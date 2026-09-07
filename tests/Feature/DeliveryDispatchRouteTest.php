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
 * Pedido explícito: "al delivery le deberia llegar de donde tiene que buscar
 * el pedido" -- el operador confirma la sucursal de retirada al despachar
 * (por seguridad), y con esas coordenadas más las del cliente se arma la
 * ruta completa (origen sucursal -> destino cliente) que se manda al
 * repartidor, en vez de un simple pin del destino.
 */
class DeliveryDispatchRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{company: Company, profile: WhatsappBusinessProfile, user: User, branchA: BusinessBranch, branchB: BusinessBranch, cart: WhatsappCart} */
    private function deliveryFixture(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'empresa-test',
            'slug' => 'empresa-test-' . Str::random(6), 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'empresa-test', 'display_name' => 'empresa-test',
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => 'PHONE-' . Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $branchA = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA',
            'is_default' => true, 'is_active' => true, 'latitude' => -2.15, 'longitude' => -79.90,
        ]);
        $branchB = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Sur', 'code' => 'SUR',
            'is_active' => true, 'latitude' => -2.20, 'longitude' => -79.95,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente',
        ]);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branchA->id,
            'status' => WhatsappCart::STATUS_READY, 'total' => 12.5, 'payment_method' => 'efectivo',
            'metadata' => [
                'pickup_mode' => 'delivery',
                'delivery_location' => ['latitude' => -2.138, 'longitude' => -79.893],
                'inventory_reserved_at' => now()->toIso8601String(),
                'order_details' => ['order_number' => 'ORD-500'],
            ],
        ]);

        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'user', 'branchA', 'branchB', 'cart');
    }

    public function test_branches_endpoint_lists_active_branches_and_the_orders_current_branch(): void
    {
        $f = $this->deliveryFixture();

        $response = $this->actingAs($f['user'])
            ->withSession(['active_company_id' => $f['company']->id])
            ->getJson(route('admin.delivery.branches', ['id' => $f['cart']->id]));

        $response->assertOk();
        $response->assertJson(['current_branch_id' => $f['branchA']->id]);
        $this->assertCount(2, $response->json('branches'));
    }

    public function test_dispatching_without_confirming_a_branch_is_rejected(): void
    {
        $f = $this->deliveryFixture();

        $response = $this->actingAs($f['user'])
            ->withSession(['active_company_id' => $f['company']->id])
            ->postJson(route('admin.delivery.dispatch', ['id' => $f['cart']->id]), [
                'first_name' => 'Pedro', 'phone_number' => '593991234567',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('branch_id');
    }

    public function test_dispatch_response_includes_the_route_from_the_confirmed_branch_to_the_customer(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        $f = $this->deliveryFixture();

        // El operador confirma la sucursal Sur, distinta a la que el pedido
        // traía por defecto (Urdesa) -- por ejemplo porque el cliente está
        // más cerca de esa otra sucursal.
        $response = $this->actingAs($f['user'])
            ->withSession(['active_company_id' => $f['company']->id])
            ->postJson(route('admin.delivery.dispatch', ['id' => $f['cart']->id]), [
                'first_name' => 'Pedro', 'phone_number' => '593991234567',
                'branch_id' => $f['branchB']->id,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'branch_name' => 'Sur']);
        $this->assertNotNull($response->json('distance_km'));
        $mapsUrl = $response->json('maps_url');
        $this->assertStringContainsString('origin=-2.2,-79.95', $mapsUrl);
        $this->assertStringContainsString('destination=-2.138,-79.893', $mapsUrl);

        $this->assertSame($f['branchB']->id, $f['cart']->fresh()->branch_id);
    }

    public function test_orders_list_exposes_a_directions_link_when_both_ends_have_coordinates(): void
    {
        $f = $this->deliveryFixture();

        $response = $this->actingAs($f['user'])
            ->withSession(['active_company_id' => $f['company']->id])
            ->getJson(route('admin.delivery.data'));

        $response->assertOk();
        $order = collect($response->json('orders'))->firstWhere('id', $f['cart']->id);
        $this->assertNotNull($order);
        $this->assertStringContainsString('maps/dir/?api=1', $order['maps_url']);
        $this->assertGreaterThan(0, $order['distance_km']);
    }
}
