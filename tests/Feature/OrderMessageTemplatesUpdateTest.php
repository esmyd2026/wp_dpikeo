<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\MessageTemplate;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: reescribir el ticket "¡Tu pedido está casi
 * listo!" (order_confirmation_ticket, se ve al armar el pedido por el
 * micrositio) para que incluya envío/entrega/total, y "Pedido confirmado"
 * (order_confirmed, plantillas de pago) para que muestre el total y los
 * datos bancarios en un solo bloque.
 */
class OrderMessageTemplatesUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, BusinessBranch, WhatsappContact, WhatsappPrice} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['bank_transfer_instructions' => "Banco Pichincha\nCuenta corriente: 2100320686\nTitular: DPIKEOS S.A.S."],
        ]);
        $branch = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'URDESA', 'code' => 'URD', 'is_default' => true, 'is_active' => true]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Gregorio Osorio']);
        $menu = WhatsappMenu::create(['business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'Menú', 'action_id' => 'prices_menu']);
        $category = WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test']);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Producto de prueba', 'price' => 1, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);

        return [$profile, $branch, $contact, $product];
    }

    public function test_the_confirmation_ticket_shows_shipping_delivery_and_total(): void
    {
        [, $branch, $contact, $product] = $this->fixture();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 36.48,
            'metadata' => [
                'source' => 'bulk_web_form', 'pickup_mode' => 'delivery', 'service_type' => 'llevar',
                'delivery_fee' => 2.5,
                'delivery_location' => ['manual_address' => 'Av. Siempre Viva 123'],
                'delivery_recipient_name' => 'Gregorio Osorio',
            ],
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => 'Mega D-Box', 'price' => 20, 'quantity' => 1]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => 'Combo Familiar', 'price' => 16.48, 'quantity' => 1]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $payload = $service->buildOrderConfirmationPayload($cart, 'https://app.dpikeos.com/orden/1/pdf');
        $body = $payload['interactive']['body']['text'];

        $this->assertStringContainsString('¡Tu pedido está casi listo!', $body);
        $this->assertStringContainsString('Mega D-Box x1', $body);
        $this->assertStringContainsString('🚚 Envío: $2.50', $body);
        $this->assertStringContainsString('💰 *Total a pagar:* $36.48', $body);
        $this->assertStringContainsString('Sucursal: URDESA', $body);
        $this->assertStringContainsString('Dirección: Av. Siempre Viva 123', $body);
        $this->assertStringContainsString('Recibe: Gregorio Osorio', $body);
        $this->assertStringContainsString('https://app.dpikeos.com/orden/1/pdf', $body);
    }

    public function test_the_confirmation_ticket_shows_pending_shipping_when_the_fee_is_not_calculated_yet(): void
    {
        [, $branch, $contact, $product] = $this->fixture();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 10,
            'metadata' => ['source' => 'bulk_web_form', 'pickup_mode' => 'delivery', 'delivery_fee_pending_review' => true],
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => 'Combo 1', 'price' => 10, 'quantity' => 1]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $body = $service->buildOrderConfirmationPayload($cart, 'https://app.dpikeos.com/orden/2/pdf')['interactive']['body']['text'];

        $this->assertStringContainsString('🚚 Envío: Por confirmar', $body);
    }

    public function test_confirming_an_order_by_transfer_shows_the_total_and_the_bank_block_together(): void
    {
        [, $branch, $contact, $product] = $this->fixture();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => 'active', 'total' => 15,
            'payment_method' => 'transferencia',
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => 'Combo', 'price' => 15, 'quantity' => 1]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $response = $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);
        // Sin el paso de flujo "Comprobante de pago" configurado, este
        // carrito no exige comprobante -- el bloque de banco/total ya se
        // arma igual (es lo que se está probando), solo cambia el cierre.
        $body = $response['interactive']['body']['text'];

        $this->assertStringContainsString('¡Pedido confirmado!', $body);
        $this->assertStringContainsString('💳 *Total a pagar: USD 15.00*', $body);
        $this->assertStringContainsString('Banco Pichincha', $body);
        $this->assertStringContainsString('⚠️ *Importante*', $body);
    }

    public function test_the_proof_pending_closing_message_asks_for_the_screenshot(): void
    {
        $service = app(WhatsappService::class);
        $body = $this->invoke($service, 'renderPaymentTemplate', ['proof_pending', []]);

        $this->assertStringContainsString('envía aquí la captura o comprobante', $body);
        $this->assertStringContainsString('pendiente de verificación', $body);
    }

    public function test_the_message_template_migration_updated_the_stored_ticket_body(): void
    {
        $template = MessageTemplate::where('key', 'order_confirmation_ticket')->firstOrFail();

        $this->assertStringContainsString('{{shipping_line}}', $template->body);
        $this->assertStringContainsString('{{fulfillment}}', $template->body);
        $this->assertContains('shipping_line', $template->placeholders);
        $this->assertContains('fulfillment', $template->placeholders);
    }

    /**
     * Pedido explícito en vivo: si el envío todavía no se confirma, el total
     * no puede aparecer como un número fijo -- se marcaría un monto que
     * después cambia, justo lo que buildCostBreakdownText() ya evitaba para
     * el desglose completo. Debe pasar lo mismo con el {{total}} suelto.
     */
    public function test_the_total_is_marked_as_pending_when_shipping_is_still_unconfirmed(): void
    {
        [, $branch, $contact, $product] = $this->fixture();
        // order_review no muestra {{total}} en su texto por defecto a
        // propósito (ver PaymentMessageTemplates::order_review) -- se
        // comprueba el cálculo directo en vez de esperar que aparezca ahí.
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => 'active', 'total' => 15,
            'payment_method' => 'transferencia',
            'metadata' => ['pickup_mode' => 'delivery', 'delivery_fee_pending_review' => true],
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => 'Combo', 'price' => 15, 'quantity' => 1]);
        $this->assertTrue($cart->hasPendingFulfillmentCosts());

        // El mismo cálculo se usa en order_confirmed (confirmarPedido()) --
        // ahí sí es visible en el texto final que llega al cliente.
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $response = $this->invoke($service, 'confirmarPedido', [$contact, $cart->id]);
        $body = $response['interactive']['body']['text'] ?? $response['text']['body'];

        $this->assertStringContainsString('15.00 + envío (por confirmar)', $body);
    }
}
