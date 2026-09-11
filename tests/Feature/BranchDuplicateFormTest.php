<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchDeliveryFeeTier;
use App\Models\BusinessBranchHour;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "poder duplicar pero que se me llene el
 * formulario para la nueva sucursal para no repetir todo de nuevo uno a
 * uno" -- el botón "Duplicar" no llama al backend, solo entrega al
 * JavaScript de la pantalla los datos completos de la sucursal (nombre,
 * horario, tarifas de delivery, etc.) para que el formulario de "Nueva
 * sucursal" se rellene solo.
 */
class BranchDuplicateFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    public function test_each_branch_card_carries_a_full_duplicate_payload_for_the_new_branch_form(): void
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Sucursales Dup', 'slug' => 'empresa-sucursales-dup', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Dup', 'display_name' => 'Empresa Dup',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-DUP', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        // El rol "admin" de fábrica no trae branches.menu/pricing_settings.view
        // (ver config/permissions.php) -- esta pantalla la administra super_admin.
        $role = Role::where('slug', 'super_admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'CENTENARIO - SUR', 'code' => 'SUR',
            'phone' => '0984031492', 'address' => 'Capitán Nájera y Chile, Guayaquil',
            'reservations_info' => 'Reservas al 099-000-0000', 'is_active' => true, 'is_default' => true,
            'orders_enabled' => true, 'dine_in_enabled' => true, 'delivery_fee_minimum' => 2.5,
        ]);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 1, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00']);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 0, 'is_closed' => true]);
        BusinessBranchDeliveryFeeTier::create(['business_branch_id' => $branch->id, 'from_km' => 0, 'to_km' => 3, 'price' => 1.5]);
        BusinessBranchDeliveryFeeTier::create(['business_branch_id' => $branch->id, 'from_km' => 3, 'to_km' => null, 'price' => 2.5]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.branches.index'));

        $response->assertOk();
        $response->assertSee('js-branch-duplicate', false);
        $response->assertSee('⧉ Duplicar');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-branch=\'([^\']+)\'/', $html);
        preg_match('/data-branch=\'([^\']+)\'/', $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1]), true);

        $this->assertSame('CENTENARIO - SUR', $payload['name']);
        $this->assertSame('SUR', $payload['code']);
        $this->assertSame('0984031492', $payload['phone']);
        $this->assertTrue($payload['is_active']);
        $this->assertTrue($payload['orders_enabled']);
        $this->assertTrue($payload['dine_in_enabled']);
        $this->assertSame('10:00', $payload['hours']['1']['opens_at']);
        $this->assertSame('20:00', $payload['hours']['1']['closes_at']);
        $this->assertFalse($payload['hours']['1']['is_closed']);
        $this->assertTrue($payload['hours']['0']['is_closed']);
        $this->assertCount(2, $payload['tiers']);
        $this->assertEqualsWithDelta(1.5, (float) $payload['tiers'][0]['price'], 0.001);
        $this->assertEqualsWithDelta(2.5, (float) $payload['tiers'][1]['price'], 0.001);
    }
}
