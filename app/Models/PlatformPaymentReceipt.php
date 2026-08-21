<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlatformPaymentReceipt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const FOR_PLAN = 'plan';

    public const FOR_META = 'meta';

    public const FOR_BOTH = 'both';

    public const METHOD_TRANSFER = 'transferencia';

    public const METHOD_DEPOSIT = 'deposito';

    public const METHOD_CASH = 'efectivo';

    protected $fillable = [
        'user_id',
        'payment_for',
        'payment_method',
        'amount',
        'paid_at',
        'receipt_path',
        'status',
        'client_notes',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function paymentForLabel(): string
    {
        return match ($this->payment_for) {
            self::FOR_META => 'Mensajería Meta',
            self::FOR_BOTH => 'Plan + Meta',
            default => 'Plan de plataforma',
        };
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            self::METHOD_DEPOSIT => 'Depósito',
            self::METHOD_CASH => 'Efectivo',
            default => 'Transferencia',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_REJECTED => 'Rechazado',
            default => 'Pendiente',
        };
    }

    public function receiptUrl(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_path);
    }
}
