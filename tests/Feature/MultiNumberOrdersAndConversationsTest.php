<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "y cada pedido tiene la etiqueta de que número
 * están pidiendo... y que pasa con el módulo de conversaciones, cuando
 * existe más de un número?" -- Conversaciones tenía el mismo problema que
 * Pedidos (filtraba por un solo business_profile_id "principal"), y los
 * pedidos/chats no dejaban ver por cuál número habían llegado.
 */
class MultiNumberOrdersAndConversationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, WhatsappBusinessProfile, User} */
    private function twoNumberCompany(string $suffix): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Dos Numeros Chat', 'slug' => 'empresa-dos-numeros-chat-'.$suffix, 'status' => 'active']);
        $profileA = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Sucursal Centro', 'display_name' => 'Sucursal Centro',
            'phone_number' => '593993'.$suffix.'1', 'phone_number_id' => 'CHAT-NUM-A-'.$suffix, 'access_token' => 'token-a',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $profileB = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Sucursal Norte', 'display_name' => 'Sucursal Norte',
            'phone_number' => '593993'.$suffix.'2', 'phone_number_id' => 'CHAT-NUM-B-'.$suffix, 'access_token' => 'token-b',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id, ['role_id' => $role->id]);

        return [$company, $profileA, $profileB, $user];
    }

    public function test_the_conversations_sidebar_lists_contacts_from_both_numbers(): void
    {
        [$company, $profileA, $profileB, $user] = $this->twoNumberCompany('01');
        $contactA = WhatsappContact::create(['business_profile_id' => $profileA->id, 'phone_number' => '593999600001', 'name' => 'Cliente A']);
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999600002', 'name' => 'Cliente B']);
        WhatsappMessage::create(['business_profile_id' => $profileA->id, 'contact_id' => $contactA->id, 'message_id' => 'wamid.a1', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);
        WhatsappMessage::create(['business_profile_id' => $profileB->id, 'contact_id' => $contactB->id, 'message_id' => 'wamid.b1', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chat', $contactA->id));

        $response->assertOk();
        $response->assertSee('Cliente A');
        $response->assertSee('Cliente B');
    }

    public function test_opening_a_conversation_from_the_second_number_does_not_404(): void
    {
        [$company, $profileA, $profileB, $user] = $this->twoNumberCompany('02');
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999600003', 'name' => 'Cliente Numero B']);
        WhatsappMessage::create(['business_profile_id' => $profileB->id, 'contact_id' => $contactB->id, 'message_id' => 'wamid.b2', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chat', $contactB->id));

        $response->assertOk();
        $response->assertSee('Cliente Numero B');
    }

    public function test_the_chat_shows_which_number_the_conversation_belongs_to_when_there_are_two(): void
    {
        [$company, $profileA, $profileB, $user] = $this->twoNumberCompany('03');
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999600004', 'name' => 'Cliente Norte']);
        WhatsappMessage::create(['business_profile_id' => $profileB->id, 'contact_id' => $contactB->id, 'message_id' => 'wamid.b3', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chat', $contactB->id));

        $response->assertOk();
        $response->assertSee('Sucursal Norte');
    }

    public function test_the_number_label_is_hidden_when_the_company_only_has_one_number(): void
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Un Numero Chat', 'slug' => 'empresa-un-numero-chat', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Unico', 'display_name' => 'Unico',
            'phone_number' => '593994000001', 'phone_number_id' => 'CHAT-UNICO', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id, ['role_id' => $role->id]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999600005', 'name' => 'Cliente Unico']);
        WhatsappMessage::create(['business_profile_id' => $profile->id, 'contact_id' => $contact->id, 'message_id' => 'wamid.u1', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received']);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chat', $contact->id));

        $response->assertOk();
        $response->assertDontSee('Recibido por:');
    }

    public function test_the_orders_list_labels_which_number_each_order_came_from(): void
    {
        [$company, $profileA, $profileB, $user] = $this->twoNumberCompany('04');
        $contactA = WhatsappContact::create(['business_profile_id' => $profileA->id, 'phone_number' => '593999600006', 'name' => 'Cliente Pedido A']);
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999600007', 'name' => 'Cliente Pedido B']);
        WhatsappCart::create(['contact_id' => $contactA->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 10]);
        WhatsappCart::create(['contact_id' => $contactB->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 12]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertSee('Sucursal Centro');
        $response->assertSee('Sucursal Norte');
    }

    public function test_the_order_number_label_is_hidden_when_the_company_only_has_one_number(): void
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Un Numero Pedidos', 'slug' => 'empresa-un-numero-pedidos', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Unico Pedidos', 'display_name' => 'Unico Pedidos',
            'phone_number' => '593994000002', 'phone_number_id' => 'ORD-UNICO', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id, ['role_id' => $role->id]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999600008', 'name' => 'Cliente Unico Pedido']);
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 15]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertDontSee('Unico Pedidos');
    }
}
