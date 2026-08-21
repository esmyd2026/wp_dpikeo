<?php

namespace App\Services\Concerns;

use App\Models\MarketingFlowNode;
use App\Models\MarketingFlowStep;
use App\Models\MarketingFlowVersion;
use App\Models\WhatsappContact;
use App\Services\MarketingFlowPayloadBuilder;

/**
 * Intérprete del editor visual (nodos/conexiones). Solo actúa si el negocio
 * ya publicó una versión del grafo; mientras tanto, todos los métodos
 * devuelven null/false y el motor clásico (switch de $buttonId en
 * WhatsappService) sigue funcionando exactamente igual que antes.
 *
 * El grafo NUNCA sustituye la lógica real de catálogo/carrito/checkout/etc.:
 * los nodos "de sistema" delegan en los mismos métodos privados que ya usa
 * el motor clásico (getProductsMenu, getCartContents, ...).
 */
trait UsesMarketingFlowGraph
{
    protected ?array $graphSnapshotCache = null;
    protected bool $graphSnapshotLoaded = false;

    protected function getPublishedGraphSnapshot(): ?array
    {
        if ($this->graphSnapshotLoaded) {
            return $this->graphSnapshotCache;
        }
        $this->graphSnapshotLoaded = true;

        $flow = $this->resolveMarketingFlow();
        if (!$flow) {
            return $this->graphSnapshotCache = null;
        }

        $version = MarketingFlowVersion::where('flow_id', $flow->id)
            ->where('is_current', true)
            ->first();

        return $this->graphSnapshotCache = $version?->snapshot;
    }

    /**
     * Construye y envía el nodo de inicio publicado (si existe) para un
     * saludo. Devuelve el payload enviado, o null si no hay grafo publicado
     * o el nodo de inicio no pudo renderizarse (el llamador debe usar el
     * flujo clásico en ese caso).
     */
    protected function resolveGraphStartPayload(?WhatsappContact $contact): ?array
    {
        $snapshot = $this->getPublishedGraphSnapshot();
        if (!$snapshot || empty($snapshot['start_node_uuid'])) {
            return null;
        }

        $node = $snapshot['nodes'][$snapshot['start_node_uuid']] ?? null;
        if (!$node || !($node['is_enabled'] ?? true)) {
            return null;
        }

        $payload = $this->buildGraphNodePayload($node, $contact);
        if ($payload && $contact) {
            $this->rememberGraphNode($contact, $node['node_uuid']);
        }

        return $payload;
    }

    /**
     * Intenta resolver y enviar el botón/fila presionado según el grafo
     * publicado, usando el "nodo actual" que quedó guardado la última vez
     * que le enviamos algo del grafo a este contacto. Devuelve true si el
     * grafo manejó el clic (ya se envió la respuesta); false si no aplica y
     * el motor clásico debe encargarse.
     */
    protected function tryHandleGraphButton(string $buttonId, ?WhatsappContact $contact, string $to, ?string $inboundMessageId = null): bool
    {
        if (!$contact) {
            return false;
        }

        $snapshot = $this->getPublishedGraphSnapshot();
        if (!$snapshot) {
            return false;
        }

        $currentNodeUuid = $contact->metadata['current_graph_node'] ?? null;
        if (!$currentNodeUuid) {
            return false;
        }

        if (!isset($snapshot['nodes'][$currentNodeUuid])) {
            // El nodo donde estaba este contacto ya no existe en la versión
            // publicada (se borró, o se publicó un grafo nuevo mientras la
            // conversación estaba a mitad de camino). Sin esto, el puntero
            // queda huérfano para siempre: cada clic futuro repetía el mismo
            // fallo silencioso. Se limpia para que la próxima interacción
            // entre limpia por el motor clásico en vez de quedar atascada.
            $this->forgetGraphNode($contact);

            return false;
        }

        $targetUuid = $snapshot['edges'][$currentNodeUuid][$buttonId] ?? null;
        if (!$targetUuid) {
            return false;
        }

        $node = $snapshot['nodes'][$targetUuid] ?? null;
        if (!$node || !($node['is_enabled'] ?? true)) {
            return false;
        }

        $payload = $this->buildGraphNodePayload($node, $contact);

        $this->rememberGraphNode($contact, $node['node_uuid']);

        if ($payload) {
            $this->prepareBotReply($contact, $inboundMessageId);
            $this->sendMessage($to, $payload);
        }
        // Si $payload es null (ej. "agent_handoff", que ya envía su propio
        // mensaje dentro de buildGraphNodePayload), no hay nada más que enviar.

        return true;
    }

    protected function buildGraphNodePayload(array $node, ?WhatsappContact $contact = null, ?string $bodyOverride = null): ?array
    {
        $vars = $this->marketingFlowVariables($contact);

        return match ($node['node_type']) {
            MarketingFlowNode::TYPE_CATALOG => $this->getProductsMenu($contact),
            MarketingFlowNode::TYPE_CART => $this->getCartContents($contact),
            MarketingFlowNode::TYPE_CHECKOUT => $this->finalizarCompra($contact),
            MarketingFlowNode::TYPE_ORDER_STATUS => $this->getOrderMenu(),
            MarketingFlowNode::TYPE_AGENT_HANDOFF => $this->graphTriggerAgentHandoff($contact),
            // El comprobante de pago no se dispara por un clic (llega como
            // imagen/documento en un estado de "esperando comprobante"), así
            // que este nodo no se renderiza como respuesta a un botón.
            MarketingFlowNode::TYPE_PAYMENT_PROOF => null,
            // Categoría reutiliza el mismo catálogo real que ya usa el
            // navegador de categorías, pero saltándose la selección: entra
            // directo a los productos de la categoría elegida.
            MarketingFlowNode::TYPE_CATEGORY => $this->getProductsMenu($contact, $node['config']['category_id'] ?? null),
            // Reusa la misma ficha de producto que ya usa el catálogo clásico:
            // variaciones (o el botón directo de "Agregar" si no aplica),
            // imagen real del producto (con el logo como respaldo) y el
            // botón "Ver carrito". Sus botones (quick_add_*, personalizar_*)
            // no están cableados en el grafo a propósito — ya los resuelve el
            // motor clásico, que sabe agregar al carrito con la variación y
            // cantidad correctas.
            MarketingFlowNode::TYPE_PRODUCT => $this->renderGraphProductNode($node, $contact),
            default => $this->renderGraphMessageNode($node, $vars, $bodyOverride),
        };
    }

    private function renderGraphProductNode(array $node, ?WhatsappContact $contact = null): ?array
    {
        $productId = $node['config']['product_id'] ?? null;
        if (!$productId) {
            return ['type' => 'text', 'text' => ['body' => 'Este producto ya no está disponible.']];
        }

        return $this->getProductDetails($productId, $contact)
            ?? ['type' => 'text', 'text' => ['body' => 'Este producto ya no está disponible.']];
    }

    private function renderGraphMessageNode(array $node, array $vars, ?string $bodyOverride): array
    {
        $step = new MarketingFlowStep([
            'message_template' => $node['message_template'] ?? '',
            'config' => $node['config'] ?? [],
        ]);

        return app(MarketingFlowPayloadBuilder::class)->build($step, $vars, $bodyOverride);
    }

    private function graphTriggerAgentHandoff(?WhatsappContact $contact): ?array
    {
        if ($contact) {
            $this->triggerAgentHandoff($contact, $contact->phone_number, 'graph_node');
        }

        return null;
    }

    /**
     * Config guardada para uno de los pasos internos del checkout (sucursal,
     * tipo de servicio, retiro/delivery, etc.). Estos pasos no son nodos
     * propios del grafo -- viven dentro del nodo "checkout"
     * (config.steps.<key>) para que el admin los pueda editar/desactivar sin
     * poder reordenar la lógica real del carrito/pedido.
     */
    protected function getCheckoutStepConfig(string $stepKey): array
    {
        $snapshot = $this->getPublishedGraphSnapshot();

        if ($snapshot) {
            foreach ($snapshot['nodes'] ?? [] as $node) {
                if (($node['node_type'] ?? null) !== MarketingFlowNode::TYPE_CHECKOUT) {
                    continue;
                }

                return $node['config']['steps'][$stepKey] ?? [];
            }
        }

        return [];
    }

    /**
     * Texto de un paso interno del checkout. Si no hay grafo publicado, o el
     * paso no tiene texto propio guardado, se usa el texto por defecto de
     * siempre.
     */
    protected function getCheckoutStepMessage(string $stepKey, string $default, array $vars = []): string
    {
        $custom = $this->getCheckoutStepConfig($stepKey)['message'] ?? null;

        if (is_string($custom) && trim($custom) !== '') {
            return MarketingFlowNode::interpolate($custom, $vars);
        }

        return MarketingFlowNode::interpolate($default, $vars);
    }

    /**
     * false solo si el admin desactivó explícitamente este paso desde el
     * editor visual (config.steps.<key>.enabled === false). Por defecto todos
     * los pasos están activos.
     */
    protected function isCheckoutStepEnabled(string $stepKey): bool
    {
        return ($this->getCheckoutStepConfig($stepKey)['enabled'] ?? true) !== false;
    }

    /**
     * Valor a usar automáticamente cuando el paso está desactivado, elegido
     * por el admin en el editor visual. Si no configuró ninguno, se usa
     * $fallback (el comportamiento de siempre).
     */
    protected function getCheckoutStepDefault(string $stepKey, string $fallback): string
    {
        $value = $this->getCheckoutStepConfig($stepKey)['default'] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * false solo si el admin desactivó explícitamente este método de pago
     * (config.steps.payment_method.payment_methods.<method>.enabled ===
     * false) desde el editor visual. Si desactivar dejaría cero métodos
     * disponibles, se ignora esa configuración y se muestran todos -- un
     * checkout sin ninguna forma de pago rompería el pedido.
     */
    protected function isPaymentMethodEnabled(string $method): bool
    {
        return in_array($method, $this->enabledPaymentMethods(), true);
    }

    protected function enabledPaymentMethods(): array
    {
        $all = ['transferencia', 'efectivo', 'tarjeta'];
        $configured = $this->getCheckoutStepConfig('payment_method')['payment_methods'] ?? [];

        $enabled = array_values(array_filter(
            $all,
            fn ($method) => ($configured[$method]['enabled'] ?? true) !== false
        ));

        return $enabled ?: $all;
    }

    protected function rememberGraphNode(WhatsappContact $contact, string $nodeUuid): void
    {
        $meta = $contact->metadata ?? [];
        $meta['current_graph_node'] = $nodeUuid;
        $contact->metadata = $meta;
        $contact->save();
    }

    protected function forgetGraphNode(WhatsappContact $contact): void
    {
        $contact->forgetFlowPosition();
    }
}
