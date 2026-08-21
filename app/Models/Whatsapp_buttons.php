<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Whatsapp_buttons extends Model
{
    use HasFactory;
    protected $table = 'whatsapp_buttons';

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
     * Formatea el botón para la API de WhatsApp
     */
    public function formatForWhatsApp(): array
    {
        return [
            'type' => 'reply',
            'reply' => [
                'id' => $this->action_id,
                'title' => $this->icon ? $this->icon . ' ' . $this->title : $this->title
            ]
        ];
    }

    /**
     * Formatea el botón para la API de WhatsApp con ID de producto
     */
    public function formatForWhatsAppWithProduct(int $productId): array
    {
        // Lista de acciones que requieren ID de producto
        $productActions = ['ver_producto', 'comprar'];

        $buttonId = in_array($this->action_id, $productActions)
            ? $this->action_id . '_' . $productId
            : $this->action_id;

        return [
            'type' => 'reply',
            'reply' => [
                'id' => $buttonId,
                'title' => $this->icon ? $this->icon . ' ' . $this->title : $this->title
            ]
        ];
    }

    /**
     * Obtiene los botones activos para un perfil de negocio
     */
    public static function getActiveButtonsForProfile(int $businessProfileId): array
    {
        return self::where('business_profile_id', $businessProfileId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(fn($button) => $button->formatForWhatsApp())
            ->toArray();
    }

    /**
     * Obtiene los botones activos para un perfil de negocio con ID de producto
     */
    public static function getActiveButtonsForProfileWithProduct(int $businessProfileId, int $productId): array
    {
        return self::where('business_profile_id', $businessProfileId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(fn($button) => $button->formatForWhatsAppWithProduct($productId))
            ->toArray();
    }

    public function getButtonByActionId(string $actionId): ?self
    {
        return self::where('action_id', $actionId)
            ->where('is_active', true)
            ->first();
    }
}
