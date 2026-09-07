<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeliveryConfirmationToken extends Model
{
    protected $fillable = [
        'whatsapp_cart_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WhatsappCart::class, 'whatsapp_cart_id');
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function markUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
