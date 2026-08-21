<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappChatbotResponse extends Model
{
    protected $fillable = [
        'keyword',
        'response',
        'type',
        'is_active',
        'order',
        'metadata',
        'contacts',
        'show_menu'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'contacts' => 'array',
        'show_menu' => 'boolean'
    ];

    public static function findResponse($message)
    {
        return static::where('is_active', true)
            ->where(function($query) use ($message) {
                $query->where('keyword', 'LIKE', "%{$message}%")
                    ->orWhere('keyword', '=', $message);
            })
            ->orderBy('order')
            ->first();
    }
}
