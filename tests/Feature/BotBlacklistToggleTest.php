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
 * Pedido explícito: contactos que nunca deben reactivarse solos -- ni por el
 * job diario ni por accidente -- agregables/quitables tanto desde
 * Conversaciones como desde Clientes (ambas vistas pegan a este mismo
 * endpoint, ver AdminController::toggleBlacklist()).
 */
class BotBlacklistToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{WhatsappContact, User} */
    private function fixture(string $suffix = '01'): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Blacklist', 'slug' => 'empresa-blacklist-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Blacklist', 'display_name' => 'Empresa Blacklist',
            'phone_number' => '593997'.$suffix, 'phone_number_id' => 'BLACKLIST-PHONE-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999400'.$suffix, 'name' => 'Cliente', 'bot_enabled' => true]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$contact, $user];
    }

    public function test_blacklisting_a_contact_also_pauses_its_bot(): void
    {
        [$contact, $user] = $this->fixture('01');

        $response = $this->actingAs($user)->postJson(route('admin.contact.toggle-blacklist', $contact->id), ['blacklisted' => true]);

        $response->assertOk()->assertJson(['success' => true, 'bot_blacklisted' => true, 'bot_enabled' => false]);
        $this->assertTrue($contact->fresh()->bot_blacklisted);
        $this->assertFalse($contact->fresh()->bot_enabled);
    }

    public function test_removing_from_the_blacklist_does_not_auto_reactivate_the_bot(): void
    {
        [$contact, $user] = $this->fixture('02');
        $contact->update(['bot_blacklisted' => true, 'bot_enabled' => false]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.toggle-blacklist', $contact->id), ['blacklisted' => false]);

        $response->assertOk()->assertJson(['success' => true, 'bot_blacklisted' => false]);
        $this->assertFalse($contact->fresh()->bot_blacklisted);
        $this->assertFalse($contact->fresh()->bot_enabled, 'Quitar de la lista negra no debe reactivar el bot por su cuenta.');
    }

    public function test_the_daily_reactivation_skips_a_blacklisted_contact_end_to_end(): void
    {
        [$contact, $user] = $this->fixture('03');
        $this->actingAs($user)->postJson(route('admin.contact.toggle-blacklist', $contact->id), ['blacklisted' => true])->assertOk();

        $this->artisan('whatsapp:reactivate-bots-daily');

        $this->assertFalse($contact->fresh()->bot_enabled);
    }

    public function test_a_user_without_access_to_the_company_cannot_blacklist_its_contact(): void
    {
        [$contact] = $this->fixture('04');
        [, $outsider] = $this->fixture('05');

        $response = $this->actingAs($outsider)->postJson(route('admin.contact.toggle-blacklist', $contact->id), ['blacklisted' => true]);

        $response->assertStatus(404);
        $this->assertFalse($contact->fresh()->bot_blacklisted);
    }
}
