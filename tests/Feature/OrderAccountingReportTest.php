<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\OrderAccountingReportService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: mejorar "Reportes de pedidos" con segmentación
 * de métodos de pago, tipo de entrega (delivery/retiro/servir/llevar),
 * costos de delivery generados y facturación, "orientado a nivel contable",
 * descargable, con gráficas de torta y de barras.
 */
class OrderAccountingReportTest extends TestCase
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
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Contable', 'slug' => 'empresa-contable', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Contable', 'display_name' => 'Empresa Contable',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-ACCT', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$company, $profile, $admin, $contact];
    }

    private function makeOrder(WhatsappContact $contact, array $overrides = []): WhatsappCart
    {
        return WhatsappCart::create(array_merge([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_COMPLETED,
            'total' => 10,
            'payment_method' => 'efectivo',
            'requires_invoice' => false,
            'metadata' => [],
            'created_at' => now(),
        ], $overrides));
    }

    public function test_summarize_groups_by_payment_method_fulfillment_delivery_cost_and_invoicing(): void
    {
        [, , , $contact] = $this->fixture();

        // Efectivo, retiro, consumidor final.
        $this->makeOrder($contact, ['total' => 20, 'payment_method' => 'efectivo', 'metadata' => ['pickup_mode' => 'retiro']]);
        // Transferencia, delivery con envío cobrado, con factura.
        $this->makeOrder($contact, [
            'total' => 35, 'payment_method' => 'transferencia', 'requires_invoice' => true,
            'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_applied' => 3.5],
        ]);
        // Tarjeta, delivery con envío cobrado.
        $this->makeOrder($contact, [
            'total' => 15, 'payment_method' => 'tarjeta',
            'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_applied' => 2.5],
        ]);
        // Cancelado -- no debe contarse en nada.
        $this->makeOrder($contact, ['total' => 999, 'status' => WhatsappCart::STATUS_CANCELLED, 'payment_method' => 'tarjeta']);

        $summary = app(OrderAccountingReportService::class)->summarize(now()->subDay(), now()->addDay());

        $this->assertSame(3, $summary['totals']['orders']);
        $this->assertEqualsWithDelta(70.0, $summary['totals']['revenue'], 0.001);
        $this->assertSame(1, $summary['totals']['cancelled_orders']);
        $this->assertEqualsWithDelta(999.0, $summary['totals']['cancelled_amount'], 0.001);

        $byPaymentMethod = collect($summary['payment_methods'])->keyBy('key');
        $this->assertSame(1, $byPaymentMethod['efectivo']['count']);
        $this->assertSame(1, $byPaymentMethod['transferencia']['count']);
        $this->assertSame(1, $byPaymentMethod['tarjeta']['count']);

        $byFulfillment = collect($summary['fulfillment'])->keyBy('key');
        $this->assertSame(1, $byFulfillment['retiro']['count']);
        $this->assertSame(2, $byFulfillment['delivery']['count']);
        $this->assertEqualsWithDelta(50.0, $byFulfillment['delivery']['amount'], 0.001);

        $this->assertSame(2, $summary['delivery_costs']['orders']);
        $this->assertEqualsWithDelta(6.0, $summary['delivery_costs']['total'], 0.001);
        $this->assertEqualsWithDelta(3.0, $summary['delivery_costs']['average'], 0.001);

        $byInvoicing = collect($summary['invoicing'])->keyBy('key');
        $this->assertSame(1, $byInvoicing['with_invoice']['count']);
        $this->assertSame(2, $byInvoicing['final_consumer']['count']);
    }

    public function test_the_reports_screen_shows_the_new_accounting_sections(): void
    {
        [$company, , $admin, $contact] = $this->fixture();
        $this->makeOrder($contact, ['total' => 12, 'payment_method' => 'transferencia', 'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_applied' => 1.5]]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.orders'));

        $response->assertOk();
        $response->assertSee('Desglose contable');
        $response->assertSee('Exportar reporte contable');
        $response->assertSee('paymentMethodChart', false);
        $response->assertSee('fulfillmentChart', false);
        $response->assertSee("type: 'doughnut'", false);
        $response->assertSee("type: 'bar'", false);
        $response->assertSee('Total cobrado por envíos');
    }

    public function test_the_accounting_export_downloads_a_workbook_matching_the_on_screen_summary(): void
    {
        [$company, , $admin, $contact] = $this->fixture();
        $this->makeOrder($contact, ['total' => 40, 'payment_method' => 'efectivo', 'metadata' => ['service_type' => 'llevar']]);
        $this->makeOrder($contact, ['total' => 25, 'payment_method' => 'tarjeta', 'requires_invoice' => true, 'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_applied' => 2]]);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.reports.orders.accounting-export', ['from' => $from, 'to' => $to]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmpPath = tempnam(sys_get_temp_dir(), 'acct_xlsx_test');
        file_put_contents($tmpPath, $response->streamedContent());

        $spreadsheet = IOFactory::load($tmpPath);
        $sheetNames = array_map(fn ($s) => $s->getTitle(), $spreadsheet->getAllSheets());
        $this->assertSame(['Resumen', 'Metodo de pago', 'Tipo de entrega', 'Facturacion'], $sheetNames);

        $summarySheet = $spreadsheet->getSheetByName('Resumen');
        $this->assertSame(2, $summarySheet->getCell('B4')->getValue());
        $this->assertEqualsWithDelta(65.0, (float) $summarySheet->getCell('B5')->getValue(), 0.001);

        $paymentSheet = $spreadsheet->getSheetByName('Metodo de pago');
        $this->assertSame('Categoria', $paymentSheet->getCell('A1')->getValue());

        unlink($tmpPath);
    }
}
