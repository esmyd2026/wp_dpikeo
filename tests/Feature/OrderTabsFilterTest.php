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
 * Pedido explícito: "quítame los [cancelados y finalizados] de Todos, solo
 * que esté todos los en curso. los cancelados que estén en los finalizados
 * con los ya entregado." -- "Todos" solo debe mostrar pedidos en curso;
 * "Finalizados" debe agrupar entregados Y cancelados.
 */
class OrderTabsFilterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, User, WhatsappContact} */
    private function fixture(): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Tabs Pedidos', 'slug' => 'empresa-tabs-pedidos-'.Str::random(6), 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Tabs Pedidos', 'display_name' => 'Empresa Tabs Pedidos',
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990003001', 'name' => 'Cliente']);

        return [$company, $admin, $contact];
    }

    private function orderWithNumber(WhatsappContact $contact, string $status, string $orderNumber): WhatsappCart
    {
        return WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => $status, 'total' => 10,
            'metadata' => ['order_details' => ['order_number' => $orderNumber]],
        ]);
    }

    public function test_the_todos_tab_only_shows_orders_still_in_progress(): void
    {
        [$company, $admin, $contact] = $this->fixture();
        $this->orderWithNumber($contact, WhatsappCart::STATUS_PENDING, 'ORD-900001');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_CONFIRMED, 'ORD-900002');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_READY, 'ORD-900003');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_COMPLETED, 'ORD-900004');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_CANCELLED, 'ORD-900005');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertSee('ORD-900001');
        $response->assertSee('ORD-900002');
        $response->assertSee('ORD-900003');
        $response->assertDontSee('ORD-900004');
        $response->assertDontSee('ORD-900005');
    }

    public function test_the_finalizados_tab_groups_completed_and_cancelled_orders(): void
    {
        [$company, $admin, $contact] = $this->fixture();
        $this->orderWithNumber($contact, WhatsappCart::STATUS_PENDING, 'ORD-900101');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_COMPLETED, 'ORD-900102');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_CANCELLED, 'ORD-900103');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders', ['segment' => 'closed']));

        $response->assertOk();
        $response->assertDontSee('ORD-900101');
        $response->assertSee('ORD-900102');
        $response->assertSee('ORD-900103');
    }

    public function test_the_todos_tab_count_excludes_completed_and_cancelled(): void
    {
        [$company, $admin, $contact] = $this->fixture();
        $this->orderWithNumber($contact, WhatsappCart::STATUS_PENDING, 'ORD-900201');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_COMPLETED, 'ORD-900202');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_CANCELLED, 'ORD-900203');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        // Solo 1 pedido en curso (el pendiente) debe contarse en "Todos" --
        // antes del arreglo, este contador incluía los 3 (total_count).
        preg_match('/<span>Todos<\/span>\s*<span class="orders-segment-count">(\d+)</', $response->getContent(), $matches);
        $this->assertSame('1', $matches[1] ?? null);
    }

    /**
     * Pedido explícito: "en finalizados colocame dos pestañas uno con los
     * entregados y la otra con los cancelados para que no estén mezclados."
     */
    public function test_finalizados_has_sub_tabs_to_separate_delivered_from_cancelled(): void
    {
        [$company, $admin, $contact] = $this->fixture();
        $this->orderWithNumber($contact, WhatsappCart::STATUS_COMPLETED, 'ORD-900301');
        $this->orderWithNumber($contact, WhatsappCart::STATUS_CANCELLED, 'ORD-900302');

        $delivered = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders', ['segment' => 'closed', 'closed_filter' => 'completed']));
        $delivered->assertOk();
        $delivered->assertSee('ORD-900301');
        $delivered->assertDontSee('ORD-900302');

        $cancelled = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders', ['segment' => 'closed', 'closed_filter' => 'cancelled']));
        $cancelled->assertOk();
        $cancelled->assertSee('ORD-900302');
        $cancelled->assertDontSee('ORD-900301');

        $both = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders', ['segment' => 'closed']));
        $both->assertOk();
        $both->assertSee('ORD-900301');
        $both->assertSee('ORD-900302');
        $both->assertSee('Entregados');
        $both->assertSee('Cancelados');
    }

    public function test_the_sub_tabs_are_hidden_outside_the_finalizados_segment(): void
    {
        [$company, $admin, $contact] = $this->fixture();
        $this->orderWithNumber($contact, WhatsappCart::STATUS_PENDING, 'ORD-900401');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertDontSee('<nav class="orders-subsegments"', false);
    }
}
