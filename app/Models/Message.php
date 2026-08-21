<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'content',
        'sender_type',
        'contact_id',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }
}
