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
 * Pedido explícito en vivo: que "Ver en pantalla completa" active pantalla
 * completa real (ocultando el menú del panel) y que existan atajos de
 * teclado (1/2/3, tipo "bump bar") para pasar un pedido de un estado a
 * otro, igual que las barras físicas de las cadenas de comida rápida.
 */
class KitchenBoardShortcutsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    private function companyWithAdmin(): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Cocina', 'slug' => 'empresa-cocina', 'status' => 'active']);
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Cocina', 'display_name' => 'Empresa Cocina',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-KITCHEN', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $admin];
    }

    public function test_the_board_ships_a_real_fullscreen_toggle_and_the_bump_keys(): void
    {
        [$company, $admin] = $this->companyWithAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.kitchen.index'));

        $response->assertOk();
        // El botón ya no es un <a target="_blank"> a la vista de TV: dispara la Fullscreen API real.
        $response->assertSee('id="kitchenFullscreenBtn"', false);
        $response->assertSee('requestFullscreen', false);
        $response->assertSee('fullscreenchange', false);
        // Atajos de teclado tipo bump bar, visibles solo para quien puede actualizar pedidos.
        $response->assertSee('kitchenBump', false);
        $response->assertSee("event.key === '1'", false);
        $response->assertSee("event.key === '2'", false);
        $response->assertSee("event.key === '3'", false);
        $response->assertSee('Atajos de teclado (siempre al pedido más antiguo');
        $response->assertSee('const kitchenCanUpdate = true;', false);
    }

    public function test_users_without_update_permission_do_not_see_the_shortcuts_hint(): void
    {
        [$company] = $this->companyWithAdmin();
        // El rol "viewer" trae kitchen.menu/orders.view pero no orders.update
        // (config/permissions.php) -- puede ver el tablero pero no accionarlo.
        $viewerRole = Role::where('slug', 'viewer')->firstOrFail();
        $viewer = User::factory()->create(['is_admin' => true, 'role_id' => $viewerRole->id]);
        $company->users()->attach($viewer->id);

        $response = $this->actingAs($viewer)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.kitchen.index'));

        $response->assertOk();
        // El aviso de atajos vive dentro de @if($canUpdateKitchen) -- no basta
        // con buscar "Atajos de teclado" a secas porque esa misma frase
        // también aparece en un comentario CSS que siempre se imprime.
        $response->assertDontSee('Atajos de teclado (siempre al pedido más antiguo');
        $response->assertSee('const kitchenCanUpdate = false;', false);
    }
}
