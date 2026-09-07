<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCart extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAYMENT_PENDING = 'payment_pending';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'contact_id',
        'branch_id',
        'total',
        'status',
        'metadata',
        'note',
        'payment_status',
        'payment_method',
        'payment_reference',
        'requires_invoice',
        'invoice_status',
        'invoice_data',
    ];

    protected $casts = [
        'metadata' => 'array',
        'invoice_data' => 'array',
        'total' => 'decimal:2',
        'requires_invoice' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Pedidos que nacen desde WhatsApp o un Flow no presentan selector de
        // local. Se asignan de forma consistente a la sucursal predeterminada.
        static::creating(function (self $cart): void {
            if ($cart->branch_id) {
                return;
            }

            $cart->branch_id = BusinessBranch::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id');
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function items()
    {
        return $this->hasMany(WhatsappCartItem::class, 'whatsapp_cart_id');
    }

    public function notes()
    {
        return $this->hasMany(WhatsappCartNote::class, 'whatsapp_cart_id');
    }

    /**
     * Carritos en compra (active) no son pedidos cerrados.
     */
    public function scopeReportable($query)
    {
        return $query->whereNotIn('status', ['active', 'abandoned']);
    }

    /**
     * Acota a los pedidos del contacto de la empresa activa del admin
     * autenticado. Sin usuario/sesión (comandos, jobs) no filtra -- esos
     * contextos no tienen "empresa activa" y deben resolver su propio tenant
     * explícitamente si lo necesitan.
     */
    public function scopeForActiveCompany($query)
    {
        $user = auth()->user();
        if (!$user) {
            return $query;
        }

        try {
            $businessProfileId = \App\Support\CompanyContext::current()->businessProfileId();
        } catch (\Throwable $e) {
            return $query;
        }

        if (!$businessProfileId) {
            return $query;
        }

        return $query->whereHas('contact', fn ($q) => $q->where('business_profile_id', $businessProfileId));
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed()
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPreparing()
    {
        return $this->status === self::STATUS_PREPARING;
    }

    public function isReady()
    {
        return $this->status === self::STATUS_READY;
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPaymentPending()
    {
        return $this->status === self::STATUS_PAYMENT_PENDING;
    }

    public function isPaid()
    {
        return $this->status === self::STATUS_PAID;
    }

    public function confirm()
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->save();
    }

    public function markAsPaymentPending($paymentMethod = null)
    {
        $this->status = self::STATUS_PAYMENT_PENDING;
        $this->payment_method = $paymentMethod;
        $this->save();
    }

    public function markAsPaid($paymentReference = null)
    {
        $this->status = self::STATUS_PAID;
        $this->payment_reference = $paymentReference;
        $this->save();
    }

    public function cancel()
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function hasPaymentProof(): bool
    {
        return !empty($this->metadata['payment_proof']);
    }

    public function isAwaitingPaymentProof(): bool
    {
        return $this->payment_status === 'awaiting_proof'
            || !empty($this->metadata['pending_payment_proof']);
    }

    public function attachPaymentProof(array $proofData): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['payment_proof'] = $proofData;
        unset($metadata['pending_payment_proof']);
        $this->metadata = $metadata;
        $this->payment_status = 'proof_submitted';
        $this->save();
    }

    public function markAwaitingPaymentProof(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['pending_payment_proof'] = true;
        $this->metadata = $metadata;
        $this->payment_status = 'awaiting_proof';
        $this->save();
    }

    public function getOrderNumber(): string
    {
        return $this->metadata['order_details']['order_number']
            ?? 'ORD-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function paymentProofMessage(): ?WhatsappMessage
    {
        $byCart = WhatsappMessage::query()
            ->where('metadata->cart_id', $this->id)
            ->where('metadata->payment_proof', true)
            ->latest()
            ->first();

        if ($byCart) {
            return $byCart;
        }

        $waMessageId = $this->metadata['payment_proof']['message_id'] ?? null;
        if ($waMessageId) {
            return WhatsappMessage::query()->where('message_id', $waMessageId)->first();
        }

        return null;
    }

    public function requiresPaymentProof(): bool
    {
        return in_array($this->payment_method, ['transferencia', 'tarjeta'], true);
    }

    /**
     * El costo de envío o de empaque para llevar todavía no lo confirmó caja
     * -- mientras esto esté pendiente, el total del pedido no es el final.
     * Ver OrderLifecycleService::sendFulfillmentCostsMessage() (limpia estas
     * banderas) y applyDeliveryRecipientName()/confirmarPedido() en
     * WhatsappService (las setean).
     */
    public function hasPendingFulfillmentCosts(): bool
    {
        $metadata = $this->metadata ?? [];

        if (($metadata['pickup_mode'] ?? null) === 'delivery') {
            if (! empty($metadata['delivery_fee_pending_review'] ?? false) || ! array_key_exists('delivery_fee', $metadata)) {
                return true;
            }
        }

        if (($metadata['service_type'] ?? null) === 'llevar' && ! array_key_exists('pickup_fee', $metadata)) {
            return true;
        }

        return false;
    }

    /** Desde cuándo espera el costo pendiente (para medir demoras), o null si no hay ninguno registrado. */
    public function pendingFulfillmentSince(): ?\Illuminate\Support\Carbon
    {
        $metadata = $this->metadata ?? [];
        $raw = $metadata['delivery_fee_pending_since'] ?? $metadata['pickup_fee_pending_since'] ?? null;

        return $raw ? \Illuminate\Support\Carbon::parse($raw) : null;
    }

    /** Autoservicio del cliente (no un admin): solo antes de que caja marque el pedido como pagado o en preparación. */
    public function isCancelableBySelfService(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PAYMENT_PENDING], true);
    }
}
