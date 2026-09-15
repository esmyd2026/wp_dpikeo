<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: actualizar los permisos de un rol tiraba 500 en
 * producción ("app.dpikeos.com/admin/roles/3/permissions"). Causa real:
 * RoleController usaba CompanyContext::current(), que resuelve además un
 * WhatsappBusinessProfile puntual y falla a propósito
 * (WhatsappPrimaryProfileNotConfiguredException) cuando la empresa tiene 2+
 * números conectados y ninguno marcado como principal -- exactamente el caso
 * de esta empresa. Roles y permisos son un concepto de EMPRESA, no de un
 * número de WhatsApp puntual, así que no debían depender de esa resolución
 * (mismo patrón ya corregido antes en ClientInsightsService/ClientController,
 * ver ClientCompanyIsolationTest).
 */
class RolePermissionsMultiProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, User} */
    private function companyWithTwoProfilesAndNoPrimary(string $suffix): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa '.$suffix, 'slug' => 'empresa-roles-'.$suffix, 'status' => 'active']);
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa '.$suffix.' A', 'display_name' => 'Número A',
            'phone_number' => '593998'.$suffix.'1', 'phone_number_id' => 'PHONE-ROLES-'.$suffix.'-A',
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => false,
        ]);
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa '.$suffix.' B', 'display_name' => 'Número B',
            'phone_number' => '593998'.$suffix.'2', 'phone_number_id' => 'PHONE-ROLES-'.$suffix.'-B',
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => false,
        ]);
        // super_admin, no 'admin': el rol "admin" sembrado por defecto no
        // incluye roles.view/roles.update -- quien administra roles y
        // permisos en producción es el dueño de la plataforma (super_admin,
        // que además omite el chequeo de permisos por completo).
        $role = Role::where('slug', 'super_admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $admin];
    }

    public function test_the_roles_screen_loads_for_a_company_with_two_numbers_and_no_primary(): void
    {
        [$company, $admin] = $this->companyWithTwoProfilesAndNoPrimary('100001');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.roles.index'));

        $response->assertOk();
    }

    public function test_updating_role_permissions_works_for_a_company_with_two_numbers_and_no_primary(): void
    {
        [$company, $admin] = $this->companyWithTwoProfilesAndNoPrimary('100002');
        $customRole = Role::create(['company_id' => $company->id, 'slug' => 'rol-custom-100002', 'name' => 'Rol Custom', 'is_system' => false]);
        $permissionId = Permission::where('key', 'clients.view')->value('id');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.roles.permissions.update', $customRole), [
                'permissions' => ['clients.view'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue($customRole->fresh()->permissions()->whereKey($permissionId)->exists());
    }

    /**
     * Segundo bug real encontrado en el mismo endpoint (con el log de
     * producción): wherePivot() solo existe en la relación BelongsToMany en
     * sí -- dentro de un closure de whereHas() el $q recibido es un Builder
     * normal, así que wherePivot() ahí generaba SQL inválido
     * ("Unknown column 'pivot'") apenas existía algún usuario asignado a ese
     * rol por la tabla pivote company_user (no por la columna users.role_id
     * directa). Este test asigna el rol exactamente por esa vía para
     * reproducirlo.
     */
    public function test_updating_permissions_works_when_a_user_has_the_role_only_via_the_company_pivot(): void
    {
        [$company, $admin] = $this->companyWithTwoProfilesAndNoPrimary('100004');
        $customRole = Role::create(['company_id' => $company->id, 'slug' => 'rol-custom-100004', 'name' => 'Rol Custom', 'is_system' => false]);
        $staff = User::factory()->create(['is_admin' => true, 'role_id' => null]);
        $company->users()->attach($staff->id, ['role_id' => $customRole->id]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.roles.permissions.update', $customRole), [
                'permissions' => ['clients.view'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_creating_a_role_works_for_a_company_with_two_numbers_and_no_primary(): void
    {
        [$company, $admin] = $this->companyWithTwoProfilesAndNoPrimary('100003');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.roles.store'), ['name' => 'Rol Nuevo']);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['name' => 'Rol Nuevo', 'company_id' => $company->id]);
    }
}
