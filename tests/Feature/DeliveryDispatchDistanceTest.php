<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "en ese mensaje podemos agregar el promedio de
 * km, donde está el 'Ruta (retiro → entrega):'" -- el texto que se le
 * comparte al repartidor por WhatsApp debe incluir la distancia estimada.
 */
class DeliveryDispatchDistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_delivery_screen_includes_the_distance_in_the_route_line_sent_to_the_driver(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Delivery', 'slug' => 'empresa-delivery-km', 'status' => 'active']);
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Delivery', 'display_name' => 'Empresa Delivery',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-DELKM', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.delivery.index'));

        $response->assertOk();
        $response->assertSee('const distanceKm = dispatchResult?.distance_km ?? order.distance_km;', false);
        $response->assertSee('Ruta (retiro → entrega, ~${distanceKm} km):', false);
    }
}
