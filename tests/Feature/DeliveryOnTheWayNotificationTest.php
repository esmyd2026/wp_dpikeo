<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeliveryConfirmationToken;
use App\Models\DeliveryDriver;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\DeliveryConfirmationService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: avisarle al cliente que su pedido va en camino lo
 * dispara el propio repartidor desde su enlace público de entrega, o la
 * operadora como respaldo manual desde el panel de Pedidos si el
 * repartidor no lo hizo. Ya NO existe un botón para esto en el panel de
 * delivery (asignar/editar repartidor): un operador reportó que el aviso
 * se disparaba justo al asignar el repartidor ahí, confundiendo ese botón
 * con parte del flujo de despacho. El guardián
 * metadata['on_the_way_notified_at'] hace que el aviso solo se mande una
 * vez sin importar cuál de los dos disparadores lo toque primero.
 */
class DeliveryOnTheWayNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart, DeliveryDriver} */
    private function dispatchedDeliveryFixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente',
            'last_inbound_at' => now(),
        ]);
        $driver = DeliveryDriver::create([
            'business_profile_id' => $profile->id, 'first_name' => 'Pedro', 'last_name' => 'Ruiz',
            'phone_number' => '593991234567', 'is_active' => true,
        ]);
        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 12.5,
            'payment_method' => 'efectivo',
            'metadata' => [
                'pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driver->id,
                'order_details' => ['order_number' => 'ORD-900'],
            ],
        ]);

        return [$profile, $contact, $cart, $driver];
    }

    public function test_the_driver_can_notify_the_customer_from_the_public_link(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, , $cart] = $this->dispatchedDeliveryFixture();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $response = $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]));

        $response->assertOk();
        $response->assertJson(['ok' => true, 'already' => false]);
        $this->assertNotNull($cart->fresh()->metadata['on_the_way_notified_at'] ?? null);
        $this->assertSame('driver', $cart->fresh()->metadata['on_the_way_notified_by']);
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'va en camino'));
    }

    public function test_clicking_it_twice_from_the_public_link_only_sends_once(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, , $cart] = $this->dispatchedDeliveryFixture();
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]))->assertOk();
        $firstSentCount = count(Http::recorded());

        $second = $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]));

        $second->assertOk();
        $second->assertJson(['ok' => true, 'already' => true]);
        $this->assertCount($firstSentCount, Http::recorded());
    }

    /** @return array{Company, User} empresa + operadora ya asociada a ella, con permiso orders.update. */
    private function operatorFor(WhatsappBusinessProfile $profile): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Envio', 'slug' => 'empresa-envio-'.Str::random(6), 'status' => 'active']);
        $profile->update(['company_id' => $company->id]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $user];
    }

    /**
     * Pedido explícito: la operadora sigue necesitando un respaldo manual
     * desde el panel de Pedidos por si el repartidor no avisa desde su
     * enlace -- lo que se quitó fue el botón en el panel de delivery
     * (asignar/editar repartidor), no el endpoint en sí.
     */
    public function test_an_operator_can_trigger_the_same_notification_as_a_manual_backup(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, , $cart] = $this->dispatchedDeliveryFixture();
        [$company, $user] = $this->operatorFor($profile);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->postJson("/admin/delivery/{$cart->id}/avisar-en-camino");

        $response->assertOk();
        $response->assertJson(['success' => true, 'already' => false]);
        $this->assertNotNull($cart->fresh()->metadata['on_the_way_notified_at'] ?? null);
        $this->assertSame($user->id, $cart->fresh()->metadata['on_the_way_notified_by']);
    }

    public function test_once_the_driver_notifies_the_operators_manual_backup_reports_already_notified(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, , $cart] = $this->dispatchedDeliveryFixture();

        // El repartidor ya avisó desde el enlace público.
        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();
        $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]))->assertOk();
        $sentAfterDriver = count(Http::recorded());

        [$company, $user] = $this->operatorFor($profile);

        // La operadora intenta el respaldo manual sin saber que ya se avisó.
        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->postJson("/admin/delivery/{$cart->id}/avisar-en-camino");

        $response->assertOk();
        $response->assertJson(['success' => true, 'already' => true]);
        $this->assertCount($sentAfterDriver, Http::recorded());
    }

    public function test_it_fails_clearly_when_no_driver_has_been_dispatched_yet(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, , $cart] = $this->dispatchedDeliveryFixture();
        $metadata = $cart->metadata;
        unset($metadata['last_dispatch_driver_id']);
        $cart->metadata = $metadata;
        $cart->save();

        $url = app(DeliveryConfirmationService::class)->urlFor($cart);
        $token = str($url)->afterLast('/')->toString();

        $response = $this->postJson(route('delivery-confirmation.on-the-way', ['token' => $token]));

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
        Http::assertNothingSent();
    }
}
