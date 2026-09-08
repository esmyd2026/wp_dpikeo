<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
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
 * Pedido explícito: al elegir transferencia como método de pago, el bot debe
 * avisar que solo se aceptan transferencias inmediatas -- si los datos
 * quedan mal puestos y el pago no se acredita al instante, el pedido no se
 * puede despachar.
 */
class TransferenciaImmediateNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{WhatsappContact, WhatsappCart} */
    private function fixture(?string $bankInstructions = 'Banco Pichincha, cta. ahorros 123, Dpikeos'): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['bank_transfer_instructions' => $bankInstructions],
        ]);
        $branch = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Matriz', 'code' => 'M1', 'is_default' => true, 'is_active' => true]);
        $menu = WhatsappMenu::create(['business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'Menú', 'action_id' => 'prices_menu']);
        $category = WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test']);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'TEST-1',
            'name' => 'Combo de prueba', 'price' => 5.99, 'currency' => 'USD', 'is_active' => true, 'stock' => 10,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => 'active', 'total' => $product->price]);
        $cart->items()->create(['whatsapp_price_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]);

        return [$contact, $cart];
    }

    public function test_choosing_transferencia_shows_the_immediate_transfer_notice_and_bank_details(): void
    {
        [$contact, $cart] = $this->fixture();
        $service = new WhatsappService();

        $response = $this->invoke($service, 'procesarPagoTransferencia', [$contact, $cart->id]);
        $body = mb_strtolower($response['interactive']['body']['text']);

        $this->assertStringContainsString('transferencias inmediatas', $body);
        $this->assertStringContainsString('banco pichincha', $body);
    }

    public function test_no_bank_block_is_shown_when_no_instructions_are_configured_but_the_warning_still_shows(): void
    {
        [$contact, $cart] = $this->fixture(bankInstructions: null);
        $service = new WhatsappService();

        $response = $this->invoke($service, 'procesarPagoTransferencia', [$contact, $cart->id]);
        $body = mb_strtolower($response['interactive']['body']['text']);

        $this->assertStringContainsString('transferencias inmediatas', $body);
        $this->assertStringNotContainsString('datos para tu transferencia', $body);
    }
}
