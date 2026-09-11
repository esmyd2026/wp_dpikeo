<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: que "Ver en pantalla completa" active pantalla
 * completa real (ocultando el menú del panel) y que existan atajos de
 * teclado (1/2/3, tipo "bump bar") para pasar un pedido de un estado a
 * otro, igual que las barras físicas de las cadenas de comida rápida. Una
 * segunda pulsación confirma para evitar que un toque accidental mueva la orden.
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
        $response->assertSee('Control rápido del primer pedido:');
        $response->assertSee('Presiona una vez para seleccionar y otra vez para confirmar');
        $response->assertSee('const kitchenVisibleLimits = { queue: 1, preparing: 3, ready: 3 };', false);
        $response->assertSee('function requestKitchenAction(order)', false);
        $response->assertSee('const isConfirmation =', false);
        $response->assertSee('kitchenArmedAction = { orderId:', false);
        $response->assertDontSee('Caja confirma');
        $response->assertSee('Cola visible');
        $response->assertDontSee('<details class="kitchen-overflow"', false);
        $response->assertSee('data-detail-order=', false);
        $response->assertSee('function openKitchenDetail(orderId)', false);
        $response->assertSee('id="kitchenDetailModal"', false);
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
        // El aviso visible vive dentro de @if($canUpdateKitchen), aunque el
        // JavaScript permanezca presente e inactivo para todos los usuarios.
        $response->assertDontSee('Control rápido del primer pedido:');
        $response->assertDontSee('Presiona una vez para seleccionar y otra vez para confirmar');
        $response->assertSee('const kitchenCanUpdate = false;', false);
    }

    public function test_the_first_queue_order_is_the_oldest_even_when_payment_statuses_differ(): void
    {
        [$company, $admin] = $this->companyWithAdmin();
        $profile = WhatsappBusinessProfile::where('company_id', $company->id)->firstOrFail();
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593990000099',
            'name' => 'Cliente cocina',
            'status' => 'active',
        ]);
        $oldestPaid = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_PAID,
            'total' => 10,
        ]);
        $oldestPaid->forceFill(['created_at' => now()->subMinutes(20)])->save();
        $newerConfirmed = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_CONFIRMED,
            'total' => 10,
        ]);
        $newerConfirmed->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.kitchen.data'));

        $response->assertOk();
        $this->assertSame($oldestPaid->id, $response->json('orders.0.id'));
        $this->assertSame($newerConfirmed->id, $response->json('orders.1.id'));
    }
}
