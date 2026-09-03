<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappMenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'business_profile_id',
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

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function scopeForBusinessProfile($query, int $businessProfileId)
    {
        return $query->where('business_profile_id', $businessProfileId);
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
     * Categorías del catálogo de productos (menú prices_menu del bot) de una
     * empresa. Sin $businessProfileId cae al primer prices_menu que
     * encuentre -- solo correcto mientras exista una única empresa con
     * catálogo; todo llamador nuevo debe pasar el id explícito.
     */
    public function scopeCatalogCategories($query, ?int $businessProfileId = null)
    {
        $menuQuery = WhatsappMenu::where('action_id', 'prices_menu');
        if ($businessProfileId) {
            $menuQuery->where('business_profile_id', $businessProfileId);
        }
        $menuId = $menuQuery->value('id');

        if (!$menuId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('menu_id', $menuId);

        if ($businessProfileId) {
            $query->where('business_profile_id', $businessProfileId);
        }

        return $query;
    }
}
