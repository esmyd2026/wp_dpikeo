<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'contact_id',
        'status',
        'last_message_at',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime'
    ];

    public function businessProfile()
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function contact()
    {
        return $this->belongsTo(WhatsappContact::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
