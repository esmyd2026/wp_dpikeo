<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: barrer el flujo completo por bugs de la misma familia
 * que el de "reiniciar conversación" con un pedido viejo. Este cubre uno más
 * serio encontrado en la misma revisión: el botón "reiniciar conversación"
 * del panel cancelaba un pedido en "payment_pending" aunque el cliente ya
 * hubiera mandado su comprobante (falta que caja lo verifique) o aunque
 * solo faltara confirmar el costo de envío -- mismo criterio que ya protege
 * al timeout automático (WhatsappCart::isWaitingOnBusiness), pero este botón
 * no lo aplicaba.
 */
class ResetConversationProtectsBusinessPendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, User} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);

        return [$profile, $contact, $user];
    }

    public function test_reset_button_never_cancels_a_payment_pending_order_with_proof_already_submitted(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact, $user] = $this->fixture();

        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 25,
            'payment_method' => 'transferencia',
        ]);
        $order->attachPaymentProof(['message_id' => 'wamid.proof', 'type' => 'image']);

        $response = $this->actingAs($user)->postJson(route('admin.contact.reset-conversation', $contact->id));

        $response->assertOk();
        $response->assertJson(['cart_closed' => false]);
        $this->assertSame(WhatsappCart::STATUS_PAYMENT_PENDING, $order->fresh()->status, 'Un pedido con comprobante ya recibido no debe cancelarse al reiniciar la conversación.');
    }

    public function test_reset_button_still_cancels_a_genuinely_stuck_active_cart(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact, $user] = $this->fixture();

        $stuckCart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.reset-conversation', $contact->id));

        $response->assertOk();
        $response->assertJson(['cart_closed' => true]);
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $stuckCart->fresh()->status);
    }

    public function test_reset_button_clears_lingering_flags_even_when_the_business_pending_order_is_the_only_cart(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $contact, $user] = $this->fixture();

        // El pedido protegido de arriba no se toca, pero otro pedido viejo
        // en "pending" (fuera del alcance del botón) sí debe perder su
        // bandera de "esperando texto".
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 25,
            'payment_method' => 'transferencia',
        ]);
        $order->attachPaymentProof(['message_id' => 'wamid.proof', 'type' => 'image']);

        $staleOrder = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 10,
            'metadata' => ['awaiting_delivery_recipient_name' => true],
        ]);

        $this->actingAs($user)->postJson(route('admin.contact.reset-conversation', $contact->id))->assertOk();

        $this->assertArrayNotHasKey('awaiting_delivery_recipient_name', $staleOrder->fresh()->metadata ?? []);
        $this->assertSame(WhatsappCart::STATUS_PENDING, $staleOrder->fresh()->status);
    }
}
