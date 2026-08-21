<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TYPE_SALE_RESERVATION = 'sale_reservation';
    public const TYPE_SALE_RELEASE = 'sale_release';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        'whatsapp_price_id', 'whatsapp_cart_id', 'user_id', 'type', 'quantity',
        'stock_before', 'stock_after', 'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(WhatsappPrice::class, 'whatsapp_price_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WhatsappCart::class, 'whatsapp_cart_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
