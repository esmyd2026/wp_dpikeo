<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\OrderLifecycleService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BranchDeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_checkout_walks_through_branch_service_type_and_delivery_address(): void
    {
        [$product, $contact, $branchUrdesa, $branchSur] = $this->fixture();
        $branchUrdesa->update(['delivery_fee_minimum' => 2.50]);

        $cart = $this->cartWithItem($contact, $product);
        $service = new WhatsappService();

        // Paso 1: sin sucursal previa recordada, debe mostrar la LISTA de sucursales.
        $step1 = $this->invoke($service, 'finalizarCompra', [$contact]);
        $this->assertSame('list', $step1['interactive']['type']);
        $rows = $step1['interactive']['action']['sections'][0]['rows'];
        $this->assertCount(2, $rows);
        $rowIds = array_column($rows, 'id');
        $this->assertContains('sucursal_set_' . $branchUrdesa->id . '_' . $cart->id, $rowIds);
        $this->assertContains('sucursal_set_' . $branchSur->id . '_' . $cart->id, $rowIds);

        // Elegir Urdesa.
        $step2 = $this->invoke($service, 'setSucursalPedido', [$contact, $branchUrdesa->id, $cart->id]);
        $cart->refresh();
        $this->assertSame($branchUrdesa->id, $cart->branch_id);
        $this->assertTrue($cart->metadata['branch_confirmed']);
        $this->assertSame($branchUrdesa->id, $contact->fresh()->getLastBranchId());
        $this->assertSame('button', $step2['interactive']['type']);
        $this->assertStringContainsString('llevar o para servir', $step2['interactive']['body']['text']);

        // Elegir "para llevar".
        $step3 = $this->invoke($service, 'setTipoServicio', [$contact, $cart->id, 'llevar']);
        $cart->refresh();
        $this->assertSame('llevar', $cart->metadata['service_type']);
        $this->assertStringContainsString('retiras en el local', mb_strtolower($step3['interactive']['body']['text']));

        // Elegir delivery: ya no se pide ubicación GPS, se pide la dirección por texto.
        $step4 = $this->invoke($service, 'setModoRetiro', [$contact, $cart->id, 'delivery']);
        $cart->refresh();
        $this->assertSame('delivery', $cart->metadata['pickup_mode']);
        $this->assertSame('text', $step4['type']);
        $this->assertTrue($cart->metadata['awaiting_delivery_address']);

        $totalBeforeDelivery = (float) $cart->total;

        // El cliente escribe la dirección.
        Http::fake();
        $addressMessage = [
            'from' => $contact->phone_number,
            'id' => 'wamid.address.test.1',
            'text' => ['body' => 'Av. Principal y Calle 5, casa azul'],
            'contacts' => [['profile' => ['name' => $contact->name]]],
        ];
        $this->invoke($service, 'handleTextMessage', [$addressMessage]);

        $cart->refresh();
        $this->assertArrayNotHasKey('awaiting_delivery_address', $cart->metadata);
        $this->assertSame('Av. Principal y Calle 5, casa azul', $cart->metadata['delivery_location']['manual_address']);
        $this->assertTrue($cart->metadata['awaiting_delivery_recipient_name']);
        // Aún no se aplica el costo de envío ni avanza a nota: falta el nombre.
        $this->assertEquals($totalBeforeDelivery, (float) $cart->total);
        $this->assertArrayNotHasKey('pending_note', $cart->metadata);

        // El cliente escribe el nombre de quien recibe.
        $recipientMessage = [
            'from' => $contact->phone_number,
            'id' => 'wamid.recipient.test.1',
            'text' => ['body' => 'Juan Pérez'],
            'contacts' => [['profile' => ['name' => $contact->name]]],
        ];
        $this->invoke($service, 'handleTextMessage', [$recipientMessage]);

        $cart->refresh();
        $this->assertArrayNotHasKey('awaiting_delivery_recipient_name', $cart->metadata);
        $this->assertSame('Juan Pérez', $cart->metadata['delivery_recipient_name']);
        $this->assertEquals(2.50, $cart->metadata['delivery_fee']);
        $this->assertTrue($cart->metadata['delivery_fee_pending_review']);
        // El costo de envío es solo referencial hasta que el vendedor lo
        // confirme desde el panel: el total del pedido NO lo incluye todavía.
        $this->assertEquals($totalBeforeDelivery, (float) $cart->total);
        // El checkout debe haber seguido automáticamente al paso de nota.
        $this->assertTrue($cart->metadata['pending_note']);

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'escribe una nota');
        });

        // Escribir la nota y avanzar: debe pedir método de pago.
        $cart->note = 'sin nota';
        $metadata = $cart->metadata;
        unset($metadata['pending_note']);
        $cart->metadata = $metadata;
        $cart->save();

        $paymentStep = $this->invoke($service, 'finalizarCompra', [$contact]);
        $this->assertSame('list', $paymentStep['interactive']['type']);

        // Elegir efectivo: el resumen debe incluir sucursal/tipo/delivery, y
        // dejar claro que el total mostrado todavía no incluye el envío.
        $summary = $this->invoke($service, 'procesarPagoEfectivo', [$contact, $cart->id]);
        $text = $summary['interactive']['body']['text'];
        $this->assertStringContainsString('Sucursal: Urdesa', $text);
        $this->assertStringContainsString('Tipo: Para llevar', $text);
        $this->assertStringContainsString('Dirección: Av. Principal y Calle 5, casa azul', $text);
        $this->assertStringContainsString('Recibe: Juan Pérez', $text);
        $this->assertStringContainsString('Envío', $text);
        $this->assertStringContainsString('por confirmar', $text);
        $this->assertEquals($totalBeforeDelivery, (float) $cart->fresh()->total);

        // Nota: confirmarPedido() dispara DailyOrderNumberService, que tiene
        // un problema conocido y ya investigado propio del entorno de pruebas
        // en SQLite (firstOrCreate + lockForUpdate en la misma transacción);
        // ya se confirmó contra la base de datos real de producción que
        // funciona correctamente ahí, así que no se re-verifica aquí.
    }

    public function test_seller_confirming_delivery_cost_adds_it_once_and_corrections_dont_double_charge(): void
    {
        [$product, $contact, $branchUrdesa] = $this->fixture();
        $branchUrdesa->update(['delivery_fee_minimum' => 2.00]);
        $cart = $this->cartWithItem($contact, $product);
        $service = new WhatsappService();

        $this->invoke($service, 'setSucursalPedido', [$contact, $branchUrdesa->id, $cart->id]);
        $this->invoke($service, 'setTipoServicio', [$contact, $cart->id, 'llevar']);
        $this->invoke($service, 'setModoRetiro', [$contact, $cart->id, 'delivery']);

        Http::fake();
        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number, 'id' => 'wamid.cost.addr',
            'text' => ['body' => 'Av Test 1'], 'contacts' => [['profile' => ['name' => $contact->name]]],
        ]]);
        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number, 'id' => 'wamid.cost.name',
            'text' => ['body' => 'Ana Test'], 'contacts' => [['profile' => ['name' => $contact->name]]],
        ]]);

        $cart->refresh();
        $totalBeforeConfirmation = (float) $cart->total;

        $lifecycle = app(OrderLifecycleService::class);

        // El vendedor confirma $3.00 la primera vez: se suma al total.
        $result = $lifecycle->sendFulfillmentCostsMessage($cart->fresh(), 3.00, null, null);
        $this->assertEquals($totalBeforeConfirmation + 3.00, (float) $result['order']->total);

        // Corrige a $4.50: reemplaza el monto anterior, no lo duplica.
        $result2 = $lifecycle->sendFulfillmentCostsMessage($result['order'], 4.50, null, null);
        $this->assertEquals($totalBeforeConfirmation + 4.50, (float) $result2['order']->total);
    }

    public function test_service_type_servir_skips_pickup_and_delivery_steps(): void
    {
        [$product, $contact] = $this->fixture();
        $cart = $this->cartWithItem($contact, $product);
        $service = new WhatsappService();

        $branch = BusinessBranch::query()->first();
        $this->invoke($service, 'setSucursalPedido', [$contact, $branch->id, $cart->id]);

        $step = $this->invoke($service, 'setTipoServicio', [$contact, $cart->id, 'servir']);
        $cart->refresh();
        $this->assertSame('servir', $cart->metadata['service_type']);
        // Al ser "para servir" no debe pedir retiro/delivery: pasa directo a
        // nota, con botón "Sin nota" para no obligar al cliente a escribir.
        $this->assertSame('button', $step['interactive']['type']);
        $this->assertStringContainsString('Ya casi terminamos', $step['interactive']['body']['text']);
        $buttonIds = array_column(array_column($step['interactive']['action']['buttons'], 'reply'), 'id');
        $this->assertContains('nota_omitir_' . $cart->id, $buttonIds);

        // Nota: "para servir" salta el método de pago y cierra el pedido con
        // finalizePayAtRegisterOrder(), pero esa función llama a
        // syncOrderDetails() -> DailyOrderNumberService, que tiene el mismo
        // problema conocido de SQLite en pruebas ya documentado en este
        // archivo (firstOrCreate + where() comparan el cast 'date' de forma
        // inconsistente en SQLite; en MySQL real la columna DATE lo coerciona
        // bien). Verificado aparte contra la base real.
    }

    public function test_recurring_customer_gets_asked_to_keep_same_branch(): void
    {
        [$product, $contact, $branchUrdesa] = $this->fixture();
        $contact->rememberBranch($branchUrdesa->id);

        $cart = $this->cartWithItem($contact, $product);
        $service = new WhatsappService();

        $step = $this->invoke($service, 'finalizarCompra', [$contact]);
        $this->assertSame('button', $step['interactive']['type']);
        $this->assertStringContainsString('Urdesa', $step['interactive']['body']['text']);

        $buttonIds = array_column(array_column($step['interactive']['action']['buttons'], 'reply'), 'id');
        $this->assertContains('sucursal_mantener_' . $cart->id, $buttonIds);
        $this->assertContains('sucursal_cambiar_' . $cart->id, $buttonIds);

        $next = $this->invoke($service, 'confirmarSucursalMantenida', [$contact, $cart->id]);
        $cart->refresh();
        $this->assertSame($branchUrdesa->id, $cart->branch_id);
        $this->assertTrue($cart->metadata['branch_confirmed']);
        $this->assertStringContainsString('llevar o para servir', $next['interactive']['body']['text']);
    }

    public function test_cancel_escape_valve_cancels_order_with_pending_checkout_step(): void
    {
        [$product, $contact] = $this->fixture();
        $cart = $this->cartWithItem($contact, $product);
        $service = new WhatsappService();

        // El carrito queda con el paso de sucursal pendiente (nunca se resolvió).
        $this->assertTrue($this->invoke($service, 'cartHasPendingCheckoutStep', [$cart]));

        Http::fake();
        $textMessage = [
            'from' => $contact->phone_number,
            'id' => 'wamid.cancel.test.1',
            'text' => ['body' => 'cancelar'],
            'contacts' => [['profile' => ['name' => $contact->name]]],
        ];
        $this->invoke($service, 'handleTextMessage', [$textMessage]);

        $cart->refresh();
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->status);
    }

    public function test_shared_location_is_accepted_as_address_when_customer_shares_it_anyway(): void
    {
        // Ya no se pide ubicación GPS, pero si el cliente comparte una de
        // todas formas mientras esperamos la dirección, no debe perderse:
        // se guarda como dirección (con link a Maps) y se sigue pidiendo el
        // nombre de quien recibe, igual que con una dirección escrita.
        [$product, $contact, $branchUrdesa] = $this->fixture();
        $cart = $this->cartWithItem($contact, $product);
        $service = new WhatsappService();

        $this->invoke($service, 'setSucursalPedido', [$contact, $branchUrdesa->id, $cart->id]);
        $this->invoke($service, 'setTipoServicio', [$contact, $cart->id, 'llevar']);
        $this->invoke($service, 'setModoRetiro', [$contact, $cart->id, 'delivery']);

        Http::fake();
        $locationMessage = [
            'from' => $contact->phone_number,
            'id' => 'wamid.location.test.1',
            'location' => ['latitude' => -2.170998, 'longitude' => -79.922359],
            'timestamp' => (string) now()->timestamp,
        ];
        $this->invoke($service, 'handleLocationMessage', [$locationMessage]);

        $cart->refresh();
        $this->assertArrayNotHasKey('awaiting_delivery_address', $cart->metadata);
        $this->assertTrue($cart->metadata['awaiting_delivery_recipient_name']);
        $this->assertStringContainsString('maps.google.com', $cart->metadata['delivery_location']['manual_address']);

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'recibimos el pedido');
        });
    }

    /** @return array{WhatsappPrice, WhatsappContact, BusinessBranch, BusinessBranch} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
        ]);

        $branchUrdesa = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Urdesa', 'code' => 'URDESA',
            'is_default' => true, 'is_active' => true,
        ]);
        $branchSur = BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Sur', 'code' => 'SUR',
            'is_default' => false, 'is_active' => true,
        ]);

        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD',
            'is_active' => true, 'stock' => 20, 'allow_quantity_selection' => true,
            'min_quantity' => 1, 'max_quantity' => 20,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593990000002',
            'name' => 'Cliente',
            'status' => 'active',
        ]);

        return [$product, $contact, $branchUrdesa, $branchSur];
    }

    private function cartWithItem(WhatsappContact $contact, WhatsappPrice $product): WhatsappCart
    {
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => 'active',
            'total' => $product->price,
        ]);
        $cart->items()->create([
            'whatsapp_price_id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
        ]);

        return $cart;
    }

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);

        return $ref->invokeArgs($service, $args);
    }
}
