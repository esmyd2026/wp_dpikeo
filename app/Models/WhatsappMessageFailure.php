<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessageFailure extends Model
{
    protected $fillable = [
        'business_profile_id',
        'contact_id',
        'phone_number',
        'message_type',
        'source',
        'error_message',
        'context',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'context' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function markResolved(?int $userId): void
    {
        $this->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ])->save();
    }
}
