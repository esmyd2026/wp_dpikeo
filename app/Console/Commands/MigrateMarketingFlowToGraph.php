<?php

namespace App\Console\Commands;

use App\Enums\MarketingButtonAction;
use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowEdge;
use App\Models\MarketingFlowNode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateMarketingFlowToGraph extends Command
{
    protected $signature = 'marketing-flow:migrate-to-graph
        {flow_id? : ID del flujo a migrar (por defecto, el flujo activo/por defecto)}
        {--force : Regenerar el grafo aunque ya existan nodos (borra los nodos/conexiones actuales)}';

    protected $description = 'Genera el borrador del editor visual (nodos y conexiones) a partir de los 10 pasos actuales del flujo de marketing.';

    // welcome/products_menu/orders_menu/cart_summary/checkout/payment_proof/agent_handoff
    // siempre son el mismo tipo de nodo de sistema. main_menu/info_menu se resuelven
    // según su interactive_type actual (botones o lista). fallback_message no se migra:
    // no se dispara por un clic, no encaja en el modelo de grafo.
    private const STEP_TO_NODE_TYPE = [
        MarketingStepKey::WELCOME => MarketingFlowNode::TYPE_START,
        MarketingStepKey::PRODUCTS_MENU => MarketingFlowNode::TYPE_CATALOG,
        MarketingStepKey::ORDERS_MENU => MarketingFlowNode::TYPE_ORDER_STATUS,
        MarketingStepKey::CART_SUMMARY => MarketingFlowNode::TYPE_CART,
        MarketingStepKey::CHECKOUT => MarketingFlowNode::TYPE_CHECKOUT,
        MarketingStepKey::PAYMENT_PROOF => MarketingFlowNode::TYPE_PAYMENT_PROOF,
        MarketingStepKey::AGENT_HANDOFF => MarketingFlowNode::TYPE_AGENT_HANDOFF,
    ];

    // Acciones resueltas cuyo destino es inequívoco (mismo mapeo que ya usa el bot
    // en vivo vía MarketingButtonAction::resolve()).
    private const ACTION_TO_STEP_KEY = [
        MarketingButtonAction::PRODUCTS => MarketingStepKey::PRODUCTS_MENU,
        MarketingButtonAction::ORDERS => MarketingStepKey::ORDERS_MENU,
        MarketingButtonAction::INFO => MarketingStepKey::INFO_MENU,
        MarketingButtonAction::MAIN_MENU => MarketingStepKey::MAIN_MENU,
        MarketingButtonAction::VIEW_CART => MarketingStepKey::CART_SUMMARY,
        MarketingButtonAction::CHECKOUT => MarketingStepKey::CHECKOUT,
        MarketingButtonAction::AGENT => MarketingStepKey::AGENT_HANDOFF,
    ];

    public function handle(): int
    {
        $flow = $this->resolveFlow();
        if (!$flow) {
            $this->error('No se encontró un flujo de marketing activo.');

            return self::FAILURE;
        }

        if ($flow->nodes()->exists() && !$this->option('force')) {
            $this->warn("El flujo #{$flow->id} ya tiene nodos migrados. Usa --force para regenerarlos (se borran los nodos/conexiones actuales del borrador).");

            return self::SUCCESS;
        }

        $flow->edges()->delete();
        $flow->nodes()->delete();

        $steps = $flow->steps()->get()->keyBy('step_key');
        $groups = MarketingStepKey::scenarioGroups();

        /** @var array<string, MarketingFlowNode> $nodesByStepKey */
        $nodesByStepKey = [];

        // El alto real de un nodo depende de cuántos botones/filas tiene (cada
        // uno agrega una fila de "handle" visible). Si se usa un paso fijo de Y
        // sin importar el contenido, los nodos con más botones se encimarían
        // con el siguiente nodo de la misma columna.
        $estimateNodeHeight = function (array $config): int {
            $handleCount = count($config['buttons'] ?? []);
            foreach ($config['list']['sections'] ?? [] as $section) {
                $handleCount += count($section['rows'] ?? []);
            }

            return 150 + max(1, $handleCount) * 30;
        };

        foreach ($groups as $groupIndex => $group) {
            $y = 0;

            foreach (array_values($group['steps']) as $stepKey) {
                if ($stepKey === MarketingStepKey::FALLBACK_MESSAGE) {
                    continue;
                }

                $step = $steps->get($stepKey);
                if (!$step) {
                    continue;
                }

                $nodeType = self::STEP_TO_NODE_TYPE[$stepKey]
                    ?? ($step->getInteractiveType() === 'list' ? MarketingFlowNode::TYPE_LIST_MENU : MarketingFlowNode::TYPE_BUTTON_MENU);

                $config = $step->config ?? [];
                if ($stepKey === MarketingStepKey::WELCOME) {
                    // Hoy el bot clásico envía la bienvenida y el menú principal
                    // como UN solo mensaje (texto de bienvenida + botones del
                    // menú). El nodo de inicio del grafo tiene que quedar
                    // autosuficiente de la misma forma: si no, el saludo se
                    // manda sin ninguna opción para tocar.
                    $mainMenuStep = $steps->get(MarketingStepKey::MAIN_MENU);
                    if ($mainMenuStep) {
                        $config['interactive_type'] = $mainMenuStep->getInteractiveType();
                        $config['buttons'] = $mainMenuStep->getButtons();
                        $config['list'] = $mainMenuStep->getListConfig();
                    }
                }

                $nodesByStepKey[$stepKey] = MarketingFlowNode::create([
                    'flow_id' => $flow->id,
                    'node_uuid' => (string) Str::uuid(),
                    'node_type' => $nodeType,
                    'name' => $step->name ?: (MarketingStepKey::all()[$stepKey] ?? $stepKey),
                    'message_template' => $step->message_template,
                    'config' => $config,
                    'position_x' => ($groupIndex - 1) * 460,
                    'position_y' => $y,
                    'is_enabled' => $step->is_enabled,
                    'is_start' => $stepKey === MarketingStepKey::WELCOME,
                ]);

                $y += $estimateNodeHeight($config) + 60;
            }
        }

        $unresolved = [];

        foreach ($steps as $stepKey => $step) {
            if (!isset($nodesByStepKey[$stepKey])) {
                continue;
            }

            $sourceNode = $nodesByStepKey[$stepKey];
            $rows = array_merge(
                $step->getButtons(),
                collect($step->getListConfig()['sections'] ?? [])->flatMap(fn ($section) => $section['rows'] ?? [])->all()
            );

            foreach ($rows as $row) {
                $handleId = $row['id'] ?? null;
                if (!$handleId) {
                    continue;
                }

                if (!empty($row['response_message'])) {
                    $unresolved[] = "{$stepKey}:{$handleId} (responde texto libre, no navega)";
                    continue;
                }

                $action = MarketingButtonAction::resolve($handleId, $row['action'] ?? null);
                $targetStepKey = self::ACTION_TO_STEP_KEY[$action] ?? null;

                if (!$targetStepKey || !isset($nodesByStepKey[$targetStepKey])) {
                    $unresolved[] = "{$stepKey}:{$handleId} (acción '{$action}' sin destino claro)";
                    continue;
                }

                MarketingFlowEdge::create([
                    'flow_id' => $flow->id,
                    'source_node_uuid' => $sourceNode->node_uuid,
                    'source_handle' => $handleId,
                    'target_node_uuid' => $nodesByStepKey[$targetStepKey]->node_uuid,
                ]);

                // El nodo de inicio comparte los botones de "Menú principal"
                // (ver arriba), así que también necesita las mismas conexiones.
                if ($stepKey === MarketingStepKey::MAIN_MENU && isset($nodesByStepKey[MarketingStepKey::WELCOME])) {
                    MarketingFlowEdge::create([
                        'flow_id' => $flow->id,
                        'source_node_uuid' => $nodesByStepKey[MarketingStepKey::WELCOME]->node_uuid,
                        'source_handle' => $handleId,
                        'target_node_uuid' => $nodesByStepKey[$targetStepKey]->node_uuid,
                    ]);
                }
            }
        }

        $this->info('Grafo generado: ' . count($nodesByStepKey) . ' nodos, ' . $flow->edges()->count() . ' conexiones.');

        if ($unresolved) {
            $this->warn('Botones/filas sin conectar automáticamente (revísalos en el editor):');
            foreach ($unresolved as $item) {
                $this->line('  - ' . $item);
            }
        }

        return self::SUCCESS;
    }

    private function resolveFlow(): ?MarketingFlow
    {
        $flowId = $this->argument('flow_id');
        if ($flowId) {
            return MarketingFlow::with('steps')->find($flowId);
        }

        return MarketingFlow::where('is_active', true)->where('is_default', true)->with('steps')->first()
            ?? MarketingFlow::where('is_active', true)->with('steps')->first();
    }
}
