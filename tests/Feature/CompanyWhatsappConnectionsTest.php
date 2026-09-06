<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use App\Services\WhatsappCredentialService;
use App\Services\WhatsappService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El panel "Empresas -> WhatsApp" ahora administra VARIOS
 * WhatsappBusinessProfile por empresa (manual, embedded signup, coexistencia).
 * Estas pruebas cubren lo que puede romperse con esa multiplicidad: que un
 * perfil de otra empresa nunca sea alcanzable por id, que "desconectar" sea
 * local y no destructivo, y que un perfil desconectado deje de ser elegible
 * en cualquier resolución real (webhook, envío, contexto de empresa).
 */
class CompanyWhatsappConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    private function adminUser(): User
    {
        $role = Role::where('slug', 'admin')->firstOrFail();

        return User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    }

    /** @return array{company: Company, profile: WhatsappBusinessProfile, user: User} */
    private function makeCompanyWithProfile(string $slug, array $profileOverrides = [], ?User $user = null): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        $profile = WhatsappBusinessProfile::create(array_merge([
            'company_id' => $company->id,
            'business_name' => $slug,
            'display_name' => $slug,
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => strtoupper($slug) . '-PHONE-' . Str::random(6),
            'access_token' => 'token-' . $slug,
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'connection_type' => 'manual',
        ], $profileOverrides));

        $user ??= $this->adminUser();
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'user');
    }

    public function test_company_a_cannot_view_details_of_a_profile_owned_by_company_b(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a');
        $b = $this->makeCompanyWithProfile('empresa-b');

        $response = $this->actingAs($a['user'])->getJson(
            route('admin.empresas.whatsapp.profile.details', [$a['company'], $b['profile']])
        );

        $response->assertNotFound();
    }

    public function test_company_a_cannot_disconnect_a_profile_owned_by_company_b(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a');
        $b = $this->makeCompanyWithProfile('empresa-b');

        $response = $this->actingAs($a['user'])->post(
            route('admin.empresas.whatsapp.profile.disconnect', [$a['company'], $b['profile']])
        );

        $response->assertNotFound();
        $this->assertSame(WhatsappBusinessProfile::STATUS_CONNECTED, $b['profile']->fresh()->status);
    }

    public function test_disconnect_does_not_physically_delete_the_record(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a', ['connection_type' => 'embedded_signup', 'connected_at' => now()]);

        $response = $this->actingAs($a['user'])->post(
            route('admin.empresas.whatsapp.profile.disconnect', [$a['company'], $a['profile']])
        );

        $response->assertRedirect();

        $fresh = $a['profile']->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(WhatsappBusinessProfile::STATUS_DISCONNECTED, $fresh->status);
        $this->assertNotNull($fresh->disconnected_at);

        // Trazabilidad: nada de esto se borra al desconectar.
        $this->assertSame($a['profile']->phone_number_id, $fresh->phone_number_id);
        $this->assertSame($a['profile']->whatsapp_business_id, $fresh->whatsapp_business_id);
        $this->assertSame('embedded_signup', $fresh->connection_type);
        $this->assertNotNull($fresh->connected_at);
    }

    public function test_a_disconnected_profile_is_never_treated_as_the_active_connection(): void
    {
        // Empresa "de control": conectada, sirve para probar que un intento
        // de reasignar a un perfil desconectado NO pisa el perfil ya
        // asignado (el riesgo real: una instancia de WhatsappService
        // reutilizada entre contactos de distintas empresas, como hacen
        // SendWhatsAppTemplate/PendingReplyRecoveryService).
        $control = $this->makeCompanyWithProfile('empresa-control');
        $a = $this->makeCompanyWithProfile('empresa-a', [
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);

        // CompanyContext: no debe resolver un perfil desconectado como "el" de la empresa.
        $context = CompanyContext::forCompany($a['company']);
        $this->assertNull($context->businessProfile);

        // WhatsappCredentialService: ni por empresa ni por phone_number_id.
        $credentials = app(WhatsappCredentialService::class);
        $this->assertNull($credentials->forCompany($a['company']));
        $this->assertNull($credentials->byPhoneNumberId($a['profile']->phone_number_id));

        // WhatsappService: un intento explícito de usar el perfil desconectado
        // ahora LANZA (no lo ignora en silencio) -- y además limpia el perfil
        // de la instancia, para no dejar "empresa-control" pegado si algún
        // llamador atrapa la excepción sin revisar qué pasó.
        $service = app(WhatsappService::class);
        $service->useBusinessProfile($control['profile']);
        $this->assertSame($control['profile']->id, $service->getBusinessProfile()->id);

        try {
            $service->useBusinessProfile($a['profile']);
            $this->fail('Se esperaba WhatsappBusinessProfileUnavailableException.');
        } catch (\App\Exceptions\WhatsappBusinessProfileUnavailableException $e) {
            // esperado
        }
        $this->assertNull($service->getBusinessProfile());

        // Webhook: un mensaje entrante al phone_number_id desconectado tampoco
        // debe resolverlo (misma garantía, otra vía de entrada). Instancia
        // nueva con el perfil de control ya asignado para aislar este chequeo.
        $service2 = app(WhatsappService::class);
        $service2->useBusinessProfile($control['profile']);
        $service2->setWebhookPhoneNumberId($a['profile']->phone_number_id);
        $this->assertSame($control['profile']->id, $service2->getBusinessProfile()->id);
    }

    public function test_test_connection_never_returns_the_access_token(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'PHONE-X',
                'display_phone_number' => '+593 99 000 0000',
                'verified_name' => 'Empresa A',
                'quality_rating' => 'GREEN',
                'code_verification_status' => 'VERIFIED',
            ], 200),
        ]);

        $a = $this->makeCompanyWithProfile('empresa-a');

        $response = $this->actingAs($a['user'])->postJson(
            route('admin.empresas.whatsapp.profile.test', [$a['company'], $a['profile']])
        );

        $response->assertOk();
        $response->assertJsonMissingPath('access_token');
        $this->assertStringNotContainsString('token-empresa-a', $response->getContent());
    }

    public function test_test_connection_only_performs_a_read_only_lookup_never_register_or_subscribed_apps(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'PHONE-X', 'display_phone_number' => '+593990000000'], 200),
        ]);

        $a = $this->makeCompanyWithProfile('empresa-a');

        $this->actingAs($a['user'])->postJson(
            route('admin.empresas.whatsapp.profile.test', [$a['company'], $a['profile']])
        )->assertOk();

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '/register')
            || str_contains((string) $request->url(), 'subscribed_apps')
            || $request->method() !== 'GET');
    }

    public function test_manual_connection_type_keeps_working_after_the_multi_profile_changes(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a', ['connection_type' => 'manual']);

        $response = $this->actingAs($a['user'])->put(route('admin.empresas.whatsapp.update', $a['company']), [
            'phone_number' => '593987654321',
            'phone_number_id' => $a['profile']->phone_number_id,
            'whatsapp_business_id' => 'WABA-MANUAL',
        ]);

        $response->assertRedirect();
        $this->assertSame('593987654321', $a['profile']->fresh()->phone_number);
    }

    public function test_a_connected_profile_cannot_be_deleted(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a');

        $response = $this->actingAs($a['user'])->delete(
            route('admin.empresas.whatsapp.profile.destroy', [$a['company'], $a['profile']])
        );

        $response->assertRedirect();
        $this->assertNotNull($a['profile']->fresh(), 'No debió borrarse: sigue conectado.');
    }

    public function test_a_disconnected_profile_with_contacts_cannot_be_deleted(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a', [
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);

        \App\Models\WhatsappContact::create([
            'business_profile_id' => $a['profile']->id,
            'phone_number' => '593987654321',
            'name' => 'Cliente real',
            'status' => 'active',
        ]);

        $response = $this->actingAs($a['user'])->delete(
            route('admin.empresas.whatsapp.profile.destroy', [$a['company'], $a['profile']])
        );

        $response->assertRedirect();
        $this->assertNotNull($a['profile']->fresh(), 'No debió borrarse: tiene contactos asociados.');
    }

    public function test_a_disconnected_profile_without_contacts_can_be_deleted_along_with_its_own_catalog(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a', [
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);

        $menu = \App\Models\WhatsappMenu::create([
            'business_profile_id' => $a['profile']->id,
            'title' => 'Menu', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        \App\Models\WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $a['profile']->id, 'title' => 'Cat', 'action_id' => 'cat_x',
        ]);

        $response = $this->actingAs($a['user'])->delete(
            route('admin.empresas.whatsapp.profile.destroy', [$a['company'], $a['profile']])
        );

        $response->assertRedirect(route('admin.empresas.whatsapp', $a['company']));
        $this->assertNull($a['profile']->fresh());
        $this->assertSame(0, \App\Models\WhatsappMenu::where('business_profile_id', $a['profile']->id)->count());
        $this->assertSame(0, \App\Models\WhatsappMenuItem::where('business_profile_id', $a['profile']->id)->count());
    }

    public function test_company_a_cannot_delete_a_profile_owned_by_company_b(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a');
        $b = $this->makeCompanyWithProfile('empresa-b', [
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);

        $response = $this->actingAs($a['user'])->delete(
            route('admin.empresas.whatsapp.profile.destroy', [$a['company'], $b['profile']])
        );

        $response->assertNotFound();
        $this->assertNotNull($b['profile']->fresh());
    }

    public function test_company_name_can_be_edited_without_changing_the_slug(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a');
        $originalSlug = $a['company']->slug;

        $response = $this->actingAs($a['user'])->put(route('admin.empresas.update', $a['company']), [
            'name' => 'Empresa A Corregida',
        ]);

        $response->assertRedirect(route('admin.empresas.whatsapp', $a['company']));
        $fresh = $a['company']->fresh();
        $this->assertSame('Empresa A Corregida', $fresh->name);
        $this->assertSame($originalSlug, $fresh->slug, 'El slug no debe cambiar al editar el nombre.');
    }

    public function test_company_a_cannot_rename_company_b(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a');
        $b = $this->makeCompanyWithProfile('empresa-b');

        $response = $this->actingAs($a['user'])->put(route('admin.empresas.update', $b['company']), [
            'name' => 'Nombre Robado',
        ]);

        $response->assertForbidden();
        $this->assertNotEquals('Nombre Robado', $b['company']->fresh()->name);
    }

    public function test_multiple_profiles_can_belong_to_the_same_company(): void
    {
        $a = $this->makeCompanyWithProfile('empresa-a', ['connection_type' => 'manual']);

        WhatsappBusinessProfile::create([
            'company_id' => $a['company']->id,
            'business_name' => 'empresa-a',
            'display_name' => 'Embedded',
            'phone_number' => '593900000002',
            'phone_number_id' => 'EMPRESA-A-EMBEDDED-PHONE',
            'access_token' => 'token-embedded',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'connection_type' => 'embedded_signup',
        ]);

        $this->assertSame(2, $a['company']->whatsappAccounts()->count());

        $response = $this->actingAs($a['user'])->get(route('admin.empresas.whatsapp', $a['company']));
        $response->assertOk();
        $response->assertSee('Embedded Signup');
        $response->assertSee('Manual');
    }
}
