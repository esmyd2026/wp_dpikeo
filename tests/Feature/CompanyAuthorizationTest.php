<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\OrderConfirmationService;
use App\Services\PermissionService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cubre la capa de autorización explícita por empresa: qué puede ver/tocar
 * cada admin, qué pasa si se manipula el selector para pedir una empresa no
 * autorizada, y que los envíos "de fondo" (fuera del webhook) nunca usan
 * credenciales de una empresa distinta a la del dato que están procesando.
 */
class CompanyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Necesario para que los usuarios de prueba (no super_admin) pasen
        // el middleware `permission:` de las rutas reales -- el rol "admin"
        // ya trae los permisos de catálogo/chatbot que estas pruebas ejercen.
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    private function adminUser(): User
    {
        $role = Role::where('slug', 'admin')->firstOrFail();

        return User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    }

    private function makeCompanyWithCatalogAndUser(string $slug, string $token, ?User $sharedUser = null): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => $slug,
            'display_name' => $slug,
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => strtoupper($slug) . '-PHONE',
            'access_token' => $token,
            'status' => 'connected',
        ]);

        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => "Menu {$slug}", 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id,
            'title' => "Categoria {$slug}", 'action_id' => "cat_{$slug}", 'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id,
            'category' => $category->title, 'sku' => strtoupper($slug) . '-1',
            'name' => "Producto {$slug}", 'price' => 10, 'currency' => 'USD',
            'is_active' => true, 'stock' => 5,
        ]);

        $user = $sharedUser ?? $this->adminUser();
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'category', 'product', 'user');
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrador', 'is_system' => true]);

        return User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    }

    public function test_admin_sees_only_their_companys_products_and_categories(): void
    {
        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        $this->actingAs($a['user']);
        $this->session(['active_company_id' => $a['company']->id]);

        $response = $this->get(route('admin.products.index'));
        $response->assertOk();
        $response->assertSee('Producto piqueo');
        $response->assertDontSee('Producto zapatos-demo');
    }

    public function test_admin_cannot_access_a_company_they_are_not_authorized_for(): void
    {
        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        // El admin de "piqueo" (sin fila en company_user para zapatos-demo)
        // intenta ver la pantalla de WhatsApp de zapatos-demo por URL directa.
        $response = $this->actingAs($a['user'])->get(route('admin.empresas.whatsapp', $b['company']));

        $response->assertForbidden();
    }

    public function test_manipulated_company_id_in_switcher_is_rejected(): void
    {
        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        $response = $this->actingAs($a['user'])
            ->post(route('admin.active-company.store'), ['company' => $b['company']->slug]);

        $response->assertForbidden();
        $this->assertNotEquals($b['company']->id, session('active_company_id'));
    }

    public function test_super_admin_can_switch_to_any_company(): void
    {
        $super = $this->superAdmin();
        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        $response = $this->actingAs($super)
            ->post(route('admin.active-company.store'), ['company' => $b['company']->slug]);

        $response->assertRedirect();
        $this->assertEquals($b['company']->id, session('active_company_id'));
    }

    public function test_embedded_signup_callback_is_rejected_for_unauthorized_company(): void
    {
        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        // El admin de "piqueo" intenta postear un callback de Embedded
        // Signup como si fuera para "zapatos-demo".
        $response = $this->actingAs($a['user'])->postJson(
            route('admin.empresas.whatsapp.embedded-signup', $b['company']),
            ['code' => 'x', 'waba_id' => 'y', 'phone_number_id' => 'z']
        );

        $response->assertForbidden();
    }

    public function test_background_send_uses_the_contacts_own_company_never_the_first_ones(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        // Contacto de la SEGUNDA empresa creada -- si algo cayera al
        // "primer" perfil de la base (piqueo), este envío usaría TOKEN-A.
        $contactB = WhatsappContact::create([
            'business_profile_id' => $b['profile']->id,
            'phone_number' => '593987654321',
            'name' => 'Cliente B',
            'status' => 'active',
        ]);

        $service = app(WhatsappService::class);
        $service->useBusinessProfile($contactB->businessProfile);
        $service->sendTypingIndicator('wamid.fake');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer TOKEN-B');
        });
        Http::assertNotSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer TOKEN-A');
        });
    }

    public function test_whatsapp_template_controller_fails_closed_without_falling_back_to_env(): void
    {
        config(['whatsapp.token' => 'ENV-FALLBACK-TOKEN', 'whatsapp.business_id' => 'ENV-FALLBACK-BUSINESS']);

        // Empresa autorizada pero SIN WhatsappBusinessProfile todavía.
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'sin-whatsapp', 'slug' => 'sin-whatsapp', 'status' => 'active']);
        $user = $this->adminUser();
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)->postJson(route('admin.marketing.templates.sync'));

        $response->assertStatus(400);
        $response->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('WHATSAPP_PROFILE_NOT_CONFIGURED', $response->json('message'));
    }

    public function test_order_confirmation_service_uses_the_orders_own_company_credentials(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.doc']]], 200)]);

        $a = $this->makeCompanyWithCatalogAndUser('piqueo', 'TOKEN-A');
        $b = $this->makeCompanyWithCatalogAndUser('zapatos-demo', 'TOKEN-B');

        $contactB = WhatsappContact::create([
            'business_profile_id' => $b['profile']->id,
            'phone_number' => '593999111222',
            'name' => 'Cliente B',
            'status' => 'active',
        ]);

        $cart = \App\Models\WhatsappCart::create([
            'contact_id' => $contactB->id,
            'status' => \App\Models\WhatsappCart::STATUS_PENDING,
            'total' => 10,
        ]);

        try {
            app(OrderConfirmationService::class)->sendToClient($cart);
        } catch (\Throwable $e) {
            // El PDF/documento puede fallar en el entorno de test; lo único
            // que interesa acá es qué credencial se usó para el intento.
        }

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer TOKEN-B');
        });
        Http::assertNotSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer TOKEN-A');
        });
    }
}
