<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: al crear y enviar una campaña, la redirección
 * a "ver campaña" (admin/campanas/{id}) mostraba 404. Causa:
 * MarketingCampaignController::store() resolvía el negocio con
 * WhatsappBusinessProfile::first() -- el primer perfil de TODA la
 * plataforma, sin importar la empresa activa -- mientras que show() (y el
 * resto del controlador) sí filtra estrictamente por la empresa activa vía
 * campaignQuery(). La campaña quedaba creada con el business_profile_id de
 * OTRA empresa (la que tuviera el perfil más antiguo en toda la base), así
 * que el findOrFail() de show() nunca la encontraba.
 */
class CampaignSendRedirectsToOwnCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    public function test_sending_a_campaign_redirects_to_a_page_that_actually_finds_it(): void
    {
        // Empresa A se crea PRIMERO -- su perfil sería el que devuelve
        // WhatsappBusinessProfile::first(), para reproducir el bug exacto.
        $companyA = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa A', 'slug' => 'empresa-a', 'status' => 'active',
        ]);
        WhatsappBusinessProfile::create([
            'company_id' => $companyA->id, 'business_name' => 'Empresa A', 'display_name' => 'Empresa A',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-A', 'access_token' => 'token-a',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        // Empresa B es la que realmente crea y envía la campaña.
        $companyB = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa B', 'slug' => 'empresa-b', 'status' => 'active',
        ]);
        $profileB = WhatsappBusinessProfile::create([
            'company_id' => $companyB->id, 'business_name' => 'Empresa B', 'display_name' => 'Empresa B',
            'phone_number' => '593990000002', 'phone_number_id' => 'PHONE-B', 'access_token' => 'token-b',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $companyB->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $companyB->id])
            ->from(route('admin.marketing.create'))
            ->post(route('admin.marketing.store'), [
                'name' => 'Campaña de prueba',
                'message_type' => 'text',
                'message_content' => 'Hola {{nombre}}',
                'recipient_type' => 'all',
                'send_immediately' => '1',
            ]);

        $campaign = \App\Models\WhatsappCampaign::where('name', 'Campaña de prueba')->firstOrFail();
        $this->assertSame($profileB->id, $campaign->business_profile_id);

        $response->assertRedirect(route('admin.marketing.show', $campaign));

        $this->actingAs($user)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('admin.marketing.show', $campaign))
            ->assertOk();
    }
}
