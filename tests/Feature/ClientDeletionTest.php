<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactNote;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: para limpiar fichas de prueba (duplicadas por el
 * bug de formatos de teléfono, ver ClientCompanyIsolationTest) el usuario
 * pidió un botón -- con permiso aparte y confirmación explícita mostrando
 * qué se va a borrar -- para eliminar un cliente junto con sus pedidos y su
 * chat, no solo fusionarlo con otro.
 */
class ClientDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile} */
    private function company(string $suffix): array
    {
        app(PermissionService::class)->syncDefinitions();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa '.$suffix, 'slug' => 'empresa-borrado-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa '.$suffix, 'display_name' => 'Empresa '.$suffix,
            'phone_number' => '593997'.$suffix, 'phone_number_id' => 'PHONE-BORRADO-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        return [$company, $profile];
    }

    private function adminWithPermissions(Company $company, array $extraPermissions = []): User
    {
        $role = Role::create(['name' => 'Rol '.uniqid(), 'slug' => 'rol-'.uniqid()]);
        $keys = array_merge(['clients.view', 'clients.detail'], $extraPermissions);
        $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return $user;
    }

    private function fullContact(WhatsappBusinessProfile $profile, string $phone): WhatsappContact
    {
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => $phone, 'name' => 'Cliente Prueba']);
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'sender_type' => 'client', 'receiver_type' => 'system', 'content' => 'hola', 'type' => 'text',
            'message_id' => 'wamid.'.Str::random(10), 'status' => 'received',
        ]);
        WhatsappConversation::create(['business_profile_id' => $profile->id, 'contact_id' => $contact->id, 'status' => 'active']);
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'pending', 'total' => 10]);
        $noteAuthor = User::factory()->create();
        WhatsappContactNote::create(['contact_id' => $contact->id, 'user_id' => $noteAuthor->id, 'body' => 'nota de prueba']);

        return $contact;
    }

    public function test_deleting_a_client_removes_its_messages_orders_conversations_and_notes(): void
    {
        [$company, $profile] = $this->company('100001');
        $admin = $this->adminWithPermissions($company, ['clients.delete']);
        $contact = $this->fullContact($profile, '593990002001');
        $contactId = $contact->id;

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('admin.clients.destroy', $contact));

        $response->assertRedirect(route('admin.clients.index'));
        $this->assertDatabaseMissing('whatsapp_contacts', ['id' => $contactId]);
        $this->assertDatabaseMissing('whatsapp_messages', ['contact_id' => $contactId]);
        $this->assertDatabaseMissing('whatsapp_conversations', ['contact_id' => $contactId]);
        $this->assertDatabaseMissing('whatsapp_carts', ['contact_id' => $contactId]);
        $this->assertDatabaseMissing('whatsapp_contact_notes', ['contact_id' => $contactId]);
    }

    public function test_deleting_a_client_without_the_permission_is_blocked(): void
    {
        [$company, $profile] = $this->company('100002');
        $admin = $this->adminWithPermissions($company); // sin clients.delete
        $contact = $this->fullContact($profile, '593990002002');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('admin.clients.destroy', $contact));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseHas('whatsapp_contacts', ['id' => $contact->id]);
    }

    public function test_deleting_a_client_from_another_company_is_blocked(): void
    {
        [$companyA] = $this->company('100003');
        [, $profileB] = $this->company('100004');
        $adminA = $this->adminWithPermissions($companyA, ['clients.delete']);
        $foreignContact = $this->fullContact($profileB, '593990002003');

        $response = $this->actingAs($adminA)
            ->withSession(['active_company_id' => $companyA->id])
            ->delete(route('admin.clients.destroy', $foreignContact));

        $response->assertNotFound();
        $this->assertDatabaseHas('whatsapp_contacts', ['id' => $foreignContact->id]);
    }
}
