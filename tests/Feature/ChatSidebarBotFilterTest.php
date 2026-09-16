<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: en "Conversaciones", un check para filtrar solo a los
 * clientes que habla el bot (bot_enabled=true) -- si está desactivado,
 * muestra a todos (con y sin bot). Independiente del check, los contactos
 * en lista negra nunca deben aparecer en este listado.
 */
class ChatSidebarBotFilterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function makeCompanyAndUser(): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'empresa-test',
            'slug' => 'empresa-test-'.Str::random(6), 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'empresa-test', 'display_name' => 'empresa-test',
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $profile, $user];
    }

    private function contactWithMessage(WhatsappBusinessProfile $profile, string $phone, string $name, bool $botEnabled = true, bool $blacklisted = false): WhatsappContact
    {
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => $phone, 'name' => $name,
            'bot_enabled' => $botEnabled, 'bot_blacklisted' => $blacklisted,
        ]);
        WhatsappMessage::create([
            'contact_id' => $contact->id, 'business_profile_id' => $profile->id,
            'message_id' => 'wamid.'.Str::random(10), 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received',
        ]);

        return $contact;
    }

    public function test_without_the_filter_the_sidebar_shows_both_bot_enabled_and_paused_contacts(): void
    {
        [$company, $profile, $user] = $this->makeCompanyAndUser();
        $active = $this->contactWithMessage($profile, '593990004001', 'Cliente Activo', true);
        $paused = $this->contactWithMessage($profile, '593990004002', 'Cliente Pausado', false);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.chat.contacts.update'));

        $response->assertOk();
        $ids = collect($response->json('contacts'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertContains($paused->id, $ids);
    }

    public function test_the_only_bot_filter_hides_paused_contacts(): void
    {
        [$company, $profile, $user] = $this->makeCompanyAndUser();
        $active = $this->contactWithMessage($profile, '593990004003', 'Cliente Activo Dos', true);
        $paused = $this->contactWithMessage($profile, '593990004004', 'Cliente Pausado Dos', false);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.chat.contacts.update', ['only_bot' => 1]));

        $response->assertOk();
        $ids = collect($response->json('contacts'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($paused->id, $ids);
    }

    public function test_blacklisted_contacts_never_appear_regardless_of_the_filter(): void
    {
        [$company, $profile, $user] = $this->makeCompanyAndUser();
        $normal = $this->contactWithMessage($profile, '593990004005', 'Cliente Normal', true);
        $blacklisted = $this->contactWithMessage($profile, '593990004006', 'Cliente Lista Negra', false, true);

        $withoutFilter = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.chat.contacts.update'));
        $withFilter = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.chat.contacts.update', ['only_bot' => 1]));

        foreach ([$withoutFilter, $withFilter] as $response) {
            $response->assertOk();
            $ids = collect($response->json('contacts'))->pluck('id')->all();
            $this->assertContains($normal->id, $ids);
            $this->assertNotContains($blacklisted->id, $ids);
        }
    }
}
