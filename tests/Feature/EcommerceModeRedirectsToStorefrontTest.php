<?php

namespace Tests\Feature;

use App\Models\BulkOrderToken;
use App\Models\BusinessBranch;
use App\Models\BusinessBranchHour;
use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: algunas empresas prefieren mandar al cliente al
 * micrositio en vez de armar el pedido dentro de WhatsApp, para evitar el
 * costo por conversación de Meta que genera el diálogo largo de catálogo.
 * Con "ecommerce_mode_enabled" activo, "🛍️ Productos" y "📦 Ver Pedidos"
 * deben mandar el link del micrositio en vez del flujo nativo -- salvo que
 * el cliente ya tenga un carrito nativo en curso, para no sacarlo a mitad de
 * camino. Con el toggle apagado (default), cero cambio de comportamiento.
 */
class EcommerceModeRedirectsToStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{Company, WhatsappBusinessProfile, WhatsappContact} */
    private function fixture(string $suffix, bool $ecommerceMode, bool $storefrontEnabled = true): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Ecommerce', 'slug' => 'empresa-ecommerce-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593992'.$suffix, 'phone_number_id' => 'PHONE-ECOM-'.$suffix,
            'whatsapp_business_id' => 'business-'.$suffix, 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        CompanyStorefrontSetting::create(['company_id' => $company->id, 'storefront_enabled' => $storefrontEnabled]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['ecommerce_mode_enabled' => $ecommerceMode],
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593993'.$suffix, 'name' => 'Cliente']);

        return [$company, $profile, $contact];
    }

    private function pressButton(string $phoneNumberId, WhatsappContact $contact, string $buttonId): void
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($phoneNumberId);

        $this->invoke($service, 'handleInteractiveMessage', [[
            'from' => $contact->phone_number,
            'id' => 'wamid.'.uniqid(),
            'interactive' => [
                'type' => 'button_reply',
                'button_reply' => ['id' => $buttonId, 'title' => 'x'],
            ],
        ]]);
    }

    public function test_ecommerce_mode_off_leaves_the_native_products_menu_untouched(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $profile, $contact] = $this->fixture('100001', ecommerceMode: false);

        $this->pressButton($profile->phone_number_id, $contact, 'menu_productos');

        Http::assertNotSent(fn ($request) => ($request['interactive']['action']['name'] ?? null) === 'cta_url');
    }

    public function test_ecommerce_mode_on_sends_the_storefront_link_instead_of_the_native_catalog(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$company, $profile, $contact] = $this->fixture('100002', ecommerceMode: true);

        $this->pressButton($profile->phone_number_id, $contact, 'menu_productos');

        Http::assertSent(function ($request) use ($contact) {
            $url = $request['interactive']['action']['parameters']['url'] ?? '';

            return ($request['interactive']['action']['name'] ?? null) === 'cta_url'
                && str_contains($url, '/pedido/')
                && ! str_contains($url, $contact->phone_number)
                && ! str_contains($url, 'cuenta=pedidos');
        });
    }

    public function test_the_bot_does_not_start_a_new_order_when_all_branches_are_closed(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $profile, $contact] = $this->fixture('100010', ecommerceMode: true);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id,
            'name' => 'Urdesa',
            'code' => 'URD-CLOSED',
            'is_active' => true,
            'orders_enabled' => true,
        ]);
        BusinessBranchHour::create([
            'business_branch_id' => $branch->id,
            'day_of_week' => 1,
            'opens_at' => '10:00',
            'closes_at' => '20:00',
            'is_closed' => false,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:00', config('app.timezone')));

        try {
            $this->pressButton($profile->phone_number_id, $contact, 'menu_productos');
        } finally {
            Carbon::setTestNow();
        }

        Http::assertSent(fn ($request) => str_contains(
            (string) ($request['text']['body'] ?? ''),
            'Estamos fuera de horario'
        ));
        Http::assertNotSent(fn ($request) => ($request['interactive']['action']['name'] ?? null) === 'cta_url');
        $this->assertDatabaseCount('bulk_order_tokens', 0);
    }

    /**
     * Bug real reportado en vivo: "Armar lista" solía preguntar el método de
     * pago ANTES de mandar a cualquier lado (así, si elegía tarjeta, el
     * micrositio ya sabe redirigir al link externo en vez de cobrar ahí) --
     * pero quedó después del redirect al storefront en el código, así que el
     * gate nunca se disparaba para ninguna empresa con storefront publicado.
     */
    public function test_armar_lista_asks_payment_method_first_then_uses_the_modern_storefront(): void
    {
        [$company, $profile, $contact] = $this->fixture('100007', ecommerceMode: true);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $gateResponse = $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $this->assertSame('list', $gateResponse['interactive']['type']);
        $this->assertStringContainsString('método de pago', $gateResponse['interactive']['body']['text']);
        $this->assertDatabaseCount('bulk_order_tokens', 0);

        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $response = $this->invoke($service, 'procesarPagoEfectivo', [$contact, $cart->id]);
        $url = $response['interactive']['action']['parameters']['url'] ?? '';

        $this->assertSame('cta_url', $response['interactive']['type']);
        $this->assertStringContainsString('/pedido/', $url);
        $this->assertStringNotContainsString($contact->phone_number, $url);
        // Ya eligió efectivo en el chat -- el micrositio no debe preguntarlo
        // de nuevo apenas llegue.
        $this->assertStringContainsString('payment_method=efectivo', $url);
        $this->assertDatabaseCount('bulk_order_tokens', 1);
    }

    /**
     * Pedido explícito en vivo: "cuando es pago con tarjeta es para la
     * página dpikeos.ec y cuando es efectivo y transferencia es para el de
     * dpikeos.com" -- dpikeos.ec es un ecommerce aparte, con su propio
     * catálogo y carrito (el cliente arma y paga TODO allá), así que tarjeta
     * nunca debe pasar por el micrositio propio, ni siquiera con el carrito
     * de "Armar lista" todavía vacío. Antes SÍ lo mandaba al micrositio acá
     * (razonando que no había un total real que cobrar) -- eso quedó mal
     * una vez que dpikeos.ec no depende de nuestro carrito ni de un total
     * nuestro.
     */
    public function test_choosing_card_for_armar_lista_sends_the_external_card_link_directly(): void
    {
        [, $profile, $contact] = $this->fixture('100010', ecommerceMode: true);
        WhatsappChatbotConfig::where('business_profile_id', $profile->id)
            ->update(['metadata' => ['ecommerce_mode_enabled' => true, 'card_payment_url' => 'https://dpikeos.ec/']]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();

        $response = $this->invoke($service, 'procesarPagoTarjeta', [$contact, $cart->id]);

        $this->assertSame('cta_url', $response['interactive']['type']);
        $this->assertSame('https://dpikeos.ec/', $response['interactive']['action']['parameters']['url'] ?? null);
        $this->assertStringNotContainsString('/pedido/', $response['interactive']['action']['parameters']['url'] ?? '');
        $this->assertSame('tarjeta', $cart->fresh()->payment_method);
        $this->assertSame(WhatsappCart::STATUS_CANCELLED, $cart->fresh()->status);
    }

    /**
     * El enlace que manda el bot cuando el cliente ya eligió cómo pagar debe
     * llegar hasta la página del micrositio sin que le vuelva a preguntar --
     * cadena completa: WhatsappService arma la URL -> BulkOrderController la
     * reenvía en el redirect -> StorefrontController la deja lista para el
     * JS de la página (ver storefrontOrderDefaults en storefront/show.blade.php).
     */
    public function test_the_payment_method_already_chosen_in_the_chat_reaches_the_storefront_page(): void
    {
        [$company, $profile, $contact] = $this->fixture('100011', ecommerceMode: true);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $this->invoke($service, 'sendBulkWebOrderLink', [$contact]);
        $cart = WhatsappCart::where('contact_id', $contact->id)->where('status', 'active')->firstOrFail();
        $response = $this->invoke($service, 'procesarPagoEfectivo', [$contact, $cart->id]);
        $url = $response['interactive']['action']['parameters']['url'] ?? '';
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $page = $this->get($path);
        $page->assertStatus(302);
        $location = $page->headers->get('Location');
        $this->assertStringStartsWith(route('storefront.show', $company), $location);
        $this->assertStringContainsString('payment_method=efectivo', $location);
        $page = $this->get($location);

        $page->assertOk();
        $page->assertSee('window.storefrontOrder.payment_method="efectivo"', false);
    }

    public function test_an_old_bulk_order_link_redirects_to_the_published_storefront(): void
    {
        [$company, , $contact] = $this->fixture('100008', ecommerceMode: true);
        $token = BulkOrderToken::create([
            'contact_id' => $contact->id,
            'token' => BulkOrderToken::generateToken(),
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('bulk-order.show', $token->token))
            ->assertRedirect(route('storefront.show', $company));
        $this->assertAuthenticatedAs($contact, 'storefront_customer');
        $this->assertNotNull($token->fresh()->used_at);
    }

    public function test_the_public_order_entry_also_redirects_to_the_published_storefront(): void
    {
        [$company] = $this->fixture('100009', ecommerceMode: true);

        $this->get(route('landing.start-order'))
            ->assertRedirect(route('storefront.show', $company));

        $this->assertDatabaseCount('bulk_order_tokens', 0);
    }

    public function test_ecommerce_mode_on_but_storefront_not_enabled_falls_back_to_the_native_catalog(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $profile, $contact] = $this->fixture('100003', ecommerceMode: true, storefrontEnabled: false);

        $this->pressButton($profile->phone_number_id, $contact, 'menu_productos');

        Http::assertNotSent(fn ($request) => ($request['interactive']['action']['name'] ?? null) === 'cta_url');
    }

    public function test_ecommerce_mode_on_does_not_interrupt_a_native_cart_already_in_progress(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $profile, $contact] = $this->fixture('100004', ecommerceMode: true);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'combos', 'is_active' => true,
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'COMBO-1',
            'name' => 'Combo', 'price' => 5, 'currency' => 'USD', 'is_active' => true, 'stock' => 5,
        ]);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 0]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => 'Combo', 'price' => 5, 'quantity' => 1]);

        $this->pressButton($profile->phone_number_id, $contact, 'menu_productos');

        Http::assertNotSent(fn ($request) => ($request['interactive']['action']['name'] ?? null) === 'cta_url');
    }

    public function test_ecommerce_mode_on_redirects_order_tracking_to_the_storefront_account_page(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$company, $profile, $contact] = $this->fixture('100005', ecommerceMode: true);

        $this->pressButton($profile->phone_number_id, $contact, 'menu_pedido');

        Http::assertSent(function ($request) use ($contact) {
            $url = $request['interactive']['action']['parameters']['url'] ?? '';

            return ($request['interactive']['action']['name'] ?? null) === 'cta_url'
                && str_contains($url, '/pedido/')
                && ! str_contains($url, $contact->phone_number)
                && str_contains($url, 'cuenta=pedidos');
        });
    }

    public function test_ecommerce_mode_off_leaves_order_tracking_untouched(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [, $profile, $contact] = $this->fixture('100006', ecommerceMode: false);

        $this->pressButton($profile->phone_number_id, $contact, 'menu_pedido');

        Http::assertNotSent(fn ($request) => ($request['interactive']['action']['name'] ?? null) === 'cta_url');
    }
}
