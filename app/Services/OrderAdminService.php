<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappCartNote;
use App\Models\WhatsappContact;

class OrderAdminService
{
    public const INVOICE_STATUSES = [
        'none' => 'Sin factura',
        'requested' => 'Cliente pidió factura',
        'data_ready' => 'Datos listos para emitir',
        'issued' => 'Factura emitida',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'transferencia' => 'Transferencia bancaria',
        'tarjeta' => 'Tarjeta',
        'efectivo' => 'Efectivo',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'awaiting_proof' => 'Esperando comprobante',
        'proof_submitted' => 'Comprobante recibido',
        'pending' => 'Pago pendiente',
        'confirmed' => 'Confirmado',
        'cash_on_delivery' => 'Efectivo contra entrega',
    ];

    public function __construct(private readonly OrderLifecycleService $lifecycle) {}

    public function orderPayload(WhatsappCart $order): array
    {
        $order->load([
            'items',
            'contact',
            'notes' => fn ($q) => $q->with('user:id,name')->latest('created_at'),
        ]);

        $contact = $order->contact;
        $billing = $this->resolveBillingData($order, $contact);

        return [
            'id' => $order->id,
            'total' => $order->total,
            'status' => $order->status,
            'created_at' => $order->created_at,
            'note' => $order->note,
            'requires_invoice' => (bool) $order->requires_invoice,
            'invoice_status' => $order->invoice_status ?? 'none',
            'invoice_status_label' => self::INVOICE_STATUSES[$order->invoice_status ?? 'none'] ?? $order->invoice_status,
            'invoice_data' => $order->invoice_data ?? [],
            'billing' => $billing,
            'items' => $order->items,
            'items_count' => $order->items->count(),
            'order_number' => $order->getOrderNumber(),
            'awaiting_client_confirmation' => !empty($order->metadata['awaiting_client_confirmation']),
            'confirmation_sent_at' => $order->metadata['confirmation_sent_at'] ?? null,
            'contact' => $contact ? [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone_number' => $contact->phone_number,
                'national_id' => $contact->national_id,
                'address' => $contact->address,
                'billing_type' => $contact->billing_type,
                'billing_id' => $contact->billing_id,
                'billing_legal_name' => $contact->billing_legal_name,
            ] : null,
            'notes' => $order->notes->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'type_label' => $n->typeLabel(),
                'body' => $n->body,
                'author' => $n->user?->name ?? 'Agente',
                'created_at' => $n->created_at,
            ]),
            'internal_notes_count' => $order->notes->where('type', WhatsappCartNote::TYPE_INTERNAL)->count(),
            'feedback_count' => $order->notes->where('type', WhatsappCartNote::TYPE_FEEDBACK)->count(),
            'agent_checklist' => $this->agentChecklist($order, $billing),
            'payment' => $this->paymentPayload($order),
            'fulfillment' => $this->fulfillmentPayload($order),
        ];
    }

    /** @return array<string, mixed>|null */
    private function fulfillmentPayload(WhatsappCart $order): ?array
    {
        $serviceType = $order->metadata['service_type'] ?? null;
        if (!$serviceType) {
            return null;
        }

        $pickupMode = $order->metadata['pickup_mode'] ?? null;

        return [
            'branch' => $order->branch?->name,
            'service_type' => $serviceType,
            'service_type_label' => $serviceType === 'llevar' ? 'Para llevar' : 'Para servir',
            'pickup_mode' => $pickupMode,
            'pickup_mode_label' => match ($pickupMode) {
                'retiro' => 'Retiro en el local',
                'delivery' => 'Delivery',
                default => null,
            },
            'address' => $order->metadata['delivery_location']['manual_address'] ?? null,
            'recipient_name' => $order->metadata['delivery_recipient_name'] ?? null,
            'last_dispatch_driver' => \App\Models\DeliveryDriver::summaryFor($order->metadata['last_dispatch_driver_id'] ?? null),
            // Pensado para el repartidor: si es efectivo, cuánto cobrar al
            // entregar; si ya se pagó por transferencia/tarjeta, que no cobre nada.
            'payment_dispatch_label' => match ($order->payment_method) {
                'efectivo' => 'Efectivo — cobrar $' . number_format((float) $order->total, 2) . ' al entregar',
                'transferencia' => 'Transferencia o depósito (ya pagado, no cobrar)',
                'tarjeta' => 'Tarjeta (ya pagado, no cobrar)',
                default => 'No especificado',
            },
            'delivery_fee' => $order->metadata['delivery_fee'] ?? null,
            'delivery_fee_pending_review' => (bool) ($order->metadata['delivery_fee_pending_review'] ?? false),
            'delivery_distance_km' => $order->metadata['delivery_distance_km'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentPayload(WhatsappCart $order): array
    {
        $visible = $order->hasPaymentProof()
            || $order->isAwaitingPaymentProof()
            || $order->requiresPaymentProof();

        if (!$visible) {
            return ['visible' => false];
        }

        $method = (string) ($order->payment_method ?? '');
        $state = 'none';
        if ($order->hasPaymentProof()) {
            $state = 'submitted';
        } elseif ($order->isAwaitingPaymentProof()) {
            $state = 'awaiting';
        }

        $proofMeta = is_array($order->metadata['payment_proof'] ?? null)
            ? $order->metadata['payment_proof']
            : null;

        $proof = null;
        if ($state === 'submitted') {
            $message = $order->paymentProofMessage();
            $type = $proofMeta['type'] ?? $message?->type ?? 'image';

            $proof = [
                'type' => $type,
                'filename' => $proofMeta['filename'] ?? ($type === 'document' ? 'comprobante.pdf' : 'comprobante.jpg'),
                'received_at' => $proofMeta['received_at'] ?? $message?->created_at?->toIso8601String(),
                'media_url' => route('admin.orders.payment-proof', $order->id),
            ];
        }

        return [
            'visible' => true,
            'method' => $method,
            'method_label' => self::PAYMENT_METHOD_LABELS[$method] ?? ($method !== '' ? ucfirst($method) : '—'),
            'status' => $order->payment_status,
            'status_label' => self::PAYMENT_STATUS_LABELS[$order->payment_status ?? '']
                ?? ($order->payment_status ?: '—'),
            'state' => $state,
            'proof' => $proof,
        ];
    }

    /** @return array<string, mixed> */
    public function resolveBillingData(WhatsappCart $order, ?WhatsappContact $contact): array
    {
        $stored = is_array($order->invoice_data) ? $order->invoice_data : [];

        return [
            'billing_type' => $stored['billing_type'] ?? $contact?->billing_type ?? 'cedula',
            'billing_id' => $stored['billing_id'] ?? $contact?->billing_id ?? $contact?->national_id ?? '',
            'billing_legal_name' => $stored['billing_legal_name'] ?? $contact?->billing_legal_name ?? $contact?->name ?? '',
            'address' => $stored['address'] ?? $contact?->address ?? '',
        ];
    }

    public function updateOrder(WhatsappCart $order, array $data, bool $syncProfile = true): WhatsappCart
    {
        $nextStatus = $data['status'] ?? null;
        if (array_key_exists('requires_invoice', $data)) {
            $order->requires_invoice = (bool) $data['requires_invoice'];
            if ($order->requires_invoice && ($order->invoice_status === 'none' || !$order->invoice_status)) {
                $order->invoice_status = 'requested';
            }
            if (!$order->requires_invoice) {
                $order->invoice_status = 'none';
            }
        }

        if (!empty($data['invoice_status']) && in_array($data['invoice_status'], array_keys(self::INVOICE_STATUSES), true)) {
            $order->invoice_status = $data['invoice_status'];
            if ($data['invoice_status'] !== 'none') {
                $order->requires_invoice = true;
            }
        }

        $billingKeys = ['billing_type', 'billing_id', 'billing_legal_name', 'address'];
        $billingInput = array_intersect_key($data, array_flip($billingKeys));
        if ($billingInput !== []) {
            $current = is_array($order->invoice_data) ? $order->invoice_data : [];
            $order->invoice_data = array_merge($current, $billingInput);

            if ($this->billingIsComplete($order->invoice_data) && in_array($order->invoice_status, ['requested', 'none'], true)) {
                $order->invoice_status = 'data_ready';
            }
        }

        $order->save();

        if ($nextStatus && $nextStatus !== $order->status) {
            $order = $this->lifecycle->transition($order, $nextStatus, auth()->id());
        }

        if ($syncProfile && $order->contact && ($billingInput !== [] || $order->requires_invoice)) {
            $this->syncBillingToContact($order->contact, $order->invoice_data ?? []);
        }

        return $order->fresh(['items', 'contact', 'notes.user']);
    }

    public function syncBillingToContact(WhatsappContact $contact, array $billing): void
    {
        if ($billing === []) {
            return;
        }

        $updates = [];

        if (!empty($billing['billing_type'])) {
            $updates['billing_type'] = $billing['billing_type'];
        }
        if (!empty($billing['billing_id'])) {
            $updates['billing_id'] = preg_replace('/\s+/', '', trim($billing['billing_id']));
            if (($billing['billing_type'] ?? $contact->billing_type) === 'cedula') {
                $updates['national_id'] = $updates['billing_id'];
            }
        }
        if (!empty($billing['billing_legal_name'])) {
            $updates['billing_legal_name'] = trim($billing['billing_legal_name']);
        }
        if (!empty($billing['address'])) {
            $updates['address'] = trim($billing['address']);
        }

        if ($updates !== []) {
            $contact->update($updates);
        }
    }

    /** @param array<string, mixed>|null $billing */
    public function billingIsComplete(?array $billing): bool
    {
        if (!$billing) {
            return false;
        }

        $type = $billing['billing_type'] ?? '';
        $id = trim((string) ($billing['billing_id'] ?? ''));
        $name = trim((string) ($billing['billing_legal_name'] ?? ''));
        $address = trim((string) ($billing['address'] ?? ''));

        if (!in_array($type, ['cedula', 'ruc'], true)) {
            return false;
        }

        return $id !== '' && $name !== '' && $address !== '';
    }

    /** @return array<int, array{key: string, label: string, done: bool, hint: string}> */
    private function agentChecklist(WhatsappCart $order, array $billing): array
    {
        $items = [];

        if ($order->requires_invoice) {
            $items[] = [
                'key' => 'requested',
                'label' => 'Cliente solicitó factura',
                'done' => true,
                'hint' => 'Confirmado en el pedido.',
            ];
            $items[] = [
                'key' => 'data',
                'label' => 'Datos fiscales registrados',
                'done' => $this->billingIsComplete($billing),
                'hint' => 'Cédula o RUC, nombre/razón social y dirección.',
            ];
            $items[] = [
                'key' => 'issue',
                'label' => 'Factura emitida en sistema contable',
                'done' => $order->invoice_status === 'issued',
                'hint' => 'Marca como emitida cuando la generes.',
            ];
            $items[] = [
                'key' => 'send',
                'label' => 'Comprobante enviado al cliente',
                'done' => $order->notes->where('type', WhatsappCartNote::TYPE_FEEDBACK)
                    ->contains(fn ($n) => stripos($n->body, 'factura') !== false || stripos($n->body, 'comprobante') !== false),
                'hint' => 'Registra en feedback que enviaste la factura por chat.',
            ];
        }

        return $items;
    }
}
