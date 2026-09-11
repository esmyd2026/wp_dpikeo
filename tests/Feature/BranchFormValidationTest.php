<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "estaba duplicando una sucursal, luego que la
 * modifiqué mira el error... pero me borró todo el formulario, me toca
 * volver a crear todo." Dos bugs reales: (1) el código no aceptaba espacios
 * ni tildes (rechazado por alpha_dash) y con "Duplicar" es más fácil
 * toparse con eso al tocar el nombre/código copiado; (2) ninguno de los dos
 * formularios traía old(), así que CUALQUIER error de validación (este u
 * otro) vaciaba todo lo ya escrito.
 */
class BranchFormValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function fixture(): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Sucursales V', 'slug' => 'empresa-sucursales-v', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa V', 'display_name' => 'Empresa V',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-V', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'super_admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $admin];
    }

    public function test_a_code_with_spaces_or_accents_is_normalized_instead_of_rejected(): void
    {
        [$company, $profile, $admin] = $this->fixture();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.branches.store'), [
                '_form_id' => 'new',
                'name' => 'Sucursal Norte',
                'code' => 'Norte 2 - Kennedy',
            ]);

        $response->assertSessionDoesntHaveErrors('code');
        $this->assertSame('NORTE-2-KENNEDY', BusinessBranch::where('business_profile_id', $profile->id)->where('name', 'Sucursal Norte')->value('code'));
    }

    public function test_duplicating_a_branch_without_changing_the_code_gives_a_clear_error_not_a_db_crash(): void
    {
        [$company, $profile, $admin] = $this->fixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.branches.store'), [
                '_form_id' => 'new',
                'name' => 'Urdesa',
                'code' => 'Urdesa',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, BusinessBranch::where('business_profile_id', $profile->id)->count());
    }

    public function test_a_failed_create_keeps_everything_typed_instead_of_wiping_the_form(): void
    {
        [$company, $profile, $admin] = $this->fixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA', 'is_active' => true]);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.branches.store'), [
                '_form_id' => 'new',
                'name' => 'Urdesa Norte',
                'code' => 'URDESA',
                'phone' => '0991112233',
                'address' => 'Av. Copiada 123',
            ])
            ->assertSessionHasErrors('code');

        $page = $this->get(route('admin.branches.index'));

        $page->assertOk();
        $page->assertSee('value="Urdesa Norte"', false);
        $page->assertSee('value="URDESA"', false);
        $page->assertSee('0991112233');
        $page->assertSee('Av. Copiada 123');
    }

    public function test_a_failed_edit_on_one_branch_does_not_leak_into_or_wipe_another_branchs_card(): void
    {
        [$company, $profile, $admin] = $this->fixture();
        $branchA = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Centro', 'code' => 'CENTRO', 'is_active' => true, 'phone' => '0990000001']);
        $branchB = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sur', 'code' => 'SUR', 'is_active' => true, 'phone' => '0990000002']);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.branches.update', $branchB), [
                '_form_id' => (string) $branchB->id,
                'name' => 'Sur Editado',
                'code' => 'CENTRO',
                'phone' => '0990000002',
            ])
            ->assertSessionHasErrors('code');

        $page = $this->get(route('admin.branches.index'));

        $page->assertOk();
        // B conserva lo que se intentó guardar, para poder corregirlo.
        $page->assertSee('value="Sur Editado"', false);
        // A sigue mostrando sus propios datos, sin filtración del intento de B.
        $page->assertSee('value="Centro"', false);
        $page->assertSee('value="0990000001"', false);
    }
}
