<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCartNote extends Model
{
    public const TYPE_INTERNAL = 'internal';

    public const TYPE_FEEDBACK = 'feedback';

    protected $fillable = [
        'whatsapp_cart_id',
        'user_id',
        'type',
        'body',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WhatsappCart::class, 'whatsapp_cart_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_FEEDBACK => 'Feedback con cliente',
            default => 'Observación interna',
        };
    }
}
