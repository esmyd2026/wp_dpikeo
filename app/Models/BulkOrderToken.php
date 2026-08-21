<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BulkOrderToken extends Model
{
    protected $fillable = [
        'contact_id',
        'token',
        'expires_at',
        'used_at',
        'whatsapp_cart_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WhatsappCart::class, 'whatsapp_cart_id');
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function markUsed(?int $cartId = null): void
    {
        $this->used_at = now();
        if ($cartId !== null) {
            $this->whatsapp_cart_id = $cartId;
        }
        $this->save();
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
