<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappBusinessProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'whatsapp_business_id',
        'phone_number',
        'phone_number_id',
        'business_name',
        'display_name',
        'status',
        'connection_type',
        'access_token',
        'connected_at',
        'metadata'
    ];

    /** Vocabulario normalizado de whatsapp_business_profiles.status. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR = 'error';
    public const STATUS_REQUIRES_ACTION = 'requires_action';

    protected $casts = [
        'metadata' => 'array',
        'access_token' => 'encrypted',
        'connected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

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

    /** Sucursales operativas de esta empresa (matriz, locales y futuros puntos). */
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
