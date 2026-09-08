<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\BusinessBranch;
use App\Models\BusinessFaq;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: "Información" del bot debe mostrar sucursales (todas las
 * activas, reciban o no pedidos -- ver BusinessBranch::scopeAvailableForOrders)
 * y preguntas frecuentes, sin importar si la empresa usa el menú legado
 * (WhatsappMenu) o el editor visual de flujo (MarketingFlowStep) -- ambos
 * caminos de getInfoMenu() deben recibir la sección nueva (mismo patrón que
 * getMainMenu() ya usa para agregar "Catálogo WhatsApp").
 */
class InfoMenuBranchesAndFaqTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        return [$profile, $contact];
    }

    private function service(WhatsappBusinessProfile $profile): WhatsappService
    {
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        return $service;
    }

    public function test_more_info_section_is_null_without_branches_or_faqs(): void
    {
        [$profile] = $this->fixture();
        $service = $this->service($profile);

        $this->assertNull($this->invoke($service, 'buildMoreInfoSection'));
    }

    public function test_more_info_section_only_lists_whats_configured(): void
    {
        [$profile] = $this->fixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sucursal Centro', 'code' => 'CENTRO', 'is_active' => true]);

        $service = $this->service($profile);
        $section = $this->invoke($service, 'buildMoreInfoSection');

        $this->assertNotNull($section);
        $ids = collect($section['rows'])->pluck('id')->all();
        $this->assertSame(['info_sucursales'], $ids);
    }

    public function test_more_info_section_includes_both_rows_when_both_exist(): void
    {
        [$profile] = $this->fixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sucursal Centro', 'code' => 'CENTRO', 'is_active' => true]);
        BusinessFaq::create(['business_profile_id' => $profile->id, 'question' => '¿Puedo cancelar?', 'answer' => 'Sí, sin costo.', 'is_active' => true]);

        $service = $this->service($profile);
        $section = $this->invoke($service, 'buildMoreInfoSection');

        $ids = collect($section['rows'])->pluck('id')->all();
        $this->assertSame(['info_sucursales', 'info_faq'], $ids);
    }

    public function test_legacy_menu_path_of_get_info_menu_appends_more_info_section(): void
    {
        [$profile] = $this->fixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sucursal Centro', 'code' => 'CENTRO', 'is_active' => true]);
        BusinessFaq::create(['business_profile_id' => $profile->id, 'question' => '¿Puedo cancelar?', 'answer' => 'Sí, sin costo.', 'is_active' => true]);
        WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Información', 'type' => 'list',
            'content' => 'Información', 'button_text' => 'Ver opciones', 'action_id' => 'info_menu',
            'metadata' => ['sections' => [['title' => 'Ayuda', 'rows' => [['id' => 'soporte_legacy', 'title' => 'Soporte']]]]],
        ]);

        $service = $this->service($profile);
        $payload = $this->invoke($service, 'getInfoMenu');

        $sections = $payload['interactive']['action']['sections'];
        $lastSection = end($sections);
        $this->assertSame('Más información', $lastSection['title']);
        // El camino legado agrega además un "Volver al menú" al final de cada sección.
        $this->assertSame(['info_sucursales', 'info_faq', 'return_to_menu'], collect($lastSection['rows'])->pluck('id')->all());
    }

    public function test_flow_based_path_of_get_info_menu_also_appends_more_info_section(): void
    {
        [$profile] = $this->fixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Sucursal Centro', 'code' => 'CENTRO', 'is_active' => true]);
        BusinessFaq::create(['business_profile_id' => $profile->id, 'question' => '¿Puedo cancelar?', 'answer' => 'Sí, sin costo.', 'is_active' => true]);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::INFO_MENU,
            'name' => 'Información y ayuda',
            'message_template' => '¿En qué te ayudamos?',
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'list',
                'list' => ['button' => 'Ver opciones', 'sections' => [
                    ['title' => 'Ayuda', 'rows' => [['id' => 'soporte_flujo', 'title' => 'Soporte']]],
                ]],
            ],
        ]);

        $service = $this->service($profile);
        $payload = $this->invoke($service, 'getInfoMenu');

        $sections = $payload['interactive']['action']['sections'];
        $lastSection = end($sections);
        $this->assertSame('Más información', $lastSection['title']);
        $this->assertSame(['info_sucursales', 'info_faq'], collect($lastSection['rows'])->pluck('id')->all());
    }

    public function test_branches_info_message_lists_active_branches_regardless_of_orders_enabled(): void
    {
        [$profile] = $this->fixture();
        BusinessBranch::create([
            'business_profile_id' => $profile->id, 'name' => 'Sucursal Sur', 'code' => 'SUR', 'address' => 'Av. Siempre Viva 123',
            'phone' => '0999999999', 'reservations_info' => 'Llama con 1 día de anticipación.',
            'is_active' => true, 'orders_enabled' => false,
        ]);

        $service = $this->service($profile);
        $payload = $this->invoke($service, 'buildBranchesInfoMessage');

        $this->assertSame('text', $payload['type']);
        $this->assertStringContainsString('Sucursal Sur', $payload['text']['body']);
        $this->assertStringContainsString('Av. Siempre Viva 123', $payload['text']['body']);
        $this->assertStringContainsString('Llama con 1 día de anticipación.', $payload['text']['body']);
    }

    public function test_faq_list_payload_lists_active_faqs_as_a_list(): void
    {
        [$profile] = $this->fixture();
        $faq = BusinessFaq::create(['business_profile_id' => $profile->id, 'question' => '¿Puedo cancelar mi pedido?', 'answer' => 'Sí.', 'is_active' => true]);
        BusinessFaq::create(['business_profile_id' => $profile->id, 'question' => 'Inactiva', 'answer' => 'x', 'is_active' => false]);

        $service = $this->service($profile);
        $payload = $this->invoke($service, 'buildFaqListPayload');

        $this->assertSame('list', $payload['interactive']['type']);
        $rows = $payload['interactive']['action']['sections'][0]['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('faq_'.$faq->id, $rows[0]['id']);
    }

    public function test_tapping_a_faq_row_sends_its_answer_and_returns_to_the_main_menu(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact] = $this->fixture();
        $faq = BusinessFaq::create(['business_profile_id' => $profile->id, 'question' => '¿Hay devoluciones?', 'answer' => 'Sí, hasta 24h después.', 'is_active' => true]);

        $service = $this->service($profile);
        $response = $this->invoke($service, 'buildFaqAnswerResponse', [$contact, $faq->id]);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/messages')
            && ($request['type'] ?? null) === 'text'
            && str_contains((string) ($request['text']['body'] ?? ''), 'Sí, hasta 24h después.'));

        $this->assertIsArray($response);
    }
}
