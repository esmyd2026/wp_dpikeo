<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappButton extends Model
{
    protected $fillable = [
        'business_profile_id',
        'action_id',
        'title',
        'icon',
        'type',
        'is_active',
        'order',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array'
    ];

    /**
     * Obtiene el perfil de negocio al que pertenece el botón
     */
    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    /**
     * Obtiene la acción asociada al botón
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(WhatsappAction::class);
    }

    /**
     * Formatea el botón para WhatsApp con el ID del producto si es necesario
     */
    public function formatForWhatsAppWithProduct(?int $productId = null): array
    {
        $buttonId = $this->action->requires_product && $productId
            ? $this->action->code . '_' . $productId
            : $this->action->code;

        return [
            'type' => $this->type,
            'reply' => [
                'id' => $buttonId,
                'title' => $this->icon ? $this->icon . ' ' . $this->title : $this->title
            ]
        ];
    }

    /**
     * Obtiene los botones activos para un perfil de negocio
     */
    public static function getActiveButtonsForProfile(int $businessProfileId, ?int $productId = null): array
    {
        return self::with('action')
            ->where('business_profile_id', $businessProfileId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function ($button) use ($productId) {
                return $button->formatForWhatsAppWithProduct($productId);
            })
            ->toArray();
    }
}
