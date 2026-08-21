<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappMenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'franchise_id',
        'parent_id',
        'title',
        'description',
        'action_id',
        'icon',
        'image',
        'order',
        'is_active',
        'demo_cliente'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return app(\App\Services\ProductImageService::class)->resolveUrl($this->image);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(WhatsappMenu::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WhatsappMenuItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(WhatsappMenuItem::class, 'parent_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(WhatsappPrice::class, 'menu_item_id');
    }

    /**
     * Categorías del catálogo de productos (menú prices_menu del bot).
     */
    public function scopeCatalogCategories($query)
    {
        $menuId = WhatsappMenu::where('action_id', 'prices_menu')->value('id');

        if (!$menuId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('menu_id', $menuId);
    }
}
