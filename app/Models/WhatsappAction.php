<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappAction extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'requires_product',
        'metadata',
        'is_active'
    ];

    protected $casts = [
        'requires_product' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Obtiene los botones asociados a esta acción
     */
    public function buttons(): HasMany
    {
        return $this->hasMany(WhatsappButton::class, 'action_id');
    }

    /**
     * Obtiene todas las acciones activas de un tipo específico
     */
    public static function getActiveByType(string $type): array
    {
        return self::where('type', $type)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }
}
