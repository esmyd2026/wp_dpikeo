<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappMenu extends Model
{
     protected $fillable = [
        'business_profile_id',
        'title',
        'description',
        'type',
        'content',
        'button_text',
        'icon',
        'action_id',
        'order',
        'is_active',
        'metadata',
        'image'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean'
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WhatsappMenuItem::class, 'menu_id');
    }
}
