<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bug real encontrado en producción: crear un usuario desde el panel nunca
 * lo asignaba a ninguna empresa -- el usuario nuevo chocaba con
 * CompanyContext::current() ("no tiene ninguna empresa autorizada") apenas
 * iniciaba sesión, y la única forma de arreglarlo era a mano por SQL/tinker.
 */
class UserAdminCompanyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    private function makeCompany(string $slug): Company
    {
        return Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    public function test_creating_a_user_attaches_them_to_the_creators_active_company(): void
    {
        $company = $this->makeCompany('empresa-creadora');
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $creator = User::factory()->create(['is_admin' => true, 'role_id' => $adminRole->id]);
        $company->users()->attach($creator->id);

        $response = $this->actingAs($creator)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Usuario Nuevo',
                'username' => 'usuario.nuevo',
                'email' => 'usuario.nuevo@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $adminRole->id,
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $newUser = User::where('email', 'usuario.nuevo@example.com')->firstOrFail();
        $this->assertTrue($newUser->companies()->where('companies.id', $company->id)->exists());

        // Y con eso ya puede resolver su empresa activa sin explotar.
        $this->actingAs($newUser);
        $ctx = \App\Support\CompanyContext::current();
        $this->assertSame($company->id, $ctx->company->id);
    }

    public function test_creating_a_super_admin_does_not_require_a_company_attachment(): void
    {
        $company = $this->makeCompany('empresa-creadora-2');
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();

        $creator = User::factory()->create(['is_admin' => true, 'role_id' => $superAdminRole->id, 'role' => 'super_admin']);
        $company->users()->attach($creator->id);

        $response = $this->actingAs($creator)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Super Nuevo',
                'username' => 'super.nuevo',
                'email' => 'super.nuevo@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $superAdminRole->id,
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $newUser = User::where('email', 'super.nuevo@example.com')->firstOrFail();
        $this->assertSame(0, $newUser->companies()->count());
        $this->assertTrue($newUser->isSuperAdmin());
    }
}
