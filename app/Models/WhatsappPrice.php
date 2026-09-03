<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappPrice extends Model
{
    protected $fillable = [
        'menu_item_id',
        'business_profile_id',
        'franchise_id',
        'category',
        'sku',
        'name',
        'description',
        'benefits',
        'characteristics',
        'price',
        'promo_price',
        'currency',
        'is_promo',
        'promo_start_date',
        'promo_end_date',
        'is_active',
        'demo_cliente',
        'stock',
        'allow_quantity_selection',
        'min_quantity',
        'max_quantity',
        'image',
        'metadata',
    ];

    protected $casts = [
        'is_promo' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'float',
        'promo_price' => 'float',
        'metadata' => 'array',
        'characteristics' => 'array',
        'stock' => 'integer',
        'allow_quantity_selection' => 'boolean',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'promo_start_date' => 'date',
        'promo_end_date' => 'date',
    ];

    protected $appends = ['icon', 'image_url'];

    public function getIconAttribute(): string
    {
        if ($this->relationLoaded('menuCategory') && $this->menuCategory?->icon) {
            return $this->menuCategory->icon;
        }

        if ($this->menu_item_id) {
            $icon = WhatsappMenuItem::whereKey($this->menu_item_id)->value('icon');

            return $icon ?: '📦';
        }

        return '📦';
    }

    public function getImageUrlAttribute(): ?string
    {
        return app(\App\Services\ProductImageService::class)->resolveUrl($this->image);
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(WhatsappMenuItem::class, 'menu_item_id')
            ->withDefault([
                'id' => null,
                'title' => 'Sin categoría',
                'description' => null,
                'icon' => null
            ]);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function scopeForBusinessProfile($query, int $businessProfileId)
    {
        return $query->where('business_profile_id', $businessProfileId);
    }

    /** Pedidos históricos que conservan este producto como referencia. */
    public function cartItems(): HasMany
    {
        return $this->hasMany(WhatsappCartItem::class, 'whatsapp_price_id');
    }

    /** @deprecated Use menuCategory() — alias kept for compatibility */
    public function category(): BelongsTo
    {
        return $this->menuCategory();
    }

    public function hasStock(): bool
    {
        return $this->stock > 0;
    }

    public function canSelectQuantity(): bool
    {
        return $this->allow_quantity_selection;
    }

    public function isQuantityValid(int $quantity): bool
    {
        return $quantity >= $this->min_quantity && $quantity <= $this->max_quantity;
    }

    /**
     * Estadísticas globales del catálogo (misma fuente en productos y categorías).
     */
    public static function summaryStats(?int $businessProfileId = null): array
    {
        $base = static::query();
        if ($businessProfileId) {
            $base->where('business_profile_id', $businessProfileId);
        }

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'promo' => (clone $base)->where('is_promo', true)->count(),
            'no_stock' => (clone $base)->where('stock', '<=', 0)->count(),
        ];
    }

    /**
     * Productos vinculados a categorías del menú prices_menu del bot.
     */
    public static function inCatalogCategoriesCount(bool $activeOnly = false, ?int $businessProfileId = null): int
    {
        $menuQuery = WhatsappMenu::where('action_id', 'prices_menu');
        if ($businessProfileId) {
            $menuQuery->where('business_profile_id', $businessProfileId);
        }
        $menuId = $menuQuery->value('id');

        if (!$menuId) {
            return 0;
        }

        $categoryIds = WhatsappMenuItem::where('menu_id', $menuId)->pluck('id');

        $query = static::query()->whereIn('menu_item_id', $categoryIds);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->count();
    }
}
