<?php

namespace App\Models;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
        // Solo se asigna automáticamente cuando la empresa tiene una única
        // sucursal activa. Con varias, el canal de compra debe pedir una
        // selección explícita al cliente u operador.
        static::creating(function (self $cart): void {
            if ($cart->branch_id) {
                return;
            }

            $businessProfileId = WhatsappContact::query()
                ->whereKey($cart->contact_id)
                ->value('business_profile_id');

            if (! $businessProfileId) {
                return;
            }

            $branches = BusinessBranch::query()
                ->where('business_profile_id', $businessProfileId)
                ->availableForOrders()
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->limit(2)
                ->pluck('id');

            if ($branches->isEmpty()) {
                $profile = WhatsappBusinessProfile::find($businessProfileId);
                if ($profile) {
                    $cart->branch_id = BusinessBranch::ensureDefaultForProfile($profile)->id;
                }

                return;
            }

            if ($branches->count() === 1) {
                $cart->branch_id = (int) $branches->first();
            }
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
        if (! $user) {
            return $query;
        }

        try {
            $businessProfileId = CompanyContext::current()->businessProfileId();
        } catch (\Throwable $e) {
            // En aislamiento multiempresa es preferible no devolver nada a
            // exponer pedidos de otro tenant cuando el contexto esté incompleto.
            return $query->whereRaw('1 = 0');
        }

        if (! $businessProfileId) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('contact', fn ($q) => $q->where('business_profile_id', $businessProfileId));

        $branchIds = $user->accessibleBranchIds($businessProfileId);
        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query;
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
        return ! empty($this->metadata['payment_proof']);
    }

    public function isAwaitingPaymentProof(): bool
    {
        return $this->payment_status === 'awaiting_proof'
            || ! empty($this->metadata['pending_payment_proof']);
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
            ?? 'ORD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
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
     * El costo de envío todavía no se pudo calcular solo (dirección a mano,
     * fuera de cobertura de la tabla de tramos, o sucursal sin tabla
     * configurada) y lo debe confirmar caja -- mientras esto esté
     * pendiente, el total del pedido no es el final. Ya no aplica a
     * pedidos "para llevar": ese costo se eliminó, solo existe para
     * delivery. Ver OrderLifecycleService::sendFulfillmentCostsMessage()
     * (limpia esta bandera) y applyDeliveryRecipientName() en
     * WhatsappService (la setea).
     */
    public function hasPendingFulfillmentCosts(): bool
    {
        $metadata = $this->metadata ?? [];

        if (($metadata['pickup_mode'] ?? null) !== 'delivery') {
            return false;
        }

        return ! empty($metadata['delivery_fee_pending_review'] ?? false) || ! array_key_exists('delivery_fee', $metadata);
    }

    /** Desde cuándo espera el costo de envío pendiente (para medir demoras), o null si no hay ninguno registrado. */
    public function pendingFulfillmentSince(): ?Carbon
    {
        $raw = $this->metadata['delivery_fee_pending_since'] ?? null;

        return $raw ? Carbon::parse($raw) : null;
    }

    /**
     * true si lo próximo que hace falta para avanzar el pedido depende del
     * negocio, no del cliente -- todavía no se confirma el costo de envío,
     * o el cliente ya mandó su comprobante y solo falta que caja lo
     * verifique. AbandonedCartService no debe cancelar el pedido (ni
     * avisarle al cliente que "no continuó") mientras esto sea cierto: el
     * cliente ya hizo lo que le tocaba y está esperando una respuesta.
     */
    public function isWaitingOnBusiness(): bool
    {
        return $this->hasPendingFulfillmentCosts() || $this->hasPaymentProof();
    }

    /** Autoservicio del cliente (no un admin): solo antes de que caja marque el pedido como pagado o en preparación. */
    public function isCancelableBySelfService(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PAYMENT_PENDING], true);
    }
}
