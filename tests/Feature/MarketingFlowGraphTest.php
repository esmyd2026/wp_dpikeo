<?php

namespace Tests\Feature;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingFlowGraphTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $role = Role::create([
            'slug' => 'super_admin',
            'name' => 'Super Administrador',
            'is_system' => true,
        ]);

        $user = User::factory()->create([
            'is_admin' => true,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function seedFlowWithSteps(): MarketingFlow
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Test Business',
            'display_name' => 'Test Business',
            'phone_number' => '5930000000',
            'access_token' => 'test-token',
        ]);

        $flow = MarketingFlow::create([
            'business_profile_id' => $profile->id,
            'name' => 'Flujo de prueba',
            'is_active' => true,
            'is_default' => true,
        ]);

        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::WELCOME,
            'name' => 'Bienvenida',
            'message_template' => 'Hola {{nombre}}',
            'sort_order' => 0,
            'is_enabled' => true,
            'config' => ['interactive_type' => 'text'],
        ]);

        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::MAIN_MENU,
            'name' => 'Menú principal',
            'message_template' => '¿Qué necesitas?',
            'sort_order' => 1,
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'button',
                'buttons' => [
                    ['id' => 'menu_productos', 'title' => 'Ver menú', 'action' => 'products'],
                ],
            ],
        ]);

        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::PRODUCTS_MENU,
            'name' => 'Catálogo',
            'message_template' => 'Elige una categoría',
            'sort_order' => 2,
            'is_enabled' => true,
            'config' => ['interactive_type' => 'list', 'list' => ['button' => 'Ver el menú', 'sections' => []]],
        ]);

        foreach ([
            MarketingStepKey::ORDERS_MENU,
            MarketingStepKey::INFO_MENU,
            MarketingStepKey::CART_SUMMARY,
            MarketingStepKey::CHECKOUT,
            MarketingStepKey::PAYMENT_PROOF,
            MarketingStepKey::AGENT_HANDOFF,
            MarketingStepKey::FALLBACK_MESSAGE,
        ] as $i => $stepKey) {
            MarketingFlowStep::create([
                'flow_id' => $flow->id,
                'step_key' => $stepKey,
                'name' => MarketingStepKey::all()[$stepKey],
                'message_template' => 'Texto de ' . $stepKey,
                'sort_order' => 3 + $i,
                'is_enabled' => true,
                'config' => ['interactive_type' => 'text'],
            ]);
        }

        return $flow;
    }

    public function test_graph_page_renders_for_authorized_admin(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedFlowWithSteps();

        $response = $this->get(route('admin.marketing-flow.graph.edit'));

        $response->assertOk();
        $response->assertSee('flow-editor-app', false);
    }

    public function test_graph_data_auto_migrates_steps_into_nodes_and_edges(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();

        $this->assertSame(0, $flow->nodes()->count());

        $response = $this->getJson(route('admin.marketing-flow.graph.data'));

        $response->assertOk();
        $json = $response->json();

        // fallback_message no se migra: 10 pasos - 1 = 9 nodos.
        $this->assertCount(9, $json['nodes']);

        $startNodes = array_filter($json['nodes'], fn ($n) => $n['is_start']);
        $this->assertCount(1, $startNodes);
        $this->assertSame('start', array_values($startNodes)[0]['node_type']);

        // El botón "Ver menú" (action=products) del main_menu debe quedar
        // conectado automáticamente al nodo de catálogo.
        $mainMenuNode = collect($json['nodes'])->firstWhere('name', 'Menú principal');
        $catalogNode = collect($json['nodes'])->firstWhere('node_type', 'catalog');
        $this->assertNotNull($mainMenuNode);
        $this->assertNotNull($catalogNode);

        $matchingEdge = collect($json['edges'])->first(
            fn ($e) => $e['source'] === $mainMenuNode['id']
                && $e['source_handle'] === 'menu_productos'
                && $e['target'] === $catalogNode['id']
        );
        $this->assertNotNull($matchingEdge, 'Se esperaba una conexión automática de menu_productos hacia el nodo de catálogo.');

        // Segunda carga: no debe duplicar nodos (el flujo ya tiene nodos).
        $flow->refresh();
        $this->assertSame(9, $flow->nodes()->count());
        $this->get(route('admin.marketing-flow.graph.data'));
        $this->assertSame(9, $flow->fresh()->nodes()->count());
    }

    public function test_unauthorized_user_cannot_view_graph(): void
    {
        $role = Role::create(['slug' => 'agent', 'name' => 'Agente']);
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $this->actingAs($user);

        $response = $this->get(route('admin.marketing-flow.graph.edit'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_can_create_edit_and_delete_a_message_node(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();

        $create = $this->postJson(route('admin.marketing-flow.graph.nodes.store'), [
            'node_type' => 'message',
            'name' => 'Nodo de prueba',
            'message_template' => 'Hola',
            'position' => ['x' => 10, 'y' => 20],
            'config' => ['interactive_type' => 'text'],
        ]);
        $create->assertCreated();
        $uuid = $create->json('id');

        $update = $this->putJson(route('admin.marketing-flow.graph.nodes.update', $uuid), [
            'name' => 'Nodo editado',
            'message_template' => 'Hola de nuevo',
            'is_enabled' => true,
            'config' => ['interactive_type' => 'text'],
        ]);
        $update->assertOk();
        $update->assertJsonPath('name', 'Nodo editado');

        $move = $this->putJson(route('admin.marketing-flow.graph.nodes.update', $uuid), [
            'position' => ['x' => 99, 'y' => 88],
        ]);
        $move->assertOk();
        $this->assertDatabaseHas('marketing_flow_nodes', [
            'flow_id' => $flow->id,
            'node_uuid' => $uuid,
            'position_x' => 99,
            'position_y' => 88,
            'name' => 'Nodo editado',
        ]);

        $delete = $this->deleteJson(route('admin.marketing-flow.graph.nodes.destroy', $uuid));
        $delete->assertOk();
        $this->assertDatabaseMissing('marketing_flow_nodes', ['node_uuid' => $uuid]);
    }

    public function test_list_menu_node_persists_interactive_type_and_rows(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedFlowWithSteps();

        $create = $this->postJson(route('admin.marketing-flow.graph.nodes.store'), [
            'node_type' => 'list_menu',
            'name' => 'Lista de prueba',
            'config' => ['interactive_type' => 'list', 'list' => ['button' => 'Ver opciones', 'sections' => []]],
        ]);
        $create->assertCreated();
        $uuid = $create->json('id');

        $update = $this->putJson(route('admin.marketing-flow.graph.nodes.update', $uuid), [
            'name' => 'Lista de prueba',
            'message_template' => 'Elige una opción',
            'is_enabled' => true,
            'config' => [
                'interactive_type' => 'list',
                'list' => [
                    'button' => 'Ver opciones',
                    'sections' => [['title' => 'Sección', 'rows' => [['id' => 'row_1', 'title' => 'Opción', 'description' => null]]]],
                ],
            ],
        ]);
        $update->assertOk();

        $this->assertSame('list', $update->json('config.interactive_type'));
        $this->assertSame('row_1', $update->json('config.list.sections.0.rows.0.id'));
        $this->assertDatabaseHas('marketing_flow_nodes', ['node_uuid' => $uuid]);

        $fresh = \App\Models\MarketingFlowNode::where('node_uuid', $uuid)->first();
        $this->assertSame('list', $fresh->config['interactive_type']);
    }

    public function test_button_menu_node_cannot_exceed_three_buttons(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedFlowWithSteps();

        $response = $this->postJson(route('admin.marketing-flow.graph.nodes.store'), [
            'node_type' => 'button_menu',
            'name' => 'Demasiados botones',
            'config' => [
                'interactive_type' => 'button',
                'buttons' => [
                    ['id' => 'b1', 'title' => 'Uno'],
                    ['id' => 'b2', 'title' => 'Dos'],
                    ['id' => 'b3', 'title' => 'Tres'],
                    ['id' => 'b4', 'title' => 'Cuatro'],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['config.buttons']);
    }

    public function test_start_node_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        $this->getJson(route('admin.marketing-flow.graph.data'));

        $startNode = $flow->nodes()->where('is_start', true)->firstOrFail();

        $response = $this->deleteJson(route('admin.marketing-flow.graph.nodes.destroy', $startNode->node_uuid));

        $response->assertStatus(422);
        $this->assertDatabaseHas('marketing_flow_nodes', ['node_uuid' => $startNode->node_uuid]);
    }

    public function test_can_connect_and_disconnect_two_nodes(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        $this->getJson(route('admin.marketing-flow.graph.data'));

        $start = $flow->nodes()->where('is_start', true)->firstOrFail();
        $cart = $flow->nodes()->where('node_type', 'cart')->firstOrFail();

        $create = $this->postJson(route('admin.marketing-flow.graph.edges.store'), [
            'source_node_uuid' => $start->node_uuid,
            'source_handle' => 'test_handle',
            'target_node_uuid' => $cart->node_uuid,
        ]);
        $create->assertCreated();
        $edgeId = $create->json('id');

        $this->assertDatabaseHas('marketing_flow_edges', [
            'flow_id' => $flow->id,
            'source_node_uuid' => $start->node_uuid,
            'source_handle' => 'test_handle',
            'target_node_uuid' => $cart->node_uuid,
        ]);

        $this->deleteJson(route('admin.marketing-flow.graph.edges.destroy', $edgeId))->assertOk();
        $this->assertDatabaseMissing('marketing_flow_edges', ['id' => $edgeId]);
    }

    public function test_publish_creates_a_current_version_snapshot(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        $this->getJson(route('admin.marketing-flow.graph.data'));

        $publish = $this->postJson(route('admin.marketing-flow.graph.publish'));
        $publish->assertCreated();
        $publish->assertJsonPath('version_number', 1);

        $this->assertDatabaseHas('marketing_flow_versions', [
            'flow_id' => $flow->id,
            'version_number' => 1,
            'is_current' => 1,
        ]);

        $snapshot = $flow->versions()->where('is_current', true)->first()->snapshot;
        $this->assertNotEmpty($snapshot['start_node_uuid']);
        $this->assertCount(9, $snapshot['nodes']);

        // Publicar de nuevo crea la v2 y deja de marcar la v1 como actual.
        $second = $this->postJson(route('admin.marketing-flow.graph.publish'));
        $second->assertJsonPath('version_number', 2);
        $this->assertDatabaseHas('marketing_flow_versions', ['flow_id' => $flow->id, 'version_number' => 1, 'is_current' => 0]);
        $this->assertDatabaseHas('marketing_flow_versions', ['flow_id' => $flow->id, 'version_number' => 2, 'is_current' => 1]);
    }

    public function test_publish_fails_without_start_node(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        // No llamamos a /graph/data, así que nunca se generó el borrador de nodos.

        $response = $this->postJson(route('admin.marketing-flow.graph.publish'));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('marketing_flow_versions', ['flow_id' => $flow->id]);
    }

    public function test_can_restore_a_previous_version(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        $this->getJson(route('admin.marketing-flow.graph.data'));
        $this->postJson(route('admin.marketing-flow.graph.publish'))->assertJsonPath('version_number', 1);

        // Cambiamos algo y publicamos v2.
        $start = $flow->nodes()->where('is_start', true)->firstOrFail();
        $this->putJson(route('admin.marketing-flow.graph.nodes.update', $start->node_uuid), [
            'name' => 'Bienvenida editada',
            'message_template' => 'Nuevo saludo',
            'is_enabled' => true,
            'config' => ['interactive_type' => 'text'],
        ])->assertOk();
        $this->postJson(route('admin.marketing-flow.graph.publish'))->assertJsonPath('version_number', 2);

        $v1 = $flow->versions()->where('version_number', 1)->firstOrFail();
        $restore = $this->postJson(route('admin.marketing-flow.graph.versions.restore', $v1->id));
        $restore->assertCreated();
        $restore->assertJsonPath('version_number', 3);

        $current = $flow->versions()->where('is_current', true)->first();
        $this->assertSame(3, $current->version_number);
        $this->assertSame('Bienvenida', $current->snapshot['nodes'][$start->node_uuid]['name']);
    }

    public function test_published_graph_is_used_for_greeting_and_button_click(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Test Business',
            'display_name' => 'Test Business',
            'phone_number' => '5930000000',
            'access_token' => 'test-token',
        ]);
        $flow = MarketingFlow::create([
            'business_profile_id' => $profile->id,
            'name' => 'Flujo de prueba',
            'is_active' => true,
            'is_default' => true,
        ]);

        $startUuid = (string) \Illuminate\Support\Str::uuid();
        $targetUuid = (string) \Illuminate\Support\Str::uuid();

        \App\Models\MarketingFlowVersion::create([
            'flow_id' => $flow->id,
            'version_number' => 1,
            'is_current' => true,
            'published_at' => now(),
            'snapshot' => [
                'start_node_uuid' => $startUuid,
                'nodes' => [
                    $startUuid => [
                        'node_uuid' => $startUuid,
                        'node_type' => 'button_menu',
                        'name' => 'Inicio',
                        'message_template' => 'Hola desde el grafo',
                        'config' => [
                            'interactive_type' => 'button',
                            'buttons' => [['id' => 'btn_test', 'title' => 'Probar']],
                        ],
                        'is_enabled' => true,
                    ],
                    $targetUuid => [
                        'node_uuid' => $targetUuid,
                        'node_type' => 'message',
                        'name' => 'Destino',
                        'message_template' => 'Llegaste al nodo destino',
                        'config' => ['interactive_type' => 'text'],
                        'is_enabled' => true,
                    ],
                ],
                'edges' => [
                    $startUuid => ['btn_test' => $targetUuid],
                ],
            ],
        ]);

        $contact = \App\Models\WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593999999999',
            'name' => 'Cliente Prueba',
            'status' => 'active',
        ]);

        $service = new \App\Services\WhatsappService($profile);
        $ref = new \ReflectionMethod($service, 'resolveGraphStartPayload');
        $startPayload = $ref->invoke($service, $contact);

        $this->assertNotNull($startPayload);
        $this->assertSame('Hola desde el grafo', $startPayload['interactive']['body']['text']);
        $this->assertSame($startUuid, $contact->fresh()->metadata['current_graph_node']);

        \Illuminate\Support\Facades\Http::fake();

        $handled = new \ReflectionMethod($service, 'tryHandleGraphButton');
        $result = $handled->invoke($service, 'btn_test', $contact->fresh(), $contact->phone_number, null);

        $this->assertTrue($result);
        $this->assertSame($targetUuid, $contact->fresh()->metadata['current_graph_node']);
        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->body(), 'Llegaste al nodo destino');
        });
    }

    private function seedCatalogFixture(int $businessProfileId): array
    {
        $menu = \App\Models\WhatsappMenu::create([
            'business_profile_id' => $businessProfileId,
            'title' => 'Catálogo',
            'content' => 'Catálogo de productos',
            'action_id' => 'prices_menu',
            'is_active' => true,
        ]);

        $category = \App\Models\WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Combos',
            'action_id' => 'cat_combos',
            'is_active' => true,
            'order' => 1,
        ]);

        $product = \App\Models\WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'category' => 'Combos',
            'sku' => 'SKU-1',
            'name' => 'Combo Familiar',
            'description' => '5 presas y papas',
            'price' => 10.99,
            'is_promo' => false,
            'is_active' => true,
            'stock' => 5,
            'image' => null,
        ]);

        return [$category, $product];
    }

    public function test_catalog_options_returns_active_products_and_categories(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        [$category, $product] = $this->seedCatalogFixture($flow->business_profile_id);

        $response = $this->getJson(route('admin.marketing-flow.graph.catalog-options'));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $category->id, 'title' => 'Combos']);
        $response->assertJsonFragment(['id' => $product->id, 'name' => 'Combo Familiar']);
    }

    public function test_product_node_can_be_created_without_product_id_but_publish_blocks_it(): void
    {
        $this->actingAsSuperAdmin();
        $flow = $this->seedFlowWithSteps();
        $this->getJson(route('admin.marketing-flow.graph.data'));

        $create = $this->postJson(route('admin.marketing-flow.graph.nodes.store'), [
            'node_type' => 'product',
            'name' => 'Producto destacado',
            'config' => ['show_image' => true, 'buttons' => []],
        ]);
        $create->assertCreated();

        $publish = $this->postJson(route('admin.marketing-flow.graph.publish'));
        $publish->assertStatus(422);
        $publish->assertJsonFragment(['message' => 'Termina de configurar estos nodos antes de publicar: Producto destacado']);
    }

    public function test_product_node_renders_card_and_add_to_cart_uses_real_cart_logic(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Test Business',
            'display_name' => 'Test Business',
            'phone_number' => '5930000000',
            'access_token' => 'test-token',
        ]);
        $flow = MarketingFlow::create([
            'business_profile_id' => $profile->id,
            'name' => 'Flujo de prueba',
            'is_active' => true,
            'is_default' => true,
        ]);
        [$category, $product] = $this->seedCatalogFixture($profile->id);

        $startUuid = (string) \Illuminate\Support\Str::uuid();
        $productUuid = (string) \Illuminate\Support\Str::uuid();

        \App\Models\MarketingFlowVersion::create([
            'flow_id' => $flow->id,
            'version_number' => 1,
            'is_current' => true,
            'published_at' => now(),
            'snapshot' => [
                'start_node_uuid' => $startUuid,
                'nodes' => [
                    $startUuid => [
                        'node_uuid' => $startUuid,
                        'node_type' => 'button_menu',
                        'name' => 'Inicio',
                        'message_template' => 'Hola',
                        'config' => ['interactive_type' => 'button', 'buttons' => [['id' => 'ver_producto', 'title' => 'Ver producto']]],
                        'is_enabled' => true,
                    ],
                    $productUuid => [
                        'node_uuid' => $productUuid,
                        'node_type' => 'product',
                        'name' => 'Producto destacado',
                        'message_template' => '',
                        'config' => ['product_id' => $product->id],
                        'is_enabled' => true,
                    ],
                ],
                'edges' => [
                    $startUuid => ['ver_producto' => $productUuid],
                ],
            ],
        ]);

        $contact = \App\Models\WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593999999999',
            'name' => 'Cliente Prueba',
            'status' => 'active',
            'metadata' => ['current_graph_node' => $startUuid],
        ]);

        $service = new \App\Services\WhatsappService($profile);

        // Navegar al nodo Producto: debe reusar la misma ficha que ya usa el
        // catálogo clásico (nombre, precio). allow_quantity_selection es true
        // por defecto en la BD, así que el botón pide cantidad en vez de
        // agregar 1 unidad de una vez (ver WhatsappService::getProductDetails).
        $tryHandle = new \ReflectionMethod($service, 'tryHandleGraphButton');
        \Illuminate\Support\Facades\Http::fake();
        $handled = $tryHandle->invoke($service, 'ver_producto', $contact, $contact->phone_number, null);
        $this->assertTrue($handled);
        \Illuminate\Support\Facades\Http::assertSent(
            fn ($r) => str_contains($r->body(), 'Combo Familiar')
                && str_contains($r->body(), '10.99')
                && str_contains($r->body(), 'pedir_cantidad_' . $product->id . '_base')
        );
        $this->assertSame($productUuid, $contact->fresh()->metadata['current_graph_node']);

        // El botón real (pedir_cantidad_*) no está cableado en el grafo a
        // propósito: debe caer al motor clásico, no al grafo.
        $notHandled = $tryHandle->invoke($service, 'pedir_cantidad_' . $product->id . '_base', $contact->fresh(), $contact->phone_number, null);
        $this->assertFalse($notHandled);
    }

    public function test_product_node_shows_variation_buttons_when_product_has_variations(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Test Business',
            'display_name' => 'Test Business',
            'phone_number' => '5930000000',
            'access_token' => 'test-token',
        ]);
        [$category, $product] = $this->seedCatalogFixture($profile->id);
        $product->update(['metadata' => ['variations' => [
            ['title' => 'Chico', 'price' => 8.50],
            ['title' => 'Grande', 'price' => 12.50],
        ]]]);

        $service = new \App\Services\WhatsappService($profile);
        $ref = new \ReflectionMethod($service, 'buildGraphNodePayload');
        $payload = $ref->invoke($service, ['node_type' => 'product', 'config' => ['product_id' => $product->id]], null);

        // allow_quantity_selection es true por defecto en la BD: el botón de
        // cada variación pide cantidad en vez de agregar 1 unidad directo.
        $buttonIds = collect($payload['interactive']['action']['buttons'])->pluck('reply.id')->all();
        $this->assertContains('pedir_cantidad_' . $product->id . '_0', $buttonIds);
        $this->assertContains('pedir_cantidad_' . $product->id . '_1', $buttonIds);
    }

    public function test_category_node_delegates_to_real_catalog(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Test Business',
            'display_name' => 'Test Business',
            'phone_number' => '5930000000',
            'access_token' => 'test-token',
        ]);
        [$category, $product] = $this->seedCatalogFixture($profile->id);

        // MarketingCatalogBuilder resuelve el paso "products_menu" del flujo
        // activo/por defecto para saber cómo armar la lista; sin esto cae al
        // mensaje de "catálogo no disponible".
        $flow = MarketingFlow::create([
            'business_profile_id' => $profile->id,
            'name' => 'Flujo de prueba',
            'is_active' => true,
            'is_default' => true,
        ]);
        MarketingFlowStep::create([
            'flow_id' => $flow->id,
            'step_key' => MarketingStepKey::PRODUCTS_MENU,
            'name' => 'Catálogo',
            'message_template' => 'Elige una categoría',
            'sort_order' => 0,
            'is_enabled' => true,
            'config' => ['interactive_type' => 'list', 'list' => ['button' => 'Ver el menú', 'sections' => []]],
        ]);

        $service = new \App\Services\WhatsappService($profile);
        $ref = new \ReflectionMethod($service, 'buildGraphNodePayload');
        $node = [
            'node_type' => 'category',
            'config' => ['category_id' => $category->id],
        ];

        $payload = $ref->invoke($service, $node, null);

        $this->assertSame('interactive', $payload['type']);
        $sections = $payload['interactive']['action']['sections'];
        $rowTitles = collect($sections)->flatMap(fn ($s) => collect($s['rows'])->pluck('title'))->all();
        $this->assertContains('Combo Familiar', $rowTitles);
    }
}
