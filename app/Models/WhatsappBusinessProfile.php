<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappBusinessProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_business_id',
        'phone_number',
        'phone_number_id',
        'business_name',
        'display_name',
        'status',
        'access_token',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function conversations()
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    public function contacts()
    {
        return $this->hasMany(WhatsappContact::class);
    }

    /** Sucursales operativas de DPIKEOS (matriz, locales y futuros puntos). */
    public function branches(): HasMany
    {
        return $this->hasMany(BusinessBranch::class, 'business_profile_id');
    }

    /** Enlace público wa.me para iniciar conversación con el bot. */
    public static function publicWhatsAppLink(?string $message = null): ?array
    {
        $profile = static::query()->first();
        $raw = $profile?->phone_number ?: config('whatsapp.phone_number');
        $digits = preg_replace('/\D/', '', (string) $raw);

        if ($digits === '') {
            return null;
        }

        $message ??= 'Hola';

        return [
            'digits' => $digits,
            'display_number' => '+' . $digits,
            'label' => $profile?->display_name
                ?? $profile?->business_name
                ?? 'Bot WhatsApp',
            'url' => 'https://wa.me/' . $digits . '?text=' . rawurlencode($message),
        ];
    }
}
