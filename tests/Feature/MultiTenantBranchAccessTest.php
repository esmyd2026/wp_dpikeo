<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiTenantBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    private function tenant(string $slug): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => $slug, 'slug' => $slug, 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => $slug, 'display_name' => $slug,
            'phone_number' => '593'.random_int(100000000, 999999999),
            'phone_number_id' => strtoupper($slug).'-'.Str::random(6),
            'access_token' => 'token-'.$slug, 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        return compact('company', 'profile');
    }

    private function order(WhatsappBusinessProfile $profile, BusinessBranch $branch, string $number): WhatsappCart
    {
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593'.random_int(100000000, 999999999),
            'name' => 'Cliente '.$number,
        ]);

        return WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id,
            'status' => WhatsappCart::STATUS_PENDING, 'total' => 10,
            'metadata' => ['order_details' => ['order_number' => $number], 'pickup_mode' => 'delivery'],
        ]);
    }

    public function test_automatic_branch_never_comes_from_another_company(): void
    {
        $a = $this->tenant('empresa-a');
        $b = $this->tenant('empresa-b');
        BusinessBranch::create(['business_profile_id' => $a['profile']->id, 'name' => 'A', 'code' => 'A', 'is_active' => true, 'is_default' => true]);
        $branchB = BusinessBranch::create(['business_profile_id' => $b['profile']->id, 'name' => 'B', 'code' => 'B', 'is_active' => true, 'is_default' => true]);
        $contactB = WhatsappContact::create(['business_profile_id' => $b['profile']->id, 'phone_number' => '593991111111', 'name' => 'B']);

        $cart = WhatsappCart::create(['contact_id' => $contactB->id, 'status' => 'active', 'total' => 0]);

        $this->assertSame($branchB->id, $cart->branch_id);
    }

    public function test_user_assigned_to_one_branch_only_sees_that_branches_orders(): void
    {
        $tenant = $this->tenant('empresa-sucursales');
        $branchA = BusinessBranch::create(['business_profile_id' => $tenant['profile']->id, 'name' => 'Norte', 'code' => 'N', 'is_active' => true]);
        $branchB = BusinessBranch::create(['business_profile_id' => $tenant['profile']->id, 'name' => 'Sur', 'code' => 'S', 'is_active' => true]);
        $orderA = $this->order($tenant['profile'], $branchA, 'ORD-A');
        $this->order($tenant['profile'], $branchB, 'ORD-B');

        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $tenant['company']->users()->attach($user->id, ['role_id' => $role->id]);
        $user->branches()->attach($branchA->id);

        $this->actingAs($user)->withSession(['active_company_id' => $tenant['company']->id]);

        $this->assertSame([$orderA->id], WhatsappCart::reportable()->forActiveCompany()->pluck('id')->all());
    }

    public function test_delivery_endpoint_does_not_expose_another_company_or_unassigned_branch(): void
    {
        $a = $this->tenant('delivery-a');
        $b = $this->tenant('delivery-b');
        $branchA = BusinessBranch::create(['business_profile_id' => $a['profile']->id, 'name' => 'A', 'code' => 'A', 'is_active' => true]);
        $branchB = BusinessBranch::create(['business_profile_id' => $b['profile']->id, 'name' => 'B', 'code' => 'B', 'is_active' => true]);
        $orderA = $this->order($a['profile'], $branchA, 'ORD-DEL-A');
        $orderB = $this->order($b['profile'], $branchB, 'ORD-DEL-B');

        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $a['company']->users()->attach($user->id, ['role_id' => $role->id]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $a['company']->id])
            ->getJson(route('admin.delivery.data'));

        $response->assertOk();
        $ids = collect($response->json('orders'))->pluck('id');
        $this->assertTrue($ids->contains($orderA->id));
        $this->assertFalse($ids->contains($orderB->id));
    }

    public function test_company_admin_cannot_edit_a_user_from_another_company(): void
    {
        $a = $this->tenant('usuarios-a');
        $b = $this->tenant('usuarios-b');
        $role = Role::where('slug', 'admin')->firstOrFail();
        $adminA = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $userB = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $a['company']->users()->attach($adminA->id, ['role_id' => $role->id]);
        $b['company']->users()->attach($userB->id, ['role_id' => $role->id]);

        $this->actingAs($adminA)
            ->withSession(['active_company_id' => $a['company']->id])
            ->get(route('admin.users.edit', $userB))
            ->assertNotFound();
    }

    public function test_the_same_user_can_have_different_roles_in_each_company(): void
    {
        $a = $this->tenant('roles-a');
        $b = $this->tenant('roles-b');
        $permission = Permission::where('key', 'orders.view')->firstOrFail();
        $roleA = Role::create(['company_id' => $a['company']->id, 'slug' => 'cajera-a', 'name' => 'Cajera A']);
        $roleB = Role::create(['company_id' => $b['company']->id, 'slug' => 'consulta-b', 'name' => 'Consulta B']);
        $roleA->permissions()->attach($permission->id);
        $user = User::factory()->create(['is_admin' => true]);
        $a['company']->users()->attach($user->id, ['role_id' => $roleA->id]);
        $b['company']->users()->attach($user->id, ['role_id' => $roleB->id]);

        $this->actingAs($user)->withSession(['active_company_id' => $a['company']->id]);
        $this->assertTrue(app(PermissionService::class)->userCan($user, 'orders.view'));

        session(['active_company_id' => $b['company']->id]);
        $this->assertFalse(app(PermissionService::class)->userCan($user, 'orders.view'));
    }
}
