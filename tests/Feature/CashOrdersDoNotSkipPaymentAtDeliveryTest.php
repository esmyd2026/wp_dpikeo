<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\OrderLifecycleService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: en efectivo el cliente todavía NO ha pagado
 * cuando el pedido pasa a "confirmado" -- paga al recibir, y es el
 * repartidor/caja quien confirma el cobro en ese momento, un proceso
 * distinto al de transferencia/tarjeta (que sí verifica un comprobante
 * antes). "Pago recibido" (STATUS_PAID) representa esa verificación de
 * comprobante y no debe ofrecerse ni aplicarse a pedidos en efectivo, ni en
 * el panel ni en el detalle que ve el cliente en el micrositio.
 */
class CashOrdersDoNotSkipPaymentAtDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cash_order_cannot_be_transitioned_to_paid(): void
    {
        $contact = WhatsappContact::create(['phone_number' => '593990002001', 'name' => 'Cliente Efectivo']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING,
            'payment_method' => 'efectivo', 'total' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('efectivo');

        app(OrderLifecycleService::class)->transition($cart, WhatsappCart::STATUS_PAID);
    }

    public function test_a_transferencia_order_can_still_be_transitioned_to_paid(): void
    {
        $contact = WhatsappContact::create(['phone_number' => '593990002002', 'name' => 'Cliente Transferencia']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING,
            'payment_method' => 'transferencia', 'total' => 10,
        ]);

        app(OrderLifecycleService::class)->transition($cart, WhatsappCart::STATUS_PAID);

        $this->assertSame(WhatsappCart::STATUS_PAID, $cart->fresh()->status);
    }

    public function test_allowed_transitions_for_omits_paid_only_when_payment_method_is_cash(): void
    {
        $this->assertNotContains('paid', OrderLifecycleService::allowedTransitionsFor('pending', 'efectivo'));
        $this->assertContains('paid', OrderLifecycleService::allowedTransitionsFor('pending', 'transferencia'));
        $this->assertContains('paid', OrderLifecycleService::allowedTransitionsFor('pending'));
    }

    public function test_the_admin_orders_screen_never_offers_paid_as_a_stage_for_a_cash_order(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Efectivo', 'slug' => 'empresa-efectivo-cobro', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Efectivo', 'display_name' => 'Empresa Efectivo',
            'phone_number' => '593990002003', 'phone_number_id' => 'PHONE-CASHFLOW', 'access_token' => 'test',
            'status' => 'connected',
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990002004', 'name' => 'Cliente Efectivo Panel']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING,
            'payment_method' => 'efectivo', 'total' => 15,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        // El botón lleva el método de pago para que el JS del modal (que
        // reutiliza una sola lista de opciones para todos los pedidos) sepa
        // ocultar "Pago recibido" para este pedido en particular.
        $response->assertSee('data-payment-method="efectivo"', false);
        $response->assertSee('id="status-button-'.$cart->id.'"', false);
        $response->assertSee("if (triggerEl.dataset.paymentMethod === 'efectivo')", false);
    }

    public function test_the_customer_order_progress_treats_cash_payment_as_confirmed_only_at_delivery(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593990002005', 'phone_number_id' => 'PHONE-CASHSTOREFRONT',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee("const isCash=order.payment?.method==='efectivo';", false);
        $response->assertSee("['Pedido','Preparación','Entrega y pago']", false);
    }
}
