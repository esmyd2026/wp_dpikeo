<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowEdge;
use App\Models\MarketingFlowNode;
use App\Models\MarketingFlowVersion;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingFlowGraphController extends Controller
{
    public function edit()
    {
        $context = CompanyContext::current();

        return view('admin.marketing-flow.graph', [
            'chatbotConfig' => $context->chatbotConfig(),
            'activeCompany' => $context->company,
            // Cada empresa arranca sin flujo -- nunca hereda el de otra
            // empresa (ver resolveFlow()). Se lo muestra explícito acá para
            // que el admin sepa que tiene que crear el suyo, no que está
            // viendo un flujo vacío por error.
            'hasFlow' => (bool) $this->resolveFlow(),
        ]);
    }

    public function data()
    {
        $flow = $this->resolveFlow();

        if (!$flow) {
            return response()->json([
                'flow' => null,
                'nodes' => [],
                'edges' => [],
            ]);
        }

        // Primera visita a esta pantalla: genera el borrador a partir de los
        // 10 pasos actuales si todavía no existe ningún nodo.
        if ($flow->nodes()->count() === 0) {
            Artisan::call('marketing-flow:migrate-to-graph', ['flow_id' => $flow->id]);
        }

        $flow->load(['nodes', 'edges']);
        $currentVersion = $flow->versions()->where('is_current', true)->first();

        return response()->json([
            'flow' => [
                'id' => $flow->id,
                'name' => $flow->name,
                'published_version' => $currentVersion?->version_number,
                'published_at' => $currentVersion?->published_at?->toIso8601String(),
            ],
            'nodes' => $flow->nodes->map(fn ($node) => $this->presentNode($node))->values(),
            'edges' => $flow->edges->map(fn ($edge) => [
                'id' => (string) $edge->id,
                'source' => $edge->source_node_uuid,
                'source_handle' => $edge->source_handle,
                'target' => $edge->target_node_uuid,
            ])->values(),
        ]);
    }

    public function storeNode(Request $request)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $data = $this->validateNode($request, creating: true);

        if (in_array($data['node_type'], MarketingFlowNode::SYSTEM_TYPES, true)
            && $flow->nodes()->where('node_type', $data['node_type'])->where('is_enabled', true)->exists()) {
            return response()->json([
                'message' => 'Ya existe un nodo de este tipo de sistema en el flujo. Solo puede haber uno.',
            ], 422);
        }

        if ($data['node_type'] === MarketingFlowNode::TYPE_START) {
            return response()->json(['message' => 'El nodo de inicio no se crea manualmente.'], 422);
        }

        $node = MarketingFlowNode::create([
            'flow_id' => $flow->id,
            'node_uuid' => (string) Str::uuid(),
            'node_type' => $data['node_type'],
            'name' => $data['name'],
            'message_template' => $data['message_template'] ?? null,
            'config' => $data['config'] ?? [],
            'position_x' => $data['position']['x'] ?? 0,
            'position_y' => $data['position']['y'] ?? 0,
            'is_enabled' => true,
            'is_start' => false,
        ]);

        return response()->json($this->presentNode($node), 201);
    }

    public function updateNode(Request $request, string $nodeUuid)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $node = $flow->nodes()->where('node_uuid', $nodeUuid)->firstOrFail();

        // Un simple arrastre solo trae "position": no forzar el resto de las
        // reglas de validación (nombre/mensaje) en cada movimiento del nodo.
        if ($request->has('position') && !$request->has('name')) {
            $position = $request->validate([
                'position.x' => 'required|numeric',
                'position.y' => 'required|numeric',
            ]);
            $node->update([
                'position_x' => (int) round($position['position']['x']),
                'position_y' => (int) round($position['position']['y']),
            ]);

            return response()->json($this->presentNode($node));
        }

        $data = $this->validateNode($request, creating: false, currentType: $node->node_type);

        $node->update([
            'name' => $data['name'],
            'message_template' => $data['message_template'] ?? null,
            'config' => $data['config'] ?? [],
            'is_enabled' => $data['is_enabled'] ?? $node->is_enabled,
            'position_x' => $data['position']['x'] ?? $node->position_x,
            'position_y' => $data['position']['y'] ?? $node->position_y,
        ]);

        return response()->json($this->presentNode($node));
    }

    public function destroyNode(string $nodeUuid)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $node = $flow->nodes()->where('node_uuid', $nodeUuid)->firstOrFail();

        if ($node->is_start) {
            return response()->json(['message' => 'No se puede borrar el nodo de inicio.'], 422);
        }

        $flow->edges()
            ->where('source_node_uuid', $nodeUuid)
            ->orWhere('target_node_uuid', $nodeUuid)
            ->delete();

        $node->delete();

        return response()->json(['ok' => true]);
    }

    public function storeEdge(Request $request)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $data = $request->validate([
            'source_node_uuid' => 'required|string',
            'source_handle' => 'required|string|max:120',
            'target_node_uuid' => 'required|string',
        ]);

        if ($data['source_node_uuid'] === $data['target_node_uuid']) {
            return response()->json(['message' => 'Un nodo no puede conectarse a sí mismo.'], 422);
        }

        $sourceExists = $flow->nodes()->where('node_uuid', $data['source_node_uuid'])->exists();
        $targetExists = $flow->nodes()->where('node_uuid', $data['target_node_uuid'])->exists();
        if (!$sourceExists || !$targetExists) {
            return response()->json(['message' => 'El nodo de origen o destino no existe en este flujo.'], 422);
        }

        // Un botón/fila solo puede tener un destino: si ya existía una
        // conexión para este handle, se reemplaza por la nueva.
        $flow->edges()
            ->where('source_node_uuid', $data['source_node_uuid'])
            ->where('source_handle', $data['source_handle'])
            ->delete();

        $edge = MarketingFlowEdge::create([
            'flow_id' => $flow->id,
            'source_node_uuid' => $data['source_node_uuid'],
            'source_handle' => $data['source_handle'],
            'target_node_uuid' => $data['target_node_uuid'],
        ]);

        return response()->json([
            'id' => (string) $edge->id,
            'source' => $edge->source_node_uuid,
            'source_handle' => $edge->source_handle,
            'target' => $edge->target_node_uuid,
        ], 201);
    }

    public function destroyEdge(string $edgeId)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $flow->edges()->where('id', $edgeId)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Productos y categorías reales del catálogo, para los selectores de los
     * nodos "Producto" y "Categoría" en el editor.
     */
    public function catalogOptions()
    {
        $businessProfileId = \App\Support\CompanyContext::current()->businessProfileId();

        $products = WhatsappPrice::query()
            ->where('business_profile_id', $businessProfileId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'promo_price', 'is_promo', 'image'])
            ->map(fn ($price) => [
                'id' => $price->id,
                'name' => $price->name,
                'price' => $price->is_promo && $price->promo_price ? (float) $price->promo_price : (float) $price->price,
                'has_image' => (bool) $price->image_url,
            ]);

        $categories = WhatsappMenuItem::catalogCategories($businessProfileId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'title'])
            ->map(fn ($item) => ['id' => $item->id, 'title' => $item->title]);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function uploadNodeImage(Request $request, string $nodeUuid)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $node = $flow->nodes()->where('node_uuid', $nodeUuid)->firstOrFail();

        $request->validate([
            'image' => 'required|image|max:3072',
        ]);

        $path = $request->file('image')->store("marketing-flow-node-images/{$flow->business_profile_id}", 'public');

        $config = $node->config ?? [];
        $usesHeaderImage = in_array($node->node_type, [MarketingFlowNode::TYPE_BUTTON_MENU, MarketingFlowNode::TYPE_LIST_MENU], true);

        if ($usesHeaderImage) {
            $this->deleteConfigImage($config['header']['image_path'] ?? null);
            $config['header'] = ['type' => 'image', 'image_path' => $path];
            $config['header_mode'] = 'image';
        } else {
            $this->deleteConfigImage($config['message_image_path'] ?? null);
            $config['message_image_path'] = $path;
        }

        $node->config = $config;
        $node->save();

        return response()->json($this->presentNode($node));
    }

    public function destroyNodeImage(string $nodeUuid)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $node = $flow->nodes()->where('node_uuid', $nodeUuid)->firstOrFail();
        $config = $node->config ?? [];

        $this->deleteConfigImage($config['header']['image_path'] ?? null);
        $this->deleteConfigImage($config['message_image_path'] ?? null);
        unset($config['header'], $config['header_mode'], $config['message_image_path']);

        $node->config = $config;
        $node->save();

        return response()->json($this->presentNode($node));
    }

    private function deleteConfigImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function presentNode(MarketingFlowNode $node): array
    {
        return [
            'id' => $node->node_uuid,
            'db_id' => $node->id,
            'node_type' => $node->node_type,
            'name' => $node->name,
            'message_template' => $node->message_template,
            'config' => $node->config,
            'position' => ['x' => $node->position_x, 'y' => $node->position_y],
            'is_enabled' => $node->is_enabled,
            'is_start' => $node->is_start,
            'is_system' => $node->isSystemNode(),
        ];
    }

    private function validateNode(Request $request, bool $creating, ?string $currentType = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'message_template' => 'nullable|string|max:1024',
            'position' => 'sometimes|array',
            'position.x' => 'sometimes|numeric',
            'position.y' => 'sometimes|numeric',
            'is_enabled' => 'sometimes|boolean',
            'config' => 'sometimes|array',
            'config.interactive_type' => 'sometimes|string|in:text,button,list',
            'config.footer' => 'nullable|string|max:60',
            'config.buttons' => 'array|max:3',
            'config.buttons.*.id' => 'required_with:config.buttons|string|max:120',
            'config.buttons.*.title' => 'required_with:config.buttons|string|max:20',
            'config.list' => 'sometimes|array',
            'config.list.button' => 'nullable|string|max:20',
            'config.list.sections' => 'array',
            'config.list.sections.*.title' => 'nullable|string|max:24',
            'config.list.sections.*.rows' => 'array',
            'config.list.sections.*.rows.*.id' => 'required|string|max:120',
            'config.list.sections.*.rows.*.title' => 'required|string|max:24',
            'config.list.sections.*.rows.*.description' => 'nullable|string|max:72',
            'config.product_id' => 'sometimes|nullable|integer|exists:whatsapp_prices,id',
            'config.category_id' => 'sometimes|nullable|integer|exists:whatsapp_menu_items,id',
            // Pasos internos del checkout (sucursal, tipo de servicio, etc.):
            // solo se les puede editar el texto, ver UsesMarketingFlowGraph.
            'config.steps' => 'sometimes|array',
            'config.steps.*.message' => 'nullable|string|max:1024',
            'config.steps.*.enabled' => 'sometimes|boolean',
            'config.steps.*.default' => 'nullable|string|max:40',
            'config.steps.payment_method.payment_methods' => 'sometimes|array',
            'config.steps.payment_method.payment_methods.*.enabled' => 'sometimes|boolean',
        ];

        if ($creating) {
            $rules['node_type'] = 'required|string|in:' . implode(',', [
                MarketingFlowNode::TYPE_MESSAGE,
                MarketingFlowNode::TYPE_BUTTON_MENU,
                MarketingFlowNode::TYPE_LIST_MENU,
                MarketingFlowNode::TYPE_PRODUCT,
                MarketingFlowNode::TYPE_CATEGORY,
            ]);
        }

        $data = $request->validate($rules);

        $type = $creating ? $data['node_type'] : $currentType;

        if ($type === MarketingFlowNode::TYPE_LIST_MENU) {
            $totalRows = collect($data['config']['list']['sections'] ?? [])
                ->sum(fn ($section) => count($section['rows'] ?? []));
            if ($totalRows > 10) {
                abort(response()->json([
                    'message' => 'Una lista de WhatsApp admite un máximo de 10 opciones en total.',
                ], 422));
            }
        }

        // No exigimos product_id/category_id aquí: el nodo se crea vacío y
        // se completa en el panel de edición (igual que un mensaje nuevo
        // empieza sin texto). Publicar sí valida que estén completos.

        return $data;
    }

    public function publish(Request $request)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $nodes = $flow->nodes()->get();
        $edges = $flow->edges()->get();

        $startNode = $nodes->firstWhere('is_start', true);
        if (!$startNode) {
            return response()->json([
                'message' => 'El flujo no tiene un nodo de inicio. No se puede publicar.',
            ], 422);
        }

        $incomplete = $nodes->filter(function ($node) {
            if ($node->node_type === MarketingFlowNode::TYPE_PRODUCT) {
                return empty($node->config['product_id']);
            }
            if ($node->node_type === MarketingFlowNode::TYPE_CATEGORY) {
                return empty($node->config['category_id']);
            }

            return false;
        });

        if ($incomplete->isNotEmpty()) {
            return response()->json([
                'message' => 'Termina de configurar estos nodos antes de publicar: ' . $incomplete->pluck('name')->implode(', '),
            ], 422);
        }

        // Nota: antes había aquí una validación de "nodos inalcanzables" y
        // "botones sin conectar" basada solo en las aristas del grafo. Se
        // quitó porque daba falsos positivos y bloqueaba TODA publicación:
        // varios nodos (los de tipo sistema — checkout, carrito, comprobante,
        // agente — y varios botones como "Contacto"/"Redes"/"Pedido múltiple")
        // se resuelven por id reconocido directamente en el motor clásico
        // (WhatsappService::handleInteractiveMessage), no por una arista del
        // grafo. No hay forma confiable de distinguir "huérfano de verdad" de
        // "lo resuelve el motor clásico a propósito" solo mirando el grafo.

        $snapshotNodes = [];
        foreach ($nodes as $node) {
            $snapshotNodes[$node->node_uuid] = [
                'node_uuid' => $node->node_uuid,
                'node_type' => $node->node_type,
                'name' => $node->name,
                'message_template' => $node->message_template,
                'config' => $node->config,
                'is_enabled' => $node->is_enabled,
            ];
        }

        $snapshotEdges = [];
        foreach ($edges as $edge) {
            $snapshotEdges[$edge->source_node_uuid][$edge->source_handle] = $edge->target_node_uuid;
        }

        $snapshot = [
            'start_node_uuid' => $startNode->node_uuid,
            'nodes' => $snapshotNodes,
            'edges' => $snapshotEdges,
        ];

        $version = DB::transaction(function () use ($flow, $snapshot, $request) {
            $flow->versions()->where('is_current', true)->update(['is_current' => false]);

            $nextNumber = (int) ($flow->versions()->max('version_number') ?? 0) + 1;

            return MarketingFlowVersion::create([
                'flow_id' => $flow->id,
                'version_number' => $nextNumber,
                'snapshot' => $snapshot,
                'published_by' => $request->user()?->id,
                'published_at' => now(),
                'is_current' => true,
            ]);
        });

        return response()->json([
            'id' => $version->id,
            'version_number' => $version->version_number,
            'published_at' => $version->published_at?->toIso8601String(),
            'nodes_count' => count($snapshotNodes),
        ], 201);
    }

    /**
     * Deja de usar el flujo visual sin borrar nada: el bot vuelve a
     * atenderse con "Flujo del bot" (el editor clásico) para saludo, menú,
     * etc. Pensado para cuando el grafo publicado divergió del editor
     * clásico y el admin prefiere no mantener los dos en paralelo -- puede
     * volver a publicar cuando quiera, el grafo y sus nodos quedan intactos.
     */
    public function unpublish()
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $wasPublished = $flow->versions()->where('is_current', true)->update(['is_current' => false]) > 0;

        return response()->json(['success' => true, 'was_published' => $wasPublished]);
    }

    public function versions()
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        return response()->json(
            $flow->versions()->with('publisher:id,name')->get()->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'published_at' => $v->published_at?->toIso8601String(),
                'published_by' => $v->publisher?->name,
                'is_current' => $v->is_current,
                'nodes_count' => count($v->snapshot['nodes'] ?? []),
            ])
        );
    }

    public function restoreVersion(Request $request, string $versionId)
    {
        $flow = $this->resolveFlow();
        abort_unless($flow, 404);

        $source = $flow->versions()->where('id', $versionId)->firstOrFail();

        $version = DB::transaction(function () use ($flow, $source, $request) {
            $flow->versions()->where('is_current', true)->update(['is_current' => false]);

            $nextNumber = (int) ($flow->versions()->max('version_number') ?? 0) + 1;

            return MarketingFlowVersion::create([
                'flow_id' => $flow->id,
                'version_number' => $nextNumber,
                'snapshot' => $source->snapshot,
                'published_by' => $request->user()?->id,
                'published_at' => now(),
                'is_current' => true,
            ]);
        });

        return response()->json([
            'id' => $version->id,
            'version_number' => $version->version_number,
            'restored_from' => $source->version_number,
        ], 201);
    }

    private function resolveFlow(): ?MarketingFlow
    {
        $profile = CompanyContext::current()->businessProfile;
        if (!$profile) {
            return null;
        }

        return MarketingFlow::query()
            ->where('business_profile_id', $profile->id)
            ->where('is_default', true)
            ->first()
            ?? MarketingFlow::query()
                ->where('business_profile_id', $profile->id)
                ->first();
    }
}
