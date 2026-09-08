<?php

namespace Tests\Feature;

use App\Models\BulkOrderToken;
use App\Models\BusinessBranch;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\BulkOrderService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: "Debemos pedir la ubicación del envío cuando se arme el
 * pedido por la opción de armar tu lista. pero que lo haga el bot." -- el
 * formulario web ("Armar lista") nunca preguntaba para llevar/servir ni
 * retiro/delivery, así que un pedido de delivery armado por ese medio nunca
 * dejaba ninguna dirección guardada. Ahora, al enviar el formulario, el bot
 * le pregunta esa info por WhatsApp (mismos pasos que un pedido armado por
 * chat) antes de mandarle la confirmación final.
 */
class BulkOrderAsksDeliveryLocationTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{WhatsappPrice, WhatsappContact} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Principal', 'code' => 'PRINCIPAL',
            'is_default' => true, 'is_active' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'BULK-TEST',
            'name' => 'Box Tender', 'price' => 4.50, 'currency' => 'USD', 'is_active' => true, 'stock' => 20,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$product, $contact];
    }

    private function submitBulkOrder(WhatsappPrice $product, WhatsappContact $contact): WhatsappCart
    {
        $token = BulkOrderToken::create([
            'contact_id' => $contact->id, 'token' => BulkOrderToken::generateToken(), 'expires_at' => now()->addHour(),
        ]);

        return app(BulkOrderService::class)->submitFromForm($token, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
    }

    public function test_submitting_the_form_asks_service_type_instead_of_sending_the_confirmation_right_away(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$product, $contact] = $this->fixture();
        $cart = $this->submitBulkOrder($product, $contact);

        app(BulkOrderService::class)->notifyContactViaWhatsapp($cart->fresh());

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'para llevar o para servir'));
        Http::assertNotSent(fn ($r) => ($r->data()['type'] ?? null) === 'document');
    }

    public function test_full_gate_walks_through_service_type_pickup_mode_and_location_before_confirming(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$product, $contact] = $this->fixture();
        $cart = $this->submitBulkOrder($product, $contact);

        // El operador escaneó service_type/pickup_mode a mano en lugar de
        // mockear el mensaje del bot; se prueba cada paso invocando
        // directamente los mismos handlers que usa el botón real.
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $step1 = $this->invoke($service, 'setTipoServicio', [$contact, $cart->id, 'llevar']);
        $this->assertNull($step1, 'El paso intermedio manda el mensaje directo y no debe devolver nada más para reenviar.');
        Http::assertSent(fn ($r) => str_contains(mb_strtolower(json_encode($r->data())), 'retiras en el local'));

        $step2 = $this->invoke($service, 'setModoRetiro', [$contact, $cart->id, 'delivery']);
        $this->assertNull($step2);
        Http::assertSent(fn ($r) => ($r->data()['interactive']['type'] ?? null) === 'location_request_message');

        // El cliente comparte ubicación real -> se le pide el nombre de quien recibe.
        $this->invoke($service, 'handleLocationMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.loc.'.uniqid(),
            'location' => ['latitude' => -2.15, 'longitude' => -79.90],
            'timestamp' => (string) now()->timestamp,
        ]]);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'recibimos el pedido'));

        // Al confirmar el nombre, el gate ya está completo -> debe mandar la
        // confirmación final (documento/PDF, o su respaldo en texto si el
        // PDF no se pudo generar en este entorno de prueba), no volver a
        // preguntar nada más del checkout.
        $cart->refresh();
        $result = $this->invoke($service, 'applyDeliveryRecipientName', [$contact, $cart, 'Gregorio Osorio']);

        $this->assertNull($result);
        $this->assertSame('delivery', $cart->fresh()->metadata['pickup_mode']);
        $this->assertSame('llevar', $cart->fresh()->metadata['service_type']);
        $this->assertNotNull($cart->fresh()->metadata['delivery_location']);
        Http::assertSent(fn ($r) => ($r->data()['type'] ?? null) === 'document'
            || str_contains(json_encode($r->data()), 'Pedido registrado'));
    }

    public function test_admin_created_orders_are_not_asked_anything_new(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$product, $contact] = $this->fixture();

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => $product->price,
            'metadata' => ['source' => 'admin_web_form'],
        ]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        app(BulkOrderService::class)->notifyContactViaWhatsapp($cart);

        Http::assertNotSent(fn ($r) => str_contains(json_encode($r->data()), 'para llevar o para servir'));
        Http::assertSent(fn ($r) => ($r->data()['type'] ?? null) === 'document'
            || str_contains(json_encode($r->data()), 'Pedido registrado'));
    }
}
