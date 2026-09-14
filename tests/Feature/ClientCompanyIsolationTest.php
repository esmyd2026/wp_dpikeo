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
 * Pedido explícito en vivo: en "Clientes" del panel aparecían fichas
 * repetidas para el mismo número y no estaba claro si el listado respetaba
 * la empresa activa. Se encontraron dos bugs reales: ClientInsightsService
 * nunca filtraba por empresa (mostraba contactos de CUALQUIER empresa del
 * sistema), y las rutas de ficha de cliente (show/update/reset-password/
 * notas) no verificaban que el contacto perteneciera a la empresa activa
 * (un admin podía ver/editar clientes de otra empresa cambiando el id en la
 * URL).
 */
class ClientCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function companyWithAdmin(string $suffix): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa '.$suffix, 'slug' => 'empresa-clientes-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa '.$suffix, 'display_name' => 'Empresa '.$suffix,
            'phone_number' => '593995'.$suffix, 'phone_number_id' => 'PHONE-CLIENTES-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $admin];
    }

    private function contactWithActivity(WhatsappBusinessProfile $profile, string $phone, string $name): WhatsappContact
    {
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => $phone, 'name' => $name,
        ]);
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'sender_type' => 'client', 'receiver_type' => 'system', 'content' => 'hola', 'type' => 'text',
            'message_id' => 'wamid.'.Str::random(10), 'status' => 'received',
        ]);

        return $contact;
    }

    public function test_the_client_list_never_shows_contacts_from_another_company(): void
    {
        [$companyA, $profileA, $adminA] = $this->companyWithAdmin('100001');
        [, $profileB] = $this->companyWithAdmin('100002');
        $this->contactWithActivity($profileA, '593990001001', 'Cliente De A');
        $this->contactWithActivity($profileB, '593990001002', 'Cliente De B');

        $response = $this->actingAs($adminA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('Cliente De A');
        $response->assertDontSee('Cliente De B');
    }

    public function test_viewing_a_clients_detail_page_from_another_company_returns_404(): void
    {
        [$companyA, , $adminA] = $this->companyWithAdmin('100003');
        [, $profileB] = $this->companyWithAdmin('100004');
        $foreignClient = $this->contactWithActivity($profileB, '593990001003', 'Cliente Ajeno');

        $response = $this->actingAs($adminA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('admin.clients.show', $foreignClient));

        $response->assertNotFound();
    }

    public function test_updating_a_foreign_clients_data_is_blocked(): void
    {
        [$companyA, , $adminA] = $this->companyWithAdmin('100005');
        [, $profileB] = $this->companyWithAdmin('100006');
        $foreignClient = $this->contactWithActivity($profileB, '593990001004', 'Cliente Ajeno 2');

        $response = $this->actingAs($adminA)
            ->withSession(['active_company_id' => $companyA->id])
            ->put(route('admin.clients.update', $foreignClient), ['name' => 'Hackeado']);

        $response->assertNotFound();
        $this->assertSame('Cliente Ajeno 2', $foreignClient->fresh()->name);
    }

    public function test_resetting_a_foreign_clients_password_is_blocked(): void
    {
        [$companyA, , $adminA] = $this->companyWithAdmin('100007');
        [, $profileB] = $this->companyWithAdmin('100008');
        $foreignClient = $this->contactWithActivity($profileB, '593990001005', 'Cliente Ajeno 3');

        $response = $this->actingAs($adminA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('admin.clients.reset-password', $foreignClient));

        $response->assertNotFound();
        $this->assertFalse($foreignClient->fresh()->hasAccountPassword());
    }

    public function test_adding_a_note_to_a_foreign_client_is_blocked(): void
    {
        [$companyA, , $adminA] = $this->companyWithAdmin('100009');
        [, $profileB] = $this->companyWithAdmin('100010');
        $foreignClient = $this->contactWithActivity($profileB, '593990001006', 'Cliente Ajeno 4');

        $response = $this->actingAs($adminA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('admin.clients.notes.store', $foreignClient), ['body' => 'nota intrusa']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('whatsapp_contact_notes', ['body' => 'nota intrusa']);
    }

    public function test_a_company_with_two_whatsapp_numbers_sees_clients_from_both(): void
    {
        [$company, $profileA, $admin] = $this->companyWithAdmin('100011');
        $profileB = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa 100011', 'display_name' => 'Empresa 100011 (2)',
            'phone_number' => '593996100011', 'phone_number_id' => 'PHONE-CLIENTES-100011-B',
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $this->contactWithActivity($profileA, '593990001007', 'Cliente Numero Uno');
        $this->contactWithActivity($profileB, '593990001008', 'Cliente Numero Dos');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('Cliente Numero Uno');
        $response->assertSee('Cliente Numero Dos');
    }
}
