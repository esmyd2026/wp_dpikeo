<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCartItem extends Model
{
    protected $fillable = [
        'whatsapp_cart_id',
        'whatsapp_price_id',
        'name',
        'price',
        'quantity',
        'line_note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WhatsappCart::class, 'whatsapp_cart_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(WhatsappPrice::class, 'whatsapp_price_id');
    }

    /** Catálogo vinculado (alias para evitar conflicto con la columna `price`). */
    public function product(): BelongsTo
    {
        return $this->price();
    }
}
