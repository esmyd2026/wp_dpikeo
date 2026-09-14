<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "ayúdame que punto de venta sea igual al
 * micrositio porque está en su versión antigua" -- el POS debe tener la
 * misma portada de categorías (imagen/ícono grande, sin tarjeta) que el
 * micrositio antes de entrar al catálogo, sin cambiar la mecánica propia
 * del POS (nombre del cliente, para llevar/servir).
 */
class PosKioskLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pos_screen_shows_a_storefront_style_category_landing_page(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa POS', 'slug' => 'empresa-pos-landing', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa POS', 'display_name' => 'Empresa POS',
            'phone_number' => '593990000002', 'phone_number_id' => 'PHONE-POSLANDING', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id,
            'title' => 'Boxes', 'action_id' => 'boxes', 'is_active' => true, 'order' => 1,
        ]);
        WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id,
            'category' => 'Boxes', 'sku' => 'BOX-1', 'name' => 'Box Tender',
            'price' => 4.5, 'currency' => 'USD', 'is_active' => true, 'stock' => 5,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('pos.create'));

        $response->assertOk();
        // Portada de categorías estilo micrositio, antes del catálogo.
        $response->assertSee('id="posGateway"', false);
        $response->assertSee('Panel administrativo');
        $response->assertSee('href="'.route('admin.dashboard').'"', false);
        $response->assertSee('id="posCategoryPreview"', false);
        $response->assertSee('data-preview-category=""', false);
        $response->assertSee('data-preview-category="'.$category->id.'"', false);
        $response->assertSee('Boxes');
        // El catálogo (con la mecánica del POS intacta) sigue existiendo, solo oculto hasta elegir categoría.
        $response->assertSee('id="posApp"', false);
        $response->assertSee('id="storefrontCategoryHeading"', false);
        $response->assertSee('id="bulkAddonSheet"', false);
        $response->assertSee('const usesEnhancedCustomizer = true;', false);
        $response->assertSee('¿Cuál es tu nombre?');
        $response->assertSee('id="kioskServiceLlevar"', false);
        $response->assertSee('class="kiosk-service-options"', false);
        $response->assertSee('id="kioskPaymentMethod"', false);
        $response->assertDontSee('id="bulkCartFab"', false);
        // El POS usa el mismo detalle y selector de adicionales del micrositio.
        $response->assertSee('id="storefrontDetailQty"', false);
    }
}
