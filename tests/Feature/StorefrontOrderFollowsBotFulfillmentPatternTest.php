<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
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
 * Pedido explícito en vivo: los pedidos hechos desde el micrositio (raíz "/"
 * y "/tienda/{slug}") siempre aparecían en el panel de admin como "Para
 * servir" -- incluso los de delivery -- y sin la dirección del cliente. La
 * causa: el micrositio guardaba el tipo de entrega con su propio vocabulario
 * ('service_type' => 'pickup'/'delivery', dirección en 'delivery.address')
 * en vez del que ya usa el bot ('service_type' => 'llevar' + 'pickup_mode',
 * dirección en 'delivery_location.manual_address'), que es lo único que el
 * panel de admin, el PDF y los reportes reconocen. El punto de venta (POS)
 * ya usaba el vocabulario correcto -- solo ofrece retiro, nunca delivery.
 */
class StorefrontOrderFollowsBotFulfillmentPatternTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, BusinessBranch, WhatsappPrice, User} */
    private function fixture(string $suffix): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Fulfillment', 'slug' => 'empresa-fulfillment-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593994'.$suffix, 'phone_number_id' => 'DPIKEOS-FULFILL-'.$suffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URD-'.$suffix,
            'is_active' => true, 'orders_enabled' => true, 'is_default' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id,
            'title' => 'Boxes', 'action_id' => 'boxes', 'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id,
            'category' => 'Boxes', 'sku' => 'BOX-FULFILL-'.$suffix, 'name' => 'Box Tender',
            'price' => 4.5, 'currency' => 'USD', 'is_active' => true, 'stock' => 5,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $branch, $product, $admin];
    }

    public function test_a_delivery_order_from_the_storefront_shows_as_para_llevar_with_the_clients_address_in_admin(): void
    {
        [$company, , $branch, $product, $admin] = $this->fixture('200001');

        $submit = $this->postJson("/tienda/{$company->slug}/pedido", [
            'name' => 'Cliente Delivery', 'phone' => '0991110001',
            'service_type' => 'delivery',
            'address' => 'Av. Siempre Viva 123', 'reference' => 'Casa azul, portón negro',
            'branch_id' => $branch->id, 'payment_method' => 'efectivo', 'requires_invoice' => false,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $submit->assertOk()->assertJsonPath('ok', true);
        $orderId = $submit->json('order_id');

        $details = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->getJson("/admin/orders/{$orderId}/details");

        $details->assertOk();
        $details->assertJsonPath('fulfillment.service_type_label', 'Para llevar');
        $details->assertJsonPath('fulfillment.pickup_mode', 'delivery');
        $details->assertJsonPath('fulfillment.pickup_mode_label', 'Delivery');
        $details->assertJsonPath('fulfillment.address', 'Av. Siempre Viva 123');
        $details->assertJsonPath('fulfillment.reference', 'Casa azul, portón negro');
        $details->assertJsonPath('fulfillment.recipient_name', 'Cliente Delivery');
    }

    public function test_a_pickup_order_from_the_storefront_shows_as_para_llevar_retiro_not_para_servir(): void
    {
        [$company, , $branch, $product, $admin] = $this->fixture('200002');

        $submit = $this->postJson("/tienda/{$company->slug}/pedido", [
            'name' => 'Cliente Retiro', 'phone' => '0991110002',
            'service_type' => 'pickup',
            // "Pide y retira" solo acepta transferencia (ver
            // StorefrontPickupOnlyTransferPaymentTest) -- este test prueba
            // el etiquetado de sucursal/entrega, no la forma de pago.
            'branch_id' => $branch->id, 'payment_method' => 'transferencia', 'requires_invoice' => false,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $submit->assertOk()->assertJsonPath('ok', true);
        $orderId = $submit->json('order_id');

        $details = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->getJson("/admin/orders/{$orderId}/details");

        $details->assertOk();
        $details->assertJsonPath('fulfillment.service_type_label', 'Para llevar');
        $details->assertJsonPath('fulfillment.pickup_mode', 'retiro');
        $details->assertJsonPath('fulfillment.pickup_mode_label', 'Retiro en el local');
    }

    public function test_the_customers_own_order_tracking_still_shows_delivery_and_the_address(): void
    {
        [$company, , $branch, $product] = $this->fixture('200003');
        $this->postJson("/tienda/{$company->slug}/cuenta/registro", [
            'name' => 'Cliente Propio', 'phone' => '0991110003',
            'password' => 'secreto1', 'password_confirmation' => 'secreto1',
        ])->assertOk();

        $submit = $this->postJson("/tienda/{$company->slug}/pedido", [
            'name' => 'Cliente Propio', 'phone' => '0991110003',
            'service_type' => 'delivery',
            'address' => 'Calle Falsa 456', 'reference' => 'Frente al parque',
            'branch_id' => $branch->id, 'payment_method' => 'efectivo', 'requires_invoice' => false,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $submit->assertOk();

        $orders = $this->getJson("/tienda/{$company->slug}/cuenta/pedidos");
        $orders->assertOk();
        $order = collect($orders->json('orders'))->firstWhere('id', $submit->json('order_id'));

        $this->assertNotNull($order);
        $this->assertSame('Delivery', $order['fulfillment']['label']);
        $this->assertSame('Calle Falsa 456', $order['fulfillment']['address']);
        $this->assertSame('Frente al parque', $order['fulfillment']['reference']);
    }
}
