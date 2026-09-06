<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappCartNote;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPrice;
use App\Services\OrderLifecycleService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Borrado definitivo de un pedido (distinto de cancelarlo): protegido por el
 * permiso orders.delete, borra en cascada lo propio del pedido y devuelve el
 * stock reservado -- incluso para pedidos 'completed', que normalmente no
 * tienen ninguna transición de estado permitida hacia 'cancelled'.
 */
class OrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{company: Company, profile: WhatsappBusinessProfile, product: WhatsappPrice, contact: WhatsappContact, user: User} */
    private function makeCompanyOrderSetup(bool $grantDeletePermission): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'empresa-test',
            'slug' => 'empresa-test-' . Str::random(6),
            'status' => 'active',
        ]);

        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'empresa-test', 'display_name' => 'empresa-test',
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => 'PHONE-' . Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menu', 'type' => 'list',
            'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Cat', 'action_id' => 'cat_x',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id,
            'category' => 'Cat', 'sku' => 'SKU-' . Str::random(6),
            'name' => 'Producto test', 'price' => 10, 'currency' => 'USD',
            'is_active' => true, 'stock' => 5,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active',
        ]);

        $role = Role::where('slug', 'admin')->firstOrFail();
        if ($grantDeletePermission) {
            $permission = \App\Models\Permission::where('key', 'orders.delete')->firstOrFail();
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'product', 'contact', 'user');
    }

    private function orderWithLine(WhatsappContact $contact, WhatsappPrice $product, int $quantity, string $status): WhatsappCart
    {
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => $status,
            'total' => $product->price * $quantity,
            'metadata' => ['order_details' => ['order_number' => 'ORD-TEST']],
        ]);
        $order->items()->create([
            'whatsapp_price_id' => $product->id, 'name' => $product->name,
            'price' => $product->price, 'quantity' => $quantity,
        ]);

        return $order;
    }

    public function test_user_without_delete_permission_gets_forbidden(): void
    {
        $setup = $this->makeCompanyOrderSetup(grantDeletePermission: false);
        $order = $this->orderWithLine($setup['contact'], $setup['product'], 2, WhatsappCart::STATUS_PENDING);

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->deleteJson(route('admin.orders.destroy', $order->id));

        $response->assertForbidden();
        $this->assertNotNull($order->fresh());
    }

    public function test_deleting_a_reserved_order_releases_stock_and_removes_cascaded_records(): void
    {
        $setup = $this->makeCompanyOrderSetup(grantDeletePermission: true);
        $order = $this->orderWithLine($setup['contact'], $setup['product'], 2, WhatsappCart::STATUS_PENDING);

        // Confirmar reserva stock antes de borrar (simula un pedido en curso).
        app(OrderLifecycleService::class)->transition($order, WhatsappCart::STATUS_CONFIRMED);
        $this->assertSame(3, $setup['product']->fresh()->stock);

        $note = WhatsappCartNote::create([
            'whatsapp_cart_id' => $order->id, 'user_id' => $setup['user']->id,
            'type' => 'internal', 'body' => 'Nota de prueba',
        ]);

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->deleteJson(route('admin.orders.destroy', $order->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertNull(WhatsappCart::find($order->id));
        $this->assertNull(WhatsappCartNote::find($note->id), 'Las notas deben borrarse en cascada.');
        $this->assertSame(0, $order->items()->count());

        // Stock devuelto por completo (reservó 2, quedó en 3, vuelve a 5).
        $this->assertSame(5, $setup['product']->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'whatsapp_price_id' => $setup['product']->id,
            'type' => InventoryMovement::TYPE_SALE_RELEASE,
            'quantity' => 2,
        ]);
    }

    public function test_deleting_a_completed_order_still_releases_stock_even_though_it_has_no_allowed_transition(): void
    {
        $setup = $this->makeCompanyOrderSetup(grantDeletePermission: true);
        $order = $this->orderWithLine($setup['contact'], $setup['product'], 2, WhatsappCart::STATUS_PENDING);

        app(OrderLifecycleService::class)->transition($order, WhatsappCart::STATUS_CONFIRMED);
        app(OrderLifecycleService::class)->transition($order->fresh(), WhatsappCart::STATUS_PREPARING);
        app(OrderLifecycleService::class)->transition($order->fresh(), WhatsappCart::STATUS_READY);
        $order = $order->fresh();
        app(OrderLifecycleService::class)->transition($order, WhatsappCart::STATUS_COMPLETED);
        $this->assertSame(3, $setup['product']->fresh()->stock);

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->deleteJson(route('admin.orders.destroy', $order->id));

        $response->assertOk();
        $this->assertNull(WhatsappCart::find($order->id));
        $this->assertSame(5, $setup['product']->fresh()->stock);
    }

    public function test_deleting_an_order_never_touched_its_whatsapp_chat_history(): void
    {
        $setup = $this->makeCompanyOrderSetup(grantDeletePermission: true);
        $order = $this->orderWithLine($setup['contact'], $setup['product'], 1, WhatsappCart::STATUS_PENDING);

        $message = WhatsappMessage::create([
            'contact_id' => $setup['contact']->id,
            'business_profile_id' => $setup['profile']->id,
            'message_id' => 'wamid.' . Str::random(10),
            'type' => 'text', 'status' => 'sent', 'sender_type' => 'system', 'receiver_type' => 'client',
            'content' => 'Tu pedido fue confirmado',
            'metadata' => ['cart_id' => $order->id],
        ]);

        $this->actingAs($setup['user'])
            ->withSession(['active_company_id' => $setup['company']->id])
            ->deleteJson(route('admin.orders.destroy', $order->id))
            ->assertOk();

        $this->assertNotNull(WhatsappMessage::find($message->id), 'El historial de chat no debe borrarse junto con el pedido.');
    }

    public function test_company_a_cannot_delete_an_order_belonging_to_company_b(): void
    {
        $a = $this->makeCompanyOrderSetup(grantDeletePermission: true);
        $b = $this->makeCompanyOrderSetup(grantDeletePermission: true);
        $orderB = $this->orderWithLine($b['contact'], $b['product'], 1, WhatsappCart::STATUS_PENDING);

        $response = $this->actingAs($a['user'])
            ->withSession(['active_company_id' => $a['company']->id])
            ->deleteJson(route('admin.orders.destroy', $orderB->id));

        $response->assertNotFound();
        $this->assertNotNull(WhatsappCart::find($orderB->id));
    }
}
