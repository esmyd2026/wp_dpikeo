<?php

namespace Tests\Feature;

use App\Models\DeliveryConfirmationToken;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\DeliveryConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pedido explícito: "el repartidor puede confirmar la entrega aca? porque ya
 * fue enviado pero como sabemos que ya el cliente recibió" -- resuelto con un
 * link público (sin usuario del panel) que se manda por WhatsApp al
 * despachar, para que el repartidor mismo suba la foto de entrega desde su
 * celular.
 */
class DeliveryConfirmationByDriverTest extends TestCase
{
    use RefreshDatabase;

    private function readyDeliveryCart(): WhatsappCart
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);

        return WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_READY,
            'total' => 12.50,
            'payment_method' => 'efectivo',
            'metadata' => [
                'pickup_mode' => 'delivery',
                'delivery_recipient_name' => 'Juan Pérez',
                'delivery_location' => ['manual_address' => 'Av. Siempre Viva 123'],
                'inventory_reserved_at' => now()->toIso8601String(),
                'order_details' => ['order_number' => 'ORD-777'],
            ],
        ]);
    }

    public function test_url_for_creates_a_token_and_reuses_it_on_a_second_call(): void
    {
        $cart = $this->readyDeliveryCart();
        $service = app(DeliveryConfirmationService::class);

        $url1 = $service->urlFor($cart);
        $url2 = $service->urlFor($cart);

        $this->assertSame($url1, $url2);
        $this->assertSame(1, DeliveryConfirmationToken::where('whatsapp_cart_id', $cart->id)->count());
    }

    public function test_driver_can_open_the_public_link_and_see_the_order_summary(): void
    {
        $cart = $this->readyDeliveryCart();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('ORD-777');
        $response->assertSee('Juan Pérez');
        $response->assertSee('Av. Siempre Viva 123');
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get(route('delivery-confirmation.show', ['token' => 'does-not-exist']))->assertNotFound();
    }

    public function test_driver_confirming_delivery_uploads_proof_and_completes_the_order(): void
    {
        Storage::fake('public');
        $cart = $this->readyDeliveryCart();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $response = $this->post(route('delivery-confirmation.confirm', ['token' => $token]), [
            'photo' => UploadedFile::fake()->image('proof.jpg'),
            'note' => 'Entregado en portería.',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $cart->refresh();
        $this->assertSame(WhatsappCart::STATUS_COMPLETED, $cart->status);
        $this->assertSame('Entregado en portería.', $cart->metadata['delivery_proof']['note']);
        $this->assertSame('driver', $cart->metadata['delivery_proof']['confirmed_by']);

        $this->assertTrue(DeliveryConfirmationToken::where('token', $token)->first()->used_at !== null);
    }

    public function test_an_oversized_delivery_photo_returns_a_clear_spanish_error(): void
    {
        Storage::fake('public');
        $cart = $this->readyDeliveryCart();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $response = $this->postJson(route('delivery-confirmation.confirm', ['token' => $token]), [
            'photo' => UploadedFile::fake()->image('proof.jpg')->size(8193),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('photo')
            ->assertJsonPath('errors.photo.0', 'La foto es demasiado pesada. El máximo permitido es 8 MB.');
        $this->assertSame(WhatsappCart::STATUS_READY, $cart->fresh()->status);
    }

    public function test_a_used_token_cannot_confirm_again(): void
    {
        Storage::fake('public');
        $cart = $this->readyDeliveryCart();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $this->post(route('delivery-confirmation.confirm', ['token' => $token]), [
            'photo' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertOk();

        $second = $this->post(route('delivery-confirmation.confirm', ['token' => $token]), [
            'photo' => UploadedFile::fake()->image('proof2.jpg'),
        ]);

        $second->assertStatus(422);
        $second->assertJson(['ok' => false]);
    }

    public function test_used_token_shows_a_friendly_page_instead_of_the_form(): void
    {
        $cart = $this->readyDeliveryCart();
        $token = DeliveryConfirmationToken::create([
            'whatsapp_cart_id' => $cart->id,
            'token' => DeliveryConfirmationToken::generateToken(),
            'expires_at' => now()->addHours(1),
            'used_at' => now(),
        ]);

        $response = $this->get(route('delivery-confirmation.show', ['token' => $token->token]));

        $response->assertOk();
        $response->assertSee('ya fue confirmada');
        $response->assertDontSee('id="confirmForm"', false);
    }
}
