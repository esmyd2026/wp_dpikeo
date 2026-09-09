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
 * Pedido explícito en vivo: mientras se espera el comprobante, escribir
 * cualquier cosa que no fuera "cancelar" ("cuentas bancarias", "informacion",
 * etc.) solo repetía el recordatorio con un único botón de "Cancelar
 * pedido" -- sin salida si el cliente no quería cancelar. Se agregan los
 * botones de "Hablar con asesor" y "Menú principal" para que tenga más
 * opciones.
 */
class ProofReminderOffersMoreOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 10,
            'payment_method' => 'transferencia',
        ]);
        $cart->markAwaitingPaymentProof();

        return [$profile, $contact, $cart];
    }

    public function test_unrecognized_text_while_awaiting_proof_offers_agent_and_main_menu_buttons(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact, $cart] = $this->fixture();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleTextMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'text' => ['body' => 'cuentas bancarias'],
        ]]);

        Http::assertSent(function ($request) use ($cart) {
            $buttonIds = collect($request['interactive']['action']['buttons'] ?? [])->pluck('reply.id')->all();

            return in_array('cancelar_pedido_'.$cart->id, $buttonIds, true)
                && in_array('agent', $buttonIds, true)
                && in_array('menu_principal', $buttonIds, true);
        });
    }

    public function test_tapping_agent_while_awaiting_proof_still_escalates_to_a_human(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact, $cart] = $this->fixture();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => ['id' => 'agent', 'title' => '💬 Hablar con asesor'],
            ],
        ]]);

        $this->assertFalse($contact->fresh()->bot_enabled);
    }
}
