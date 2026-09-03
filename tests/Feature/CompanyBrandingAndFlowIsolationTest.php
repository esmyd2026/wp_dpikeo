<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El branding (dashboard, marca) y el flujo de conversación de cada empresa
 * deben ser exclusivamente suyos. Una empresa sin flujo/configuración propia
 * nunca debe mostrar ni editar el contenido de otra -- ni "heredar en
 * silencio" el de la primera empresa de la base (el bug real que motivó
 * esta ronda de correcciones).
 */
class CompanyBrandingAndFlowIsolationTest extends TestCase
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

    /** @return array{company: Company, profile: WhatsappBusinessProfile, user: User, flow: MarketingFlow} */
    private function makeCompanyWithFlow(string $name, string $flowName, string $stepMessage, ?string $dashboardTitle = null): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
        ]);

        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => $name,
            'display_name' => $name,
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => strtoupper(Str::slug($name)) . '-PHONE',
            'access_token' => 'token-' . Str::slug($name),
            'status' => 'connected',
        ]);

        $metadata = ['bot_name' => "Bot {$name}"];
        if ($dashboardTitle) {
            $metadata['dashboard_title'] = $dashboardTitle;
        }
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => $metadata,
        ]);

        $flow = MarketingFlow::create([
            'business_profile_id' => $profile->id,
            'name' => $flowName,
            'is_default' => true,
            'is_active' => true,
        ]);

        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => 'welcome',
            'name' => 'Bienvenida',
            'message_template' => $stepMessage,
            'sort_order' => 0,
            'is_enabled' => true,
            'config' => [],
        ]);

        $user = $this->adminUser();
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'user', 'flow');
    }

    private function actingAsCompany(User $user, Company $company): static
    {
        $this->actingAs($user);
        $this->session(['active_company_id' => $company->id]);

        return $this;
    }

    public function test_dpikeo_flow_is_different_from_zapatos_demo_flow(): void
    {
        $dpikeo = $this->makeCompanyWithFlow('DPIKEOS', 'Flujo DPIKEOS', 'Bienvenido a DPIKEOS, pide tu combo favorito');
        $demo = $this->makeCompanyWithFlow('Zapatos Demo', 'Flujo Zapatos Demo', 'Bienvenido a Zapatos Demo, mira nuestros modelos');

        $this->actingAsCompany($dpikeo['user'], $dpikeo['company']);
        $responseDpikeo = $this->get(route('admin.marketing-flow.edit'));
        $responseDpikeo->assertOk();
        $responseDpikeo->assertSee('Flujo DPIKEOS', false);
        $responseDpikeo->assertDontSee('Flujo Zapatos Demo');

        $this->actingAsCompany($demo['user'], $demo['company']);
        $responseDemo = $this->get(route('admin.marketing-flow.edit'));
        $responseDemo->assertOk();
        $responseDemo->assertSee('Flujo Zapatos Demo', false);
        $responseDemo->assertDontSee('Flujo DPIKEOS');

        $this->assertNotEquals($dpikeo['flow']->id, $demo['flow']->id);
    }

    public function test_editing_zapatos_demo_flow_does_not_modify_dpikeo(): void
    {
        $dpikeo = $this->makeCompanyWithFlow('DPIKEOS', 'Flujo DPIKEOS', 'Mensaje original DPIKEOS');
        $demo = $this->makeCompanyWithFlow('Zapatos Demo', 'Flujo Zapatos Demo', 'Mensaje original Zapatos Demo');

        $this->actingAsCompany($demo['user'], $demo['company']);

        $response = $this->put(route('admin.marketing-flow.update'), [
            'flow_name' => 'Flujo Zapatos Demo Editado',
            'steps' => [
                [
                    'step_key' => 'welcome',
                    'name' => 'Bienvenida',
                    'message_template' => 'Mensaje EDITADO de Zapatos Demo',
                    'sort_order' => 0,
                    'is_enabled' => true,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertSame('Flujo Zapatos Demo Editado', $demo['flow']->fresh()->name);
        $this->assertSame(
            'Mensaje EDITADO de Zapatos Demo',
            $demo['flow']->fresh()->steps()->where('step_key', 'welcome')->first()->message_template
        );

        // DPIKEOS no debe haberse tocado en absoluto.
        $this->assertSame('Flujo DPIKEOS', $dpikeo['flow']->fresh()->name);
        $this->assertSame(
            'Mensaje original DPIKEOS',
            $dpikeo['flow']->fresh()->steps()->where('step_key', 'welcome')->first()->message_template
        );
    }

    public function test_company_without_flow_never_inherits_another_companys_flow(): void
    {
        $dpikeo = $this->makeCompanyWithFlow('DPIKEOS', 'Flujo DPIKEOS', 'Mensaje DPIKEOS');

        // Empresa nueva, sin ningún MarketingFlow propio.
        $newCo = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Nueva', 'slug' => 'empresa-nueva', 'status' => 'active']);
        $newProfile = WhatsappBusinessProfile::create([
            'company_id' => $newCo->id,
            'business_name' => 'Empresa Nueva',
            'display_name' => 'Empresa Nueva',
            'phone_number' => '593900000099',
            'phone_number_id' => 'EMPRESA-NUEVA-PHONE',
            'access_token' => 'token-empresa-nueva',
            'status' => 'connected',
        ]);
        $user = $this->adminUser();
        $newCo->users()->attach($user->id);

        $this->actingAsCompany($user, $newCo);

        $graphData = $this->getJson(route('admin.marketing-flow.graph.data'));
        $graphData->assertOk();
        $graphData->assertJson(['flow' => null, 'nodes' => [], 'edges' => []]);

        $editPage = $this->get(route('admin.marketing-flow.graph.edit'));
        $editPage->assertOk();
        $editPage->assertSee('SIN FLUJO CONFIGURADO');
        $editPage->assertDontSee('Flujo DPIKEOS');
        $editPage->assertDontSee('Mensaje DPIKEOS');

        // El flujo clásico también arranca vacío, con nombre genérico -- no
        // con el de DPIKEOS.
        $classicEdit = $this->get(route('admin.marketing-flow.edit'));
        $classicEdit->assertOk();
        $classicEdit->assertDontSee('Flujo DPIKEOS');
    }

    public function test_dashboard_for_zapatos_demo_never_contains_dpikeos_branding(): void
    {
        $dpikeo = $this->makeCompanyWithFlow('DPIKEOS', 'Flujo DPIKEOS', 'x', 'DPIKEOS · Centro de operación');
        $demo = $this->makeCompanyWithFlow('Zapatos Demo', 'Flujo Zapatos Demo', 'x');

        $this->actingAsCompany($dpikeo['user'], $dpikeo['company']);
        $dpikeoDashboard = $this->get(route('admin.dashboard'));
        $dpikeoDashboard->assertOk();
        $dpikeoDashboard->assertSee('DPIKEOS · Centro de operación');

        $this->actingAsCompany($demo['user'], $demo['company']);
        $demoDashboard = $this->get(route('admin.dashboard'));
        $demoDashboard->assertOk();
        $demoDashboard->assertDontSee('DPIKEOS');
        $demoDashboard->assertDontSee('Dpikeolovers');
        $demoDashboard->assertSee('Zapatos Demo');
    }
}
