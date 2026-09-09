<?php

namespace Tests\Feature;

use App\Models\MarketingFlow;
use App\Models\MarketingFlowNode;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: "en pedido multiple no esta considerando la
 * configuracion que tengo en el flujo que lo tengo desabilitado esa opcion
 * de que si es para llevar o servir". Causa: getCheckoutStepConfig() solo
 * leía la versión PUBLICADA del grafo (getPublishedGraphSnapshot()) -- pero
 * el grafo de este negocio está deliberadamente despublicado (ver
 * marketing-flow:unpublish) para que el saludo/menú usen el editor clásico.
 * Como resultado, cualquier ajuste hecho en el nodo "Checkout (sistema)"
 * (pasos desactivados, valores por defecto) dejaba de aplicarse por
 * completo, sin que hubiera ningún equivalente en el editor clásico para
 * configurarlo de otra forma. getCheckoutStepConfig() ahora lee el nodo tal
 * como está guardado (borrador), sin exigir publicación.
 */
class CheckoutStepConfigIgnoresPublishStateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{WhatsappBusinessProfile, WhatsappContact, WhatsappCart} */
    private function fixture(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593987654321', 'name' => 'Cliente', 'status' => 'active']);

        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);

        // Nodo "Checkout (sistema)" configurado desde el editor visual: el
        // paso "¿para llevar o servir?" está desactivado, con "servir" como
        // valor por defecto. Deliberadamente NO se crea ningún
        // MarketingFlowVersion con is_current=true -- el grafo sigue
        // despublicado, tal como lo dejó el usuario.
        MarketingFlowNode::create([
            'flow_id' => $flow->id,
            'node_uuid' => 'checkout-node-uuid',
            'node_type' => MarketingFlowNode::TYPE_CHECKOUT,
            'name' => 'Checkout (sistema)',
            'config' => [
                'steps' => [
                    'service_type' => ['enabled' => false, 'default' => 'servir'],
                ],
            ],
            'is_enabled' => true,
        ]);

        $cart = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PENDING, 'total' => 18.5,
            'metadata' => ['source' => 'bulk_web_form'],
        ]);

        return [$profile, $contact, $cart];
    }

    public function test_disabled_service_type_step_is_skipped_and_uses_the_configured_default_even_without_a_published_graph(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        [$profile, $contact, $cart] = $this->fixture();

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        $askedSomething = $service->askNextBulkOrderFulfillmentStep($contact, $cart);

        $this->assertFalse($askedSomething, 'No debería preguntar "para llevar o servir" -- el paso está desactivado.');
        $this->assertSame('servir', $cart->fresh()->metadata['service_type']);
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'tipo_llevar_')
            || str_contains(json_encode($request->data()), 'tipo_servir_'));
    }
}
