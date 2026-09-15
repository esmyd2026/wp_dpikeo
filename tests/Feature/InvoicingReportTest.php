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
 * Pedido explícito: "una lista de las solicitudes de facturación para
 * darle a un equipo que hace facturación electrónica... con los detalles
 * para la facturación como el número de la orden, el total y el envío, el
 * tipo de documento y el documento, nombres, dirección, correo y el botón
 * que confirme que ya envió la factura y se liste en la otra tabla".
 */
class InvoicingReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User, WhatsappContact} */
    private function fixture(): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Factura', 'slug' => 'empresa-factura', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Factura', 'display_name' => 'Empresa Factura',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-FACT', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'super_admin')->firstOrFail();
        $billingUser = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($billingUser->id);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente',
            'address' => 'Dirección del contacto',
        ]);

        return [$company, $profile, $billingUser, $contact];
    }

    public function test_it_splits_pending_and_issued_invoice_requests_with_their_billing_details(): void
    {
        [$company, , $billingUser, $contact] = $this->fixture();

        $pendingOrder = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 32.5,
            'requires_invoice' => true, 'invoice_status' => 'data_ready',
            'invoice_data' => ['billing_type' => 'ruc', 'billing_id' => '0999999999001', 'billing_legal_name' => 'Comercial ACME', 'address' => 'Av. Siempre Viva', 'email' => 'acme@example.com'],
            'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_applied' => 2.5, 'order_details' => ['order_number' => 'ORD-100']],
        ]);
        $issuedOrder = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 18,
            'requires_invoice' => true, 'invoice_status' => 'issued',
            'invoice_data' => ['billing_type' => 'cedula', 'billing_id' => '0912345678', 'billing_legal_name' => 'Juan Pérez', 'address' => 'Calle 1', 'email' => 'juan@example.com'],
            'metadata' => ['order_details' => ['order_number' => 'ORD-101']],
        ]);
        // Consumidor final: no debe aparecer en ninguna de las dos tablas.
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 9,
            'requires_invoice' => false,
            'metadata' => ['order_details' => ['order_number' => 'ORD-102']],
        ]);

        $response = $this->actingAs($billingUser)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.invoicing'));

        $response->assertOk();
        $response->assertSee('ORD-100');
        $response->assertSee('RUC 0999999999001');
        $response->assertSee('Comercial ACME');
        $response->assertSee('$2.50');
        $response->assertSee('$32.50');
        $response->assertSee('ORD-101');
        $response->assertSee('Juan Pérez');
        $response->assertDontSee('ORD-102');
        $response->assertSee(route('admin.orders', ['open_order' => $pendingOrder->id]), false);
        $response->assertSee(route('admin.orders', ['open_order' => $issuedOrder->id]), false);
    }

    public function test_marking_a_pending_request_as_issued_moves_it_via_the_existing_order_update_endpoint(): void
    {
        [$company, , $billingUser, $contact] = $this->fixture();
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 22,
            'requires_invoice' => true, 'invoice_status' => 'data_ready',
            'invoice_data' => ['billing_type' => 'cedula', 'billing_id' => '0912345678', 'billing_legal_name' => 'Cliente Uno', 'address' => 'Calle 2', 'email' => 'c1@example.com'],
            'metadata' => [],
        ]);

        $response = $this->actingAs($billingUser)
            ->withSession(['active_company_id' => $company->id])
            ->putJson("/admin/orders/{$order->id}", ['invoice_status' => 'issued']);

        $response->assertOk();
        $this->assertSame('issued', $order->fresh()->invoice_status);
    }

    public function test_a_role_without_orders_billing_permission_cannot_view_the_report(): void
    {
        [$company, ] = $this->fixture();
        $role = Role::where('slug', 'admin')->firstOrFail();
        $adminWithoutBilling = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($adminWithoutBilling->id);

        $response = $this->actingAs($adminWithoutBilling)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.invoicing'));

        $response->assertRedirect(route('admin.dashboard'));
    }
}
