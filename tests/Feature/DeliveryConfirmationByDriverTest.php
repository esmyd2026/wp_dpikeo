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

    /**
     * Pedido explícito: "agrégame aquí el detalle del pedido para que el
     * motorizado sepa que está llevando... y adicional el desglose de los
     * valores, el total del pedido y el costo del envío, adicional el
     * número del cliente que está pidiendo."
     */
    public function test_driver_sees_the_order_items_price_breakdown_and_customer_phone(): void
    {
        $cart = $this->readyDeliveryCart();
        $cart->metadata = array_merge($cart->metadata, ['delivery_fee' => 2.50]);
        $cart->total = 14.99;
        $cart->save();
        $profileId = $cart->contact->business_profile_id;
        $menu = \App\Models\WhatsappMenu::create(['business_profile_id' => $profileId, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu']);
        $category = \App\Models\WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $profileId, 'title' => 'Boxes', 'action_id' => 'boxes', 'is_active' => true]);
        $priceA = \App\Models\WhatsappPrice::create(['menu_item_id' => $category->id, 'business_profile_id' => $profileId, 'category' => 'Boxes', 'sku' => 'BOX-1', 'name' => 'Box Tender', 'price' => 7.49, 'currency' => 'USD', 'is_active' => true, 'stock' => 5]);
        $priceB = \App\Models\WhatsappPrice::create(['menu_item_id' => $category->id, 'business_profile_id' => $profileId, 'category' => 'Boxes', 'sku' => 'GAS-1', 'name' => 'Gaseosa 1L', 'price' => 2.50, 'currency' => 'USD', 'is_active' => true, 'stock' => 5]);
        $cart->items()->create(['whatsapp_price_id' => $priceA->id, 'name' => 'Box Tender', 'price' => 7.49, 'quantity' => 1]);
        $cart->items()->create(['whatsapp_price_id' => $priceB->id, 'name' => 'Gaseosa 1L', 'price' => 2.50, 'quantity' => 2]);

        $url = app(DeliveryConfirmationService::class)->urlFor($cart);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Box Tender');
        $response->assertSee('Gaseosa 1L');
        $response->assertSee('593990000002'); // teléfono del cliente
        $response->assertSee('12.49'); // subtotal (14.99 - 2.50 de envío)
        $response->assertSee('2.50'); // envío
        $response->assertSee('14.99'); // total
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

    public function test_a_photo_rejected_by_the_server_returns_the_upload_error_in_spanish(): void
    {
        Storage::fake('public');
        $cart = $this->readyDeliveryCart();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();
        $source = UploadedFile::fake()->image('proof.jpg');
        $failedUpload = new UploadedFile(
            $source->getPathname(),
            'proof.jpg',
            'image/jpeg',
            UPLOAD_ERR_INI_SIZE,
            true
        );

        $response = $this->postJson(route('delivery-confirmation.confirm', ['token' => $token]), [
            'photo' => $failedUpload,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('photo')
            ->assertJsonPath(
                'errors.photo.0',
                'La foto no pudo subir al servidor. Intenta tomarla nuevamente o selecciona una imagen más liviana.'
            );
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
