<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\MessageTemplate;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappPrice;
use App\Support\PaymentMessageTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Centraliza los cambios operativos de un pedido: estados e inventario.
 * Evita que una pantalla marque un pedido como confirmado sin reservar stock.
 */
class OrderLifecycleService
{
    public const STATUSES = [
        WhatsappCart::STATUS_PENDING,
        WhatsappCart::STATUS_CONFIRMED,
        WhatsappCart::STATUS_PREPARING,
        WhatsappCart::STATUS_READY,
        WhatsappCart::STATUS_PAYMENT_PENDING,
        WhatsappCart::STATUS_PAID,
        WhatsappCart::STATUS_COMPLETED,
        WhatsappCart::STATUS_CANCELLED,
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_TRANSITIONS = [
        // 'active' es el estado del carrito mientras el cliente todavía está
        // agregando productos (ver WhatsappService::addToCart). Al confirmar
        // el pedido pasa directo a payment_pending/confirmed; si el cliente
        // cancela antes de terminar, a cancelled.
        'active' => ['payment_pending', 'confirmed', 'pending', 'cancelled'],
        // Caja valida el pedido, cocina lo prepara y despacho lo entrega.
        // No se permite saltar de un pedido nuevo a "listo" o "entregado".
        'pending' => ['confirmed', 'payment_pending', 'paid', 'cancelled'],
        // Desde "Pago pendiente" el único paso con sentido es marcar el pago
        // recibido (o cancelar) -- "Confirmado" ahí generaba una opción sin
        // relación aparente para quien revisa el pedido, y además abría la
        // puerta a retroceder luego de "Pagado" a "Confirmado" a "Pago
        // pendiente" en la misma sesión, mandándole al cliente avisos de
        // WhatsApp en un orden que contradice el progreso real del pedido.
        'payment_pending' => ['paid', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'paid' => ['confirmed', 'preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Orden real de avance del pedido -- "pago pendiente" y "pagado"
     * comparten posición porque son la misma etapa (dinero) vista antes y
     * después de verificar el comprobante. transition() usa esto como red
     * de seguridad para que ningún cambio futuro pueda mandar el pedido
     * "hacia atrás" (y por lo tanto notificar al cliente con estados fuera
     * de secuencia), más allá de lo que ya impida ALLOWED_TRANSITIONS.
     */
    private const STATUS_RANK = [
        'pending' => 0,
        'payment_pending' => 1,
        'paid' => 1,
        'confirmed' => 2,
        'preparing' => 3,
        'ready' => 4,
        'completed' => 5,
    ];

    /** Texto que ve el cliente por WhatsApp cuando el estado cambia. */
    private const STATUS_NOTIFICATION_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado ✅',
        'payment_pending' => 'Pago pendiente',
        'paid' => 'Pagado ✅',
        'preparing' => 'En preparación 👨‍🍳',
        'ready' => 'Listo 🎉',
        'completed' => 'Entregado ✅',
        'cancelled' => 'Cancelado ❌',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUS_NOTIFICATION_LABELS[$status] ?? $status;
    }

    /**
     * Estados a los que puede avanzar un pedido desde su etapa actual.
     *
     * La interfaz administrativa consume esta misma regla para no ofrecer
     * cambios que luego serían rechazados por transition().
     *
     * @return array<int, string>
     */
    public static function allowedTransitionsFor(string $status): array
    {
        return self::ALLOWED_TRANSITIONS[$status] ?? [];
    }

    public function transition(WhatsappCart $order, string $nextStatus, ?int $userId = null, ?string $note = null): WhatsappCart
    {
        if (! in_array($nextStatus, self::STATUSES, true)) {
            throw new InvalidArgumentException('El estado seleccionado no es válido.');
        }

        $previousStatus = (string) $order->status;

        $order = DB::transaction(function () use ($order, $nextStatus, $userId, $note) {
            $order = WhatsappCart::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            $current = (string) $order->status;

            if ($current === $nextStatus) {
                return $order;
            }

            if (! in_array($nextStatus, self::ALLOWED_TRANSITIONS[$current] ?? [], true)) {
                throw new InvalidArgumentException("No se puede cambiar un pedido de {$current} a {$nextStatus}.");
            }

            $currentRank = self::STATUS_RANK[$current] ?? null;
            $nextRank = self::STATUS_RANK[$nextStatus] ?? null;
            if ($nextStatus !== WhatsappCart::STATUS_CANCELLED && $currentRank !== null && $nextRank !== null && $nextRank < $currentRank) {
                throw new InvalidArgumentException("No se puede retroceder un pedido de {$current} a {$nextStatus}.");
            }

            if ($this->requiresReservation($nextStatus) && ! $this->isReserved($order)) {
                $this->reserveInventory($order, $userId);
            }

            if ($nextStatus === WhatsappCart::STATUS_CANCELLED && $this->isReserved($order)) {
                $this->releaseInventory($order, $userId);
            }

            $metadata = $order->metadata ?? [];
            $metadata['status_changed_at'] = now()->toIso8601String();
            $metadata['status_changed_from'] = $current;
            if ($userId) {
                $metadata['status_changed_by'] = $userId;
            }
            if ($note) {
                $metadata['status_change_note'] = $note;
            }
            $metadata['operational_timeline'] = is_array($metadata['operational_timeline'] ?? null)
                ? $metadata['operational_timeline']
                : [];
            $metadata['operational_timeline'][] = [
                'from' => $current,
                'to' => $nextStatus,
                'at' => now()->toIso8601String(),
                'user_id' => $userId,
            ];

            $order->status = $nextStatus;
            $order->metadata = $metadata;
            $order->save();

            return $order->fresh(['items', 'contact']);
        });

        // $userId solo viene poblado cuando el cambio lo hizo el staff desde
        // el panel o la comanda; los cambios que dispara el propio cliente
        // por WhatsApp (confirmar/cancelar) ya le responden en ese mismo
        // flujo, así que evitamos duplicar el aviso.
        if ($userId && $previousStatus !== $order->status) {
            $this->notifyCustomerOfStatusChange($order->id, $order->status);
        }

        // Pedido explícito: preguntar factura o consumidor final "al final,
        // cuando ya confirmó y pagó" -- a diferencia del aviso de arriba,
        // esto corre para CUALQUIER transición (con o sin $userId), porque
        // en efectivo es el propio cliente quien dispara 'confirmed' desde
        // WhatsApp (ver WhatsappService::confirmarPedido) y ese pedido nunca
        // pasa por 'paid'. 'paid' sí aplica cuando caja confirma el
        // comprobante de transferencia/tarjeta desde el panel. Un guard en
        // metadata evita preguntar dos veces si un pedido pasa por
        // paid -> confirmed.
        if ($previousStatus !== $order->status && in_array($order->status, [WhatsappCart::STATUS_PAID, WhatsappCart::STATUS_CONFIRMED], true)) {
            $this->maybeTriggerInvoicePreference($order->id);
        }

        return $order;
    }

    /**
     * Dispara (en segundo plano) la pregunta de factura/consumidor final del
     * bot. Ver WhatsappService::triggerInvoicePreferenceFlow -- ahí vive toda
     * la lógica real (respeta si el paso está desactivado en el checkout del
     * grafo, y evita preguntar dos veces vía metadata['invoice_prompt_sent']).
     */
    private function maybeTriggerInvoicePreference(int $orderId): void
    {
        dispatch(function () use ($orderId) {
            try {
                $order = WhatsappCart::with('contact')->find($orderId);
                $contact = $order?->contact;

                if (! $order || ! $contact || ! $contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
                    return;
                }

                $whatsapp = app(WhatsappService::class);
                $whatsapp->useBusinessProfile($contact->businessProfile);
                $whatsapp->triggerInvoicePreferenceFlow($order, $contact);
            } catch (\Throwable $e) {
                Log::error('[OrderLifecycleService] No se pudo iniciar la preferencia de facturación', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Avisa al cliente por WhatsApp que el estado de su pedido cambió, solo
     * si tiene un número real (no el sintético del punto de venta) y sigue
     * dentro de la ventana de 24 h de conversación de WhatsApp.
     */
    private function notifyCustomerOfStatusChange(int $orderId, string $nextStatus): void
    {
        dispatch(function () use ($orderId, $nextStatus) {
            try {
                $order = WhatsappCart::with('contact')->find($orderId);
                $contact = $order?->contact;

                if (! $contact || ! $contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
                    return;
                }

                if (! $contact->last_inbound_at || $contact->last_inbound_at->lt(now()->subHours(24))) {
                    return;
                }

                $config = $this->chatbotConfigFor($order);
                if (! MessageTemplate::isEnabledFor($config, 'order_status_changed')
                    || ! MessageTemplate::isStatusEnabledFor($config, $nextStatus)) {
                    return;
                }

                $label = self::STATUS_NOTIFICATION_LABELS[$nextStatus] ?? $nextStatus;
                $body = MessageTemplate::render('order_status_changed', [
                    'order_number' => $order->getOrderNumber(),
                    'status_label' => $label,
                ], "📦 Tu pedido *{$order->getOrderNumber()}* cambió de estado:\n\n*{$label}*");

                $whatsapp = app(WhatsappService::class);
                $whatsapp->useBusinessProfile($contact->businessProfile);
                $whatsapp->sendBotPayload($contact, [
                    'type' => 'text',
                    'text' => ['body' => $body],
                ]);
            } catch (\Throwable $e) {
                Log::error('[OrderLifecycleService] No se pudo notificar el cambio de estado', [
                    'order_id' => $orderId,
                    'status' => $nextStatus,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Acción manual de caja: confirma/ajusta el costo de envío de un pedido,
     * y le avisa al cliente por WhatsApp con un mensaje que ya incluye el
     * total final. Solo aplica a delivery -- el costo "para llevar" se
     * eliminó (ver AdminController::sendFulfillmentCosts). Igual que
     * notifyCustomerOfStatusChange(), no envía nada si el número es
     * sintético (punto de venta) o si se cerró la ventana de 24h.
     *
     * @return array{order: WhatsappCart, sent: bool, reason: ?string}
     */
    public function sendFulfillmentCostsMessage(WhatsappCart $order, float $deliveryFee, ?int $userId = null): array
    {
        if (in_array($order->status, [WhatsappCart::STATUS_CANCELLED, WhatsappCart::STATUS_COMPLETED], true)) {
            throw new InvalidArgumentException('No se puede modificar el costo de un pedido cancelado o ya entregado.');
        }
        if ($deliveryFee < 0) {
            throw new InvalidArgumentException('El costo no puede ser negativo.');
        }

        $order = DB::transaction(function () use ($order, $deliveryFee, $userId) {
            $order = WhatsappCart::query()->with(['items', 'contact'])->lockForUpdate()->findOrFail($order->id);
            $metadata = $order->metadata ?? [];

            // El envío no se suma al total hasta esta confirmación (ver
            // WhatsappService::handleTextMessage, paso de nombre del
            // receptor). 'delivery_fee_applied' guarda cuánto de ese costo
            // ya quedó reflejado en el total, para poder corregirlo más
            // adelante sin duplicar el cobro.
            $delta = $deliveryFee - (float) ($metadata['delivery_fee_applied'] ?? 0);
            $metadata['delivery_fee'] = $deliveryFee;
            $metadata['delivery_fee_applied'] = $deliveryFee;
            $metadata['delivery_fee_pending_review'] = false;
            $metadata['delivery_fee_confirmed_by'] = $userId;
            $metadata['delivery_fee_confirmed_at'] = now()->toIso8601String();
            unset($metadata['delivery_fee_pending_since']);

            $order->metadata = $metadata;
            $order->total = max(0, (float) $order->total + $delta);
            $order->save();

            return $order->fresh(['items', 'contact']);
        });

        $contact = $order->contact;
        $reason = null;

        if (! MessageTemplate::isEnabledFor($this->chatbotConfigFor($order), 'fulfillment_costs_confirmed')) {
            $reason = 'notification_disabled';
        } elseif (! $contact || ! $contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
            $reason = 'no_phone';
        } elseif (! $contact->last_inbound_at || $contact->last_inbound_at->lt(now()->subHours(24))) {
            $reason = 'window_closed';
        }

        $sent = $reason === null;

        if ($sent) {
            $orderId = $order->id;

            dispatch(function () use ($orderId) {
                try {
                    $order = WhatsappCart::with(['contact', 'items'])->find($orderId);
                    $contact = $order?->contact;

                    if (! $contact) {
                        return;
                    }

                    $body = $this->buildFulfillmentCostsMessageBody($order);

                    // El total ya es final para lo que se acaba de confirmar;
                    // si el pedido necesitaba comprobante de pago y todavía
                    // no se le pidió, se le pide junto con este mismo mensaje
                    // (ver WhatsappService::maybeRequestPaymentProofAfterCosts).
                    $whatsapp = app(WhatsappService::class);
                    $whatsapp->useBusinessProfile($contact->businessProfile);
                    $proofText = $whatsapp->maybeRequestPaymentProofAfterCosts($order);
                    if ($proofText) {
                        $body .= "\n\n".$proofText;
                    }

                    $whatsapp->sendBotPayload($contact, [
                        'type' => 'text',
                        'text' => ['body' => $body],
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[OrderLifecycleService] No se pudo enviar el costo del pedido', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }

        return ['order' => $order, 'sent' => $sent, 'reason' => $reason];
    }

    /**
     * Config del chatbot de la empresa dueña de este pedido (vía su contacto),
     * o la primera fila global como respaldo si el contacto no tiene negocio
     * asignado.
     */
    private function chatbotConfigFor(WhatsappCart $order): ?WhatsappChatbotConfig
    {
        $businessProfileId = $order->contact?->business_profile_id;

        if ($businessProfileId) {
            $config = WhatsappChatbotConfig::where('business_profile_id', $businessProfileId)->first();
            if ($config) {
                return $config;
            }
        }

        return WhatsappChatbotConfig::first();
    }

    private function buildFulfillmentCostsMessageBody(WhatsappCart $order): string
    {
        $metadata = $order->metadata ?? [];
        $address = $metadata['delivery_location']['manual_address'] ?? null;
        $recipient = $metadata['delivery_recipient_name'] ?? null;
        $deliveryFee = (float) ($metadata['delivery_fee'] ?? 0);

        $addressLine = $address ? "Dirección: {$address}\n" : '';
        $recipientLine = $recipient ? "Recibe: {$recipient}\n" : '';
        // Sin esto, el cliente veía saltar del costo de envío directo al
        // total final, sin poder ver de dónde salía el resto del monto (el
        // costo de los productos en sí).
        $productsSubtotal = $order->items->sum(fn ($item) => (float) $item->price * $item->quantity);
        $subtotalLine = 'Subtotal productos: $'.number_format($productsSubtotal, 2)."\n";
        $deliveryLine = 'Costo de envío: $'.number_format($deliveryFee, 2)."\n";

        // Si el pedido se paga por transferencia/depósito, el cliente necesita
        // saber a qué cuenta mandar el pago justo cuando se le confirma el
        // monto final -- si no, este mensaje no le dice nada accionable.
        $bankInstructions = $order->payment_method === 'transferencia'
            ? $this->chatbotConfigFor($order)?->bank_transfer_instructions
            : null;
        $bankLine = $bankInstructions
            ? "\n\n".trim(PaymentMessageTemplates::render(
                $this->chatbotConfigFor($order),
                'bank_transfer',
                ['bank_instructions' => "🏦 *Datos para tu transferencia o depósito*\n{$bankInstructions}\n\n"]
            ))
            : '';

        $lines = "📦 Pedido *{$order->getOrderNumber()}*\n\n"
            .$addressLine.$recipientLine.$subtotalLine.$deliveryLine
            .'Total a pagar: $'.number_format((float) $order->total, 2)
            .$bankLine;

        return MessageTemplate::render('fulfillment_costs_confirmed', [
            'order_number' => $order->getOrderNumber(),
            'address_line' => $addressLine,
            'recipient_line' => $recipientLine,
            'subtotal_line' => $subtotalLine,
            'delivery_line' => $deliveryLine,
            'pickup_line' => '',
            'total' => number_format((float) $order->total, 2),
            'bank_line' => $bankLine,
        ], $lines);
    }

    public function recordManualStockChange(WhatsappPrice $product, int $previousStock, ?int $userId = null): void
    {
        $current = max(0, (int) $product->stock);
        if ($current === $previousStock) {
            return;
        }

        InventoryMovement::create([
            'whatsapp_price_id' => $product->id,
            'user_id' => $userId,
            'type' => InventoryMovement::TYPE_MANUAL_ADJUSTMENT,
            'quantity' => $current - $previousStock,
            'stock_before' => max(0, $previousStock),
            'stock_after' => $current,
            'note' => 'Ajuste manual desde el catálogo web.',
        ]);
    }

    /**
     * Para el borrado definitivo de un pedido (AdminController::destroyOrder):
     * a diferencia de transition(), no está limitado por ALLOWED_TRANSITIONS
     * -- un pedido 'completed' nunca puede pasar a 'cancelled' normalmente,
     * pero borrarlo sí debe poder devolver el stock si quedó reservado. No
     * hace nada si el pedido no tenía nada reservado (idempotente).
     */
    public function releaseInventoryIfReserved(WhatsappCart $order, ?int $userId = null): void
    {
        if (! $this->isReserved($order)) {
            return;
        }

        DB::transaction(fn () => $this->releaseInventory($order, $userId));
    }

    private function requiresReservation(string $status): bool
    {
        return in_array($status, [
            WhatsappCart::STATUS_CONFIRMED,
            WhatsappCart::STATUS_PAYMENT_PENDING,
            WhatsappCart::STATUS_PAID,
            WhatsappCart::STATUS_PREPARING,
            WhatsappCart::STATUS_READY,
            WhatsappCart::STATUS_COMPLETED,
        ], true);
    }

    private function isReserved(WhatsappCart $order): bool
    {
        return ! empty($order->metadata['inventory_reserved_at']);
    }

    private function reserveInventory(WhatsappCart $order, ?int $userId): void
    {
        $needed = $order->items
            ->groupBy('whatsapp_price_id')
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->sortKeys();

        $products = WhatsappPrice::query()
            ->whereIn('id', $needed->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($needed as $productId => $quantity) {
            $product = $products->get($productId);
            if (! $product || ! $product->is_active || $product->stock < $quantity) {
                $name = $product?->name ?? 'un producto';
                throw new InvalidArgumentException("Stock insuficiente para {$name}.");
            }
        }

        foreach ($needed as $productId => $quantity) {
            /** @var WhatsappPrice $product */
            $product = $products->get($productId);
            $before = (int) $product->stock;
            $after = $before - $quantity;
            $product->update(['stock' => $after]);

            InventoryMovement::create([
                'whatsapp_price_id' => $product->id,
                'whatsapp_cart_id' => $order->id,
                'user_id' => $userId,
                'type' => InventoryMovement::TYPE_SALE_RESERVATION,
                'quantity' => -$quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'note' => 'Reserva por pedido '.$order->getOrderNumber(),
            ]);
        }

        $metadata = $order->metadata ?? [];
        $metadata['inventory_reserved_at'] = now()->toIso8601String();
        $order->metadata = $metadata;
        $order->save();
    }

    private function releaseInventory(WhatsappCart $order, ?int $userId): void
    {
        $needed = $order->items
            ->groupBy('whatsapp_price_id')
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->sortKeys();

        $products = WhatsappPrice::query()
            ->whereIn('id', $needed->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($needed as $productId => $quantity) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }
            $before = (int) $product->stock;
            $after = $before + $quantity;
            $product->update(['stock' => $after]);

            InventoryMovement::create([
                'whatsapp_price_id' => $product->id,
                'whatsapp_cart_id' => $order->id,
                'user_id' => $userId,
                'type' => InventoryMovement::TYPE_SALE_RELEASE,
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'note' => 'Liberación por cancelación '.$order->getOrderNumber(),
            ]);
        }

        $metadata = $order->metadata ?? [];
        unset($metadata['inventory_reserved_at']);
        $metadata['inventory_released_at'] = now()->toIso8601String();
        $order->metadata = $metadata;
        $order->save();
    }
}
