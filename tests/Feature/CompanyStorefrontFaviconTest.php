<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyStorefrontFaviconTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();

        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa con favicon',
            'slug' => 'empresa-con-favicon',
            'status' => 'active',
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return compact('company', 'admin');
    }

    public function test_an_admin_can_upload_a_favicon_for_the_company_storefront(): void
    {
        Storage::fake('public');
        ['company' => $company, 'admin' => $admin] = $this->fixture();

        $response = $this->actingAs($admin)->put(route('admin.empresas.storefront.update', $company), [
            'primary_color' => '#E85D04',
            'secondary_color' => '#7C2D12',
            'accent_color' => '#FFD166',
            'storefront_enabled' => '1',
            'favicon' => UploadedFile::fake()->image('favicon.png', 512, 512),
        ]);

        $response->assertRedirect();
        $settings = $company->storefrontSetting()->firstOrFail();
        $this->assertStringStartsWith('storage/company-branding/'.$company->uuid.'/', $settings->favicon_path);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $settings->favicon_path));

        $this->actingAs($admin)
            ->get(route('admin.empresas.storefront.edit', $company))
            ->assertOk()
            ->assertSee('Favicon de la tienda')
            ->assertSee($settings->faviconUrl(), false);
    }

    public function test_the_company_favicon_is_rendered_and_can_be_removed(): void
    {
        Storage::fake('public');
        ['company' => $company, 'admin' => $admin] = $this->fixture();
        Storage::disk('public')->put('company-branding/favicon.png', 'image');
        $settings = $company->storefrontSetting()->create([
            'favicon_path' => 'storage/company-branding/favicon.png',
            'storefront_enabled' => true,
        ])->refresh();

        $this->get(route('storefront.show', $company))
            ->assertOk()
            ->assertSee('rel="icon" href="'.asset('storage/company-branding/favicon.png').'"', false);

        $this->actingAs($admin)->put(route('admin.empresas.storefront.update', $company), [
            'primary_color' => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            'accent_color' => $settings->accent_color,
            'storefront_enabled' => '1',
            'remove_favicon' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($settings->fresh()->favicon_path);
        Storage::disk('public')->assertMissing('company-branding/favicon.png');
    }
}
