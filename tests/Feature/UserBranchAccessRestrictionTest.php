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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "cada usuario que cree pueda decirle qué
 * información verá en su panel por sucursal... ahorita el usuario que cree
 * ve todo los pedidos de todas las sucursales". El mecanismo (pivote
 * business_branch_user + User::accessibleBranchIds() + WhatsappCart::
 * scopeForActiveCompany()) ya existía completo -- la causa real era que el
 * formulario de crear usuario dejaba "Acceso a todas las sucursales" MARCADO
 * por defecto, así que había que acordarse de desmarcarlo cada vez. Se
 * cambió el valor por defecto a desmarcado para forzar una elección
 * consciente por usuario.
 */
class UserBranchAccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User, BusinessBranch, BusinessBranch} */
    private function fixture(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Sucursales', 'slug' => 'empresa-sucursales', 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Sucursales', 'display_name' => 'Empresa Sucursales',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-SUC', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $branchA = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA', 'is_active' => true]);
        $branchB = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sur', 'code' => 'SUR', 'is_active' => true]);

        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $admin, $branchA, $branchB];
    }

    public function test_the_create_user_form_does_not_check_all_branches_by_default(): void
    {
        [$company, , $admin] = $this->fixture();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee('id="allBranches"', false);
        // El checkbox no debe traer el atributo "checked" -- si el HTML
        // generado lo tuviera, apareceria pegado al mismo input.
        $this->assertMatchesRegularExpression(
            '/id="allBranches"[^>]*(?<!checked)>/',
            $response->getContent()
        );
    }

    public function test_creating_a_user_restricted_to_one_branch_only_shows_that_branchs_orders(): void
    {
        [$company, $profile, $admin, $branchA, $branchB] = $this->fixture();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Cajera Urdesa',
                'username' => 'cajera.urdesa',
                'email' => 'cajera.urdesa@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => Role::where('slug', 'admin')->firstOrFail()->id,
                'branch_ids' => [$branchA->id],
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $newUser = User::where('username', 'cajera.urdesa')->firstOrFail();
        $this->assertSame([$branchA->id], $newUser->accessibleBranchIds($profile->id));

        $contactA = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000010', 'name' => 'Cliente A']);
        $contactB = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000011', 'name' => 'Cliente B']);
        $orderA = WhatsappCart::create(['contact_id' => $contactA->id, 'branch_id' => $branchA->id, 'status' => 'pending', 'total' => 10]);
        $orderB = WhatsappCart::create(['contact_id' => $contactB->id, 'branch_id' => $branchB->id, 'status' => 'pending', 'total' => 12]);

        $ordersResponse = $this->actingAs($newUser)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $ordersResponse->assertOk();
        $ordersResponse->assertSee($orderA->getOrderNumber());
        $ordersResponse->assertDontSee($orderB->getOrderNumber());
    }

    public function test_creating_a_user_without_choosing_all_or_any_branch_is_rejected(): void
    {
        [$company, , $admin] = $this->fixture();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Sin Sucursal',
                'username' => 'sin.sucursal',
                'email' => 'sin.sucursal@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => Role::where('slug', 'admin')->firstOrFail()->id,
            ]);

        $response->assertSessionHasErrors('branch_ids');
        $this->assertDatabaseMissing('users', ['username' => 'sin.sucursal']);
    }
}
