<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: el panel debe permitir habilitar el "modo
 * ecommerce" (el bot manda el link del micrositio en vez de armar el pedido
 * nativo), avisando si el micrositio de la empresa todavía no está activado.
 */
class ChatbotConfigEcommerceModeAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function fixture(string $suffix, bool $storefrontEnabled): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Ecommerce Admin', 'slug' => 'empresa-ecommerce-admin-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa', 'display_name' => 'Empresa',
            'phone_number' => '593994'.$suffix, 'phone_number_id' => 'PHONE-ECOMADMIN-'.$suffix, 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        CompanyStorefrontSetting::create(['company_id' => $company->id, 'storefront_enabled' => $storefrontEnabled]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $admin];
    }

    public function test_the_checkbox_renders_and_warns_when_the_storefront_is_off(): void
    {
        [$company, , $admin] = $this->fixture('1', storefrontEnabled: false);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chatbot.config'));

        $response->assertOk();
        $response->assertSee('id="ecommerce_mode_enabled"', false);
        $response->assertSee('Tu tienda en línea todavía no está activada');
    }

    public function test_the_warning_is_gone_once_the_storefront_is_enabled(): void
    {
        [$company, , $admin] = $this->fixture('2', storefrontEnabled: true);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chatbot.config'));

        $response->assertOk();
        $response->assertDontSee('Tu tienda en línea todavía no está activada');
    }

    public function test_saving_with_the_checkbox_checked_persists_it_and_omitting_it_persists_false(): void
    {
        [$company, $profile, $admin] = $this->fixture('3', storefrontEnabled: true);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.chatbot.config.update'), ['ecommerce_mode_enabled' => '1'])
            ->assertSessionDoesntHaveErrors();

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile->id)->firstOrFail();
        $this->assertTrue($config->ecommerce_mode_enabled);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.chatbot.config.update'), [])
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse($config->fresh()->ecommerce_mode_enabled);
    }
}
