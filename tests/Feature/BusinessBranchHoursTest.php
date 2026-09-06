<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
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
 * Horario de atención por sucursal (7 filas, una por día). Un día marcado
 * "cerrado" siempre debe quedar sin horas guardadas, aunque el form mande
 * algo en esos campos -- y la hora de cierre nunca puede ser <= la de apertura.
 */
class BusinessBranchHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{company: Company, profile: WhatsappBusinessProfile, user: User} */
    private function makeCompanySetup(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'empresa-test',
            'slug' => 'empresa-test-' . Str::random(6), 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'empresa-test', 'display_name' => 'empresa-test',
            'phone_number' => '593' . random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-' . Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'user');
    }

    public function test_creating_a_branch_saves_the_submitted_weekly_hours(): void
    {
        $setup = $this->makeCompanySetup();

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->post(route('admin.branches.store'), [
                'name' => 'Sucursal Centro',
                'code' => '',
                'is_active' => '1',
                'hours' => [
                    1 => ['opens_at' => '09:00', 'closes_at' => '18:00'],
                    0 => ['is_closed' => '1'],
                ],
            ]);

        $response->assertRedirect();
        $branch = BusinessBranch::where('name', 'Sucursal Centro')->firstOrFail();

        $monday = $branch->hours()->where('day_of_week', 1)->firstOrFail();
        $this->assertFalse($monday->is_closed);
        $this->assertSame('09:00', $monday->opens_at);
        $this->assertSame('18:00', $monday->closes_at);

        $sunday = $branch->hours()->where('day_of_week', 0)->firstOrFail();
        $this->assertTrue($sunday->is_closed);
        $this->assertNull($sunday->opens_at);
        $this->assertNull($sunday->closes_at);

        // Los otros 5 días quedan creados sin horario definido todavía.
        $this->assertSame(7, $branch->hours()->count());
    }

    public function test_marking_a_day_closed_clears_any_time_values_sent_alongside_it(): void
    {
        $setup = $this->makeCompanySetup();
        $branch = BusinessBranch::create(['business_profile_id' => $setup['profile']->id, 'name' => 'Sucursal', 'code' => 'S1', 'is_default' => true]);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 2, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']);

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->put(route('admin.branches.update', $branch), [
                'name' => 'Sucursal', 'code' => 'S1', 'is_default' => '1',
                'hours' => [2 => ['is_closed' => '1', 'opens_at' => '09:00', 'closes_at' => '18:00']],
            ]);

        $response->assertRedirect();
        $tuesday = $branch->hours()->where('day_of_week', 2)->firstOrFail();
        $this->assertTrue($tuesday->is_closed);
        $this->assertNull($tuesday->opens_at);
        $this->assertNull($tuesday->closes_at);
    }

    public function test_closing_time_before_or_equal_to_opening_time_is_rejected(): void
    {
        $setup = $this->makeCompanySetup();

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->post(route('admin.branches.store'), [
                'name' => 'Sucursal Inválida',
                'code' => '',
                'hours' => [3 => ['opens_at' => '18:00', 'closes_at' => '09:00']],
            ]);

        $response->assertSessionHasErrors('hours.3.closes_at');
        $this->assertNull(BusinessBranch::where('name', 'Sucursal Inválida')->first(), 'No debió crearse: transacción revertida por la validación de horario.');
    }

    public function test_updating_hours_does_not_create_duplicate_rows_per_day(): void
    {
        $setup = $this->makeCompanySetup();
        $branch = BusinessBranch::create(['business_profile_id' => $setup['profile']->id, 'name' => 'Sucursal', 'code' => 'S1', 'is_default' => true]);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($setup['user'])
                ->withSession(['active_company_id' => $setup['company']->id])
                ->put(route('admin.branches.update', $branch), [
                    'name' => 'Sucursal', 'code' => 'S1', 'is_default' => '1',
                    'hours' => [1 => ['opens_at' => '08:00', 'closes_at' => '17:00']],
                ]);
        }

        $this->assertSame(1, BusinessBranchHour::where('business_branch_id', $branch->id)->where('day_of_week', 1)->count());
        $this->assertSame(7, BusinessBranchHour::where('business_branch_id', $branch->id)->count());
    }
}
