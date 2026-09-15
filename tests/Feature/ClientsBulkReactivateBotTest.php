<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: reactivar el bot masivamente desde el listado de
 * Clientes. Debe ignorar en silencio la lista negra y cualquier cliente que
 * no sea de la empresa activa, en vez de fallar todo el lote.
 */
class ClientsBulkReactivateBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function companyFixture(string $suffix): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Bulk', 'slug' => 'empresa-bulk-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Bulk', 'display_name' => 'Empresa Bulk',
            'phone_number' => '593998'.$suffix, 'phone_number_id' => 'BULK-PHONE-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $profile, $user];
    }

    public function test_it_reactivates_the_bot_for_every_selected_client_except_the_blacklisted_one(): void
    {
        [$company, $profile, $user] = $this->companyFixture('01');
        $normal = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999500001', 'name' => 'Cliente Normal', 'bot_enabled' => false]);
        $blacklisted = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999500002', 'name' => 'Cliente Lista Negra', 'bot_enabled' => false, 'bot_blacklisted' => true]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.clients.bulk-reactivate-bot'), ['client_ids' => [$normal->id, $blacklisted->id]]);

        $response->assertRedirect();
        $this->assertTrue($normal->fresh()->bot_enabled);
        $this->assertFalse($blacklisted->fresh()->bot_enabled, 'Un contacto en lista negra nunca debe reactivarse por la acción masiva.');
    }

    public function test_it_never_touches_a_contact_from_another_company(): void
    {
        [$companyA, , $user] = $this->companyFixture('02');
        [, $profileB] = $this->companyFixture('03');
        $foreignContact = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999500003', 'name' => 'Cliente Ajeno', 'bot_enabled' => false]);

        $this->actingAs($user)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('admin.clients.bulk-reactivate-bot'), ['client_ids' => [$foreignContact->id]])
            ->assertRedirect();

        $this->assertFalse($foreignContact->fresh()->bot_enabled, 'No debe poder reactivar el bot de un contacto de otra empresa.');
    }
}
