<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito: el negocio tiene más sucursales de las que hoy reciben
 * pedidos por WhatsApp, pero quiere mostrar TODAS en el bot de Información
 * (dirección, teléfono, horarios). `is_active` ya no alcanza para eso solo
 * (antes controlaba las dos cosas a la vez) -- se agrega `orders_enabled`
 * para que una sucursal pueda estar activa (visible en Información) sin
 * participar del checkout/delivery.
 */
class BranchOrdersToggleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, BusinessBranch, BusinessBranch} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $infoOnly = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Solo informativa', 'code' => 'INFO1',
            'is_default' => true, 'is_active' => true, 'orders_enabled' => false,
            'address' => 'Av. Siempre Viva 123', 'reservations_info' => 'Reservas al 099-000-0000',
        ]);
        $ordersBranch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Recibe pedidos', 'code' => 'ORD1',
            'is_active' => true, 'orders_enabled' => true,
        ]);

        return [$profile, $infoOnly, $ordersBranch];
    }

    public function test_available_for_orders_scope_excludes_branches_not_enabled_for_orders(): void
    {
        [, $infoOnly, $ordersBranch] = $this->fixture();

        $branchIds = BusinessBranch::availableForOrders()->pluck('id')->all();

        $this->assertNotContains($infoOnly->id, $branchIds);
        $this->assertContains($ordersBranch->id, $branchIds);
    }

    public function test_a_branch_disabled_for_orders_is_never_auto_assigned_to_a_new_cart(): void
    {
        [$profile, $infoOnly, $ordersBranch] = $this->fixture();
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        // Solo queda UNA sucursal habilitada para pedidos (la otra es
        // is_active pero orders_enabled=false) -- el carrito nuevo debe
        // auto-asignarse a esa, nunca a la informativa.
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        $this->assertSame($ordersBranch->id, $cart->branch_id);
        $this->assertNotSame($infoOnly->id, $cart->branch_id);
    }

    public function test_defaults_to_true_so_existing_branches_keep_receiving_orders(): void
    {
        [$profile] = $this->fixture();

        // Una sucursal creada sin especificar el campo nuevo (como ya
        // existían antes de esta migración) sigue recibiendo pedidos.
        $legacyBranch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Sucursal vieja', 'code' => 'OLD1', 'is_active' => true,
        ]);

        $this->assertTrue($legacyBranch->fresh()->orders_enabled);
    }
}
