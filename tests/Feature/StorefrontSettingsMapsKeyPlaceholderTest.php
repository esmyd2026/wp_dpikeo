<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: el campo "Google Maps API key" es type=password
 * y por seguridad SIEMPRE se ve vacío al recargar, tenga o no una clave
 * guardada -- eso hacía pensar que la clave "desaparecía" al guardar. Ahora
 * el placeholder avisa si ya hay una configurada (mismo patrón que el campo
 * de token de WhatsApp), sin exponer la clave real en el HTML.
 */
class StorefrontSettingsMapsKeyPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $slug): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return compact('company', 'admin');
    }

    public function test_the_placeholder_says_not_configured_when_no_key_is_saved(): void
    {
        ['company' => $company, 'admin' => $admin] = $this->fixture('empresa-maps-sin-clave');

        $response = $this->actingAs($admin)->get(route('admin.empresas.storefront.edit', $company));

        $response->assertOk();
        $response->assertSee('Aún no configurada');
        $response->assertDontSee('ya configurada');
    }

    public function test_the_placeholder_confirms_a_saved_key_without_ever_printing_it(): void
    {
        ['company' => $company, 'admin' => $admin] = $this->fixture('empresa-maps-con-clave');
        CompanyStorefrontSetting::create(['company_id' => $company->id, 'google_maps_api_key' => 'AIzaSyRealSecretKeyValue']);

        $response = $this->actingAs($admin)->get(route('admin.empresas.storefront.edit', $company));

        $response->assertOk();
        $response->assertSee('ya configurada');
        $response->assertDontSee('AIzaSyRealSecretKeyValue');
    }
}
