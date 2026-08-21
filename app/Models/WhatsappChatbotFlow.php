<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappChatbotFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'trigger_keyword',
        'flow_steps',
        'is_active'
    ];

    protected $casts = [
        'flow_steps' => 'json'
    ];
}
