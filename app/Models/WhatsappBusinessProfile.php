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
        'two_factor_pin',
        'connected_at',
        'disconnected_at',
        'last_verified_at',
        'last_verification_status',
        'is_primary',
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
        'two_factor_pin' => 'encrypted',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    /**
     * "Usable" = puede autenticar un envío real. Únicamente status=connected
     * califica -- 'pending' (nunca tuvo credenciales completas), 'error'
     * (Graph API rechazó algo) y 'disconnected' (dado de baja localmente)
     * quedan afuera. El vocabulario legacy 'active' ya no existe en datos
     * reales (ver migración de normalización); fixtures/tests deben usar
     * STATUS_CONNECTED explícitamente.
     */
    public function scopeUsable($query)
    {
        return $query->where('status', self::STATUS_CONNECTED);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

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
    /**
     * Resuelve el número de la EMPRESA ACTIVA de la sesión (nunca "la primera
     * fila de toda la tabla" -- eso mezclaba el número de otra empresa
     * apenas hubiera más de una). Si no hay contexto de empresa resuelto
     * (usuario sin sesión, o empresa con 2+ números y ninguno principal),
     * cae al .env solo como último recurso, igual que antes.
     */
    public static function publicWhatsAppLink(?string $message = null): ?array
    {
        $profile = null;
        try {
            $profile = \App\Support\CompanyContext::current()->businessProfile;
        } catch (\Throwable $e) {
            // Sin contexto de empresa resoluble: se sigue con el fallback de abajo.
        }

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
