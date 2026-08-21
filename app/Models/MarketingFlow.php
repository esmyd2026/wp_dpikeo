<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingFlow extends Model
{
    protected $fillable = [
        'business_profile_id',
        'name',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class, 'business_profile_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MarketingFlowStep::class, 'flow_id')->orderBy('sort_order');
    }

    public function enabledSteps(): HasMany
    {
        return $this->steps()->where('is_enabled', true);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(MarketingFlowNode::class, 'flow_id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(MarketingFlowEdge::class, 'flow_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MarketingFlowVersion::class, 'flow_id')->orderByDesc('version_number');
    }
}
