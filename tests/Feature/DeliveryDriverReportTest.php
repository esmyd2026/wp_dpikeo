<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeliveryDriver;
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
 * Pedido explícito: "indicadores de los envíos por repartidor, saber
 * cuántos envíos ha hecho un repartidor con el total en cantidades de
 * envío y los totales de los costos de envío, y por rangos de fecha", con
 * un enlace desde el detalle al pedido correspondiente.
 */
class DeliveryDriverReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User, WhatsappContact} */
    private function fixture(): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Delivery', 'slug' => 'empresa-delivery', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Delivery', 'display_name' => 'Empresa Delivery',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-DELIV', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$company, $profile, $admin, $contact];
    }

    public function test_it_aggregates_deliveries_and_shipping_fees_per_driver_within_the_date_range(): void
    {
        [$company, $profile, $admin, $contact] = $this->fixture();

        $driverA = DeliveryDriver::create(['business_profile_id' => $profile->id, 'first_name' => 'Pedro', 'last_name' => 'Ruiz', 'phone_number' => '593991111111', 'is_active' => true]);
        $driverB = DeliveryDriver::create(['business_profile_id' => $profile->id, 'first_name' => 'Ana', 'last_name' => 'Solis', 'phone_number' => '593992222222', 'is_active' => true]);

        // Dos entregas de Pedro dentro del período.
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 15,
            'metadata' => ['pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driverA->id, 'delivery_fee_applied' => 2.5],
            'created_at' => now(),
        ]);
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 20,
            'metadata' => ['pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driverA->id, 'delivery_fee_applied' => 3],
            'created_at' => now(),
        ]);
        // Una entrega de Ana, con envío pendiente de revisión.
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 10,
            'metadata' => ['pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driverB->id, 'delivery_fee' => 4, 'delivery_fee_pending_review' => true],
            'created_at' => now(),
        ]);
        // Pedido de delivery sin repartidor asignado -- no debe romper el reporte.
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 8,
            'metadata' => ['pickup_mode' => 'delivery'],
            'created_at' => now(),
        ]);
        // Retiro en el local -- no debe contarse como delivery.
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 12,
            'metadata' => ['pickup_mode' => 'retiro'],
            'created_at' => now(),
        ]);
        // Fuera del rango de fechas. "created_at" no es mass-assignable (los
        // timestamps los pone Eloquent automáticamente), así que hay que
        // forzarlo después de crear el registro.
        $oldOrder = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 99,
            'metadata' => ['pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driverA->id, 'delivery_fee_applied' => 9],
        ]);
        $oldOrder->forceFill(['created_at' => now()->subDays(60)])->save();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.delivery', ['period' => '30d']));

        $response->assertOk();
        $response->assertSee('Pedro Ruiz');
        $response->assertSee('Ana Solis');
        $response->assertSee('Sin repartidor asignado');
        $response->assertSee('$5.50'); // total cobrado por Pedro (2.5 + 3)
        $response->assertSee('$35.00'); // valor de los pedidos de Pedro (15 + 20)
        $response->assertSee('1 pendiente'); // envío de Ana sin confirmar
        $response->assertDontSee('$99.00'); // fuera de rango, no debe aparecer
    }

    public function test_the_order_number_links_to_the_orders_screen_to_open_that_order_detail(): void
    {
        [$company, $profile, $admin, $contact] = $this->fixture();
        $driver = DeliveryDriver::create(['business_profile_id' => $profile->id, 'first_name' => 'Pedro', 'phone_number' => '593991111111', 'is_active' => true]);
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 15,
            'metadata' => ['pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driver->id, 'delivery_fee_applied' => 2.5, 'order_details' => ['order_number' => 'ORD-555']],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.delivery'));

        $response->assertOk();
        $response->assertSee(route('admin.orders', ['open_order' => $order->id]), false);
        $response->assertSee('ORD-555');
    }

    public function test_a_role_without_the_permission_is_denied(): void
    {
        [$company, , ] = $this->fixture();
        $role = Role::create(['slug' => 'no-delivery-reports', 'name' => 'Sin permiso', 'company_id' => null]);
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.delivery'));

        $response->assertRedirect(route('admin.dashboard'));
    }
}
