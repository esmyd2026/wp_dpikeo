<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: "si tiene un pedido activo que le indique que
 * tiene el pedido y el estado actual y le dé la opción de cancelar en caso de
 * que sea posible... siempre y cuando esté sin pagar el pedido". Cubre que
 * cualquier saludo/texto libre mientras hay un pedido YA ENVIADO (no un
 * carrito en curso, eso lo cubre cartHasPendingCheckoutStep) lo mantenga
 * "anclado" a ese pedido en vez de caer al menú genérico.
 */
class ActiveOrderStatusSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function profileAndContact(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return [$profile, $contact];
    }

    /** handleTextMessage() no retorna nada -- manda el mensaje directo, hay que interceptarlo por Http::fake(). */
    private function greet(WhatsappContact $contact): void
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'text' => ['body' => 'hola'],
        ]]);
    }

    private function pressButton(WhatsappContact $contact, string $buttonId, string $title): void
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => ['id' => $buttonId, 'title' => $title],
            ],
        ]]);
    }

    public function test_greeting_with_an_unpaid_order_shows_its_status_and_a_cancel_button(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 10]);

        $this->greet($contact);

        Http::assertSent(function ($request) use ($cart) {
            $body = $request['interactive']['body']['text'] ?? '';
            $buttonIds = collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();

            return str_contains($body, $cart->getOrderNumber())
                && str_contains($body, 'Confirmado')
                && in_array('cancelar_pedido_'.$cart->id, $buttonIds, true);
        });
    }

    public function test_product_buttons_cannot_start_another_purchase_while_a_bulk_web_order_is_pending(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_PAYMENT_PENDING,
            'total' => 22,
            'metadata' => ['source' => 'bulk_web_form'],
        ]);

        $this->pressButton($contact, 'menu_productos', 'Ver menú');

        Http::assertSent(function ($request) use ($cart) {
            $body = $request['interactive']['body']['text'] ?? '';
            $buttonIds = collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();

            return str_contains($body, $cart->getOrderNumber())
                && str_contains($body, 'Pago pendiente')
                && in_array('cancelar_pedido_'.$cart->id, $buttonIds, true);
        });
        $this->assertDatabaseMissing('whatsapp_carts', [
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
    }

    public function test_bulk_microsite_link_is_not_generated_while_an_order_is_pending(): void
    {
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_PENDING,
            'total' => 18,
            'metadata' => ['source' => 'bulk_web_form'],
        ]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');

        $response = $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);

        $this->assertStringContainsString($cart->getOrderNumber(), $response['interactive']['body']['text']);
        $this->assertSame('cancelar_pedido_'.$cart->id, $response['interactive']['action']['buttons'][0]['reply']['id']);
        $this->assertDatabaseCount('bulk_order_tokens', 0);
        $this->assertDatabaseMissing('whatsapp_carts', [
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
    }

    /**
     * Pedido explícito en vivo: un pedido "Pagado" está en cancha del
     * negocio, pero cocina todavía no lo tocó -- se cambió el corte de
     * "antes de pagar" a "antes de que empiece a prepararse" (ver
     * WhatsappCart::isCancelableBySelfService).
     */
    public function test_greeting_with_an_already_paid_order_still_shows_a_cancel_button(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAID, 'total' => 10]);

        $this->greet($contact);

        Http::assertSent(function ($request) use ($cart) {
            $body = $request['interactive']['body']['text'] ?? '';
            $buttonIds = collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();

            return str_contains($body, $cart->getOrderNumber())
                && in_array('cancelar_pedido_'.$cart->id, $buttonIds, true);
        });
    }

    public function test_pressing_cancel_on_an_already_paid_order_actually_cancels_it(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAID, 'total' => 10]);

        $this->pressButton($contact, 'cancelar_pedido_'.$cart->id, '❌ Cancelar pedido');

        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->fresh()->status);
        $this->assertSame(WhatsappCart::CANCEL_REASON_CUSTOMER, $cart->fresh()->cancellationReason());
    }

    public function test_greeting_without_any_pending_order_does_not_mention_any_order(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();

        $this->greet($contact);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'pedido en curso'));
    }

    public function test_completed_orders_are_never_shown_as_active(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 10]);

        $this->greet($contact);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'pedido en curso'));
    }

    /**
     * Bug real reportado en vivo: un operador pausó "Atención automática"
     * para un contacto específico desde el panel, pero el bot le siguió
     * contestando igual con el estado de su pedido. Causa: el chequeo de
     * bot_enabled estaba dentro de una rama que este mensaje ("hola" con
     * pedido activo) nunca llegaba a pisar.
     */
    public function test_a_greeting_is_never_auto_answered_when_the_bot_is_paused_for_that_contact(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $contact->update(['bot_enabled' => false]);
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 10]);

        $this->greet($contact);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'pedido en curso'));
    }

    /**
     * Bug real reportado en vivo: el cliente mandó su comprobante, el bot
     * confirmó "Comprobante recibido", y justo después el cliente escribió
     * algo trivial ("gracias") -- el bot le repetía innecesariamente la
     * tarjeta completa "Tienes un pedido en curso... Cancelar pedido /
     * Hablar con asesor", como si nada se hubiera resuelto todavía.
     */
    public function test_a_trivial_thanks_after_the_proof_was_already_sent_does_not_repeat_the_full_order_card(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_PAYMENT_PENDING,
            'total' => 10,
            'payment_status' => 'proof_submitted',
            'metadata' => ['payment_proof' => ['message_id' => 'wamid.proof']],
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'text' => ['body' => 'gracias'],
        ]]);

        Http::assertSent(fn ($request) => str_contains($request['text']['body'] ?? '', 'Ya tenemos tu comprobante'));
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'pedido en curso'));
        Http::assertNotSent(fn ($request) => in_array('cancelar_pedido_'.$cart->id, collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all(), true));
    }

    /** Si todavía NO mandó el comprobante, un "gracias" sigue mostrando la tarjeta completa -- no se le puede dejar de recordar que falta ese paso. */
    public function test_a_trivial_thanks_before_sending_the_proof_still_shows_the_full_order_card(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_PAYMENT_PENDING,
            'total' => 10,
            'payment_status' => 'awaiting_proof',
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'text' => ['body' => 'gracias'],
        ]]);

        Http::assertSent(function ($request) use ($cart) {
            $buttonIds = collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();

            return str_contains($request['interactive']['body']['text'] ?? '', $cart->getOrderNumber())
                && in_array('cancelar_pedido_'.$cart->id, $buttonIds, true);
        });
    }

    /** Pedido explícito: un pedido "listo" (ya no cancelable) debe poder escalar a un asesor, no solo volver al menú. */
    public function test_a_ready_order_that_cannot_be_cancelled_still_offers_talking_to_an_agent(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact] = $this->profileAndContact();
        WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 10]);

        $this->greet($contact);

        Http::assertSent(function ($request) {
            $buttonIds = collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();

            return in_array('agent', $buttonIds, true) && in_array('menu_principal', $buttonIds, true);
        });
    }
}
