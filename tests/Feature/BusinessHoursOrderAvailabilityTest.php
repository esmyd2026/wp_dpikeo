<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchHour;
use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\WhatsappBusinessProfile;
use App\Services\BusinessHoursService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessHoursOrderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fixture(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Horarios',
            'slug' => 'empresa-horarios', 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593999000001', 'phone_number_id' => 'HOURS-1',
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        CompanyStorefrontSetting::create(['company_id' => $company->id, 'storefront_enabled' => true]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URD-HOURS',
            'is_active' => true, 'orders_enabled' => true, 'is_default' => true,
        ]);
        BusinessBranchHour::create([
            'business_branch_id' => $branch->id, 'day_of_week' => 1,
            'opens_at' => '10:00', 'closes_at' => '20:00', 'is_closed' => false,
        ]);

        return [$company, $profile, $branch];
    }

    public function test_configured_branch_hours_control_order_availability(): void
    {
        [, $profile, $branch] = $this->fixture();
        $hours = app(BusinessHoursService::class);

        Carbon::setTestNow(Carbon::parse('2026-09-14 09:00', config('app.timezone')));
        $this->assertFalse($hours->branchIsOpen($branch));
        $this->assertCount(0, $hours->openOrderBranches($profile->id));
        $this->assertStringContainsString('hoy a las 10:00', $hours->closedMessage($profile->id));

        Carbon::setTestNow(Carbon::parse('2026-09-14 10:01', config('app.timezone')));
        $this->assertTrue($hours->branchIsOpen($branch));
        $this->assertCount(1, $hours->openOrderBranches($profile->id));
        $this->assertNull($hours->closedMessage($profile->id));
    }

    public function test_storefront_explains_when_no_branch_is_open(): void
    {
        [$company, , $branch] = $this->fixture();
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:00', config('app.timezone')));

        $this->get(route('storefront.show', $company))
            ->assertOk()
            ->assertSee('fuera de horario')
            ->assertSee('hoy a las 10:00')
            ->assertDontSee('data-branch-option="'.$branch->id.'"', false);
    }

    public function test_storefront_rejects_an_order_if_the_selected_branch_is_closed(): void
    {
        [$company, , $branch] = $this->fixture();
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:00', config('app.timezone')));

        $this->postJson(route('storefront.submit', $company), [
            'name' => 'Cliente horario',
            'phone' => '0988492339',
            'service_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'transferencia',
            'requires_invoice' => false,
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'En este momento estamos fuera de horario. Puedes revisar el menú y volver a pedir hoy a las 10:00.');

        $this->assertDatabaseCount('whatsapp_carts', 0);
    }

    public function test_a_branch_without_configured_hours_remains_available(): void
    {
        [, $profile] = $this->fixture();
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'MATRIZ-HOURS',
            'is_active' => true, 'orders_enabled' => true,
        ]);

        $this->assertTrue(app(BusinessHoursService::class)->branchIsOpen($branch));
    }

    public function test_a_missing_day_is_closed_once_a_schedule_has_been_configured(): void
    {
        [, , $branch] = $this->fixture();
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00', config('app.timezone')));

        $this->assertFalse(app(BusinessHoursService::class)->branchIsOpen($branch));
    }
}
