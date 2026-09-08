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
 * Bug real reportado en vivo: el panel de "Conversaciones" se sentía lento.
 * Dos causas encontradas: (1) la lista de contactos del sidebar filtraba
 * "quién tiene mensajes" con un whereIn(SELECT DISTINCT contact_id FROM
 * whatsapp_messages) que escaneaba la tabla de TODA la plataforma sin
 * filtrar por empresa; (2) el polling de "mensajes nuevos" no tenía límite,
 * así que una conversación con backlog grande (o un last_message_id
 * atascado en el navegador) devolvía cientos de mensajes -- más de 500 KB
 * -- en cada poll de 2 segundos.
 */
class ChatPollingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function makeCompanyAndUser(): array
    {
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

    public function test_sidebar_contact_list_never_leaks_a_contact_from_another_company(): void
    {
        [$companyA, $profileA, $userA] = $this->makeCompanyAndUser();
        [, $profileB] = $this->makeCompanyAndUser();

        $contactA = WhatsappContact::create(['business_profile_id' => $profileA->id, 'phone_number' => '593990000001', 'name' => 'Cliente A']);
        WhatsappMessage::create(['contact_id' => $contactA->id, 'business_profile_id' => $profileA->id, 'message_id' => 'wamid.a.1', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);

        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593990000002', 'name' => 'Cliente B']);
        WhatsappMessage::create(['contact_id' => $contactB->id, 'business_profile_id' => $profileB->id, 'message_id' => 'wamid.b.1', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);

        $response = $this->actingAs($userA)
            ->withSession(['active_company_id' => $companyA->id])
            ->getJson(route('admin.chat.contacts.update'));

        $response->assertOk();
        $ids = collect($response->json('contacts'))->pluck('id')->all();
        $this->assertContains($contactA->id, $ids);
        $this->assertNotContains($contactB->id, $ids);
    }

    public function test_new_messages_polling_is_capped_and_never_returns_the_whole_backlog_at_once(): void
    {
        [$company, $profile, $user] = $this->makeCompanyAndUser();
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000003', 'name' => 'Cliente']);

        $baseline = WhatsappMessage::create([
            'contact_id' => $contact->id, 'business_profile_id' => $profile->id,
            'message_id' => 'wamid.base', 'sender_type' => 'client', 'type' => 'text', 'content' => 'mensaje base', 'status' => 'received',
        ]);
        for ($i = 0; $i < 150; $i++) {
            WhatsappMessage::create([
                'contact_id' => $contact->id, 'business_profile_id' => $profile->id,
                'message_id' => "wamid.{$i}", 'sender_type' => 'client', 'type' => 'text', 'content' => "mensaje {$i}", 'status' => 'received',
            ]);
        }

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.chat.new-messages', ['contact' => $contact->id, 'last_message_id' => $baseline->id]));

        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('count'));
    }
}
