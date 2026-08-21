<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFlowVersion extends Model
{
    protected $fillable = [
        'flow_id',
        'version_number',
        'snapshot',
        'published_by',
        'published_at',
        'is_current',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'published_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(MarketingFlow::class, 'flow_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
