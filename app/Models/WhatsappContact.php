<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * También sirve como identidad de login del cliente en el storefront (guard
 * "storefront_customer", ver config/auth.php) -- un contacto puede existir
 * sin password (creado solo por escribirle al bot o hacer un pedido); recién
 * tiene cuenta real cuando "reclama" su registro con una contraseña desde
 * StorefrontAccountController::register().
 */
class WhatsappContact extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory;

    protected $fillable = [
        'business_profile_id',
        'phone_number',
        'name',
        'national_id',
        'address',
        'birth_date',
        'billing_type',
        'billing_id',
        'billing_legal_name',
        'billing_email',
        'status',
        'bot_enabled',
        'last_inbound_message_id',
        'last_inbound_at',
        'metadata',
        'password',
        'google_id',
        'google_email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'metadata' => 'array',
        'bot_enabled' => 'boolean',
        'last_inbound_at' => 'datetime',
        'birth_date' => 'date',
        'password' => 'hashed',
    ];

    public function hasAccountPassword(): bool
    {
        return filled($this->password);
    }

    /**
     * Un mismo número real llegaba con formatos distintos según el canal
     * (WhatsApp/Meta siempre manda "593988492339"; el micrositio, el pedido
     * por admin o el POS podían recibir "0988492339" o "988492339" tal como
     * lo escribió el cliente) -- eso creaba un WhatsappContact nuevo por
     * cada formato en vez de reconocer al mismo cliente. Se normaliza todo
     * al formato que ya usa Meta (código de país 593 sin '+', sin el 0
     * inicial) antes de buscar o crear un contacto por teléfono.
     *
     * Devuelve null si no parece un número válido (8 a 15 dígitos).
     */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '593'.substr($digits, 1);
        } elseif (strlen($digits) === 9 && ! str_starts_with($digits, '0')) {
            $digits = '593'.$digits;
        }

        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? $digits : null;
    }

    public function businessProfile()
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class, 'contact_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(WhatsappMessage::class, 'contact_id')->latestOfMany('created_at');
    }

    public function conversations()
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    public function carts()
    {
        return $this->hasMany(WhatsappCart::class, 'contact_id');
    }

    public function notes()
    {
        return $this->hasMany(WhatsappContactNote::class, 'contact_id');
    }

    public function storefrontAddresses(): HasMany
    {
        return $this->hasMany(StorefrontCustomerAddress::class, 'contact_id');
    }

    public function needsAgent(): bool
    {
        return ! empty($this->metadata['needs_agent']);
    }

    public function requestAgentHandoff(string $source = 'unknown'): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['needs_agent'] = true;
        $metadata['agent_requested_at'] = now()->toIso8601String();
        $metadata['agent_request_source'] = $source;
        $this->metadata = $metadata;
        $this->save();
    }

    public function clearAgentRequest(?int $handledByUserId = null): void
    {
        $metadata = $this->metadata ?? [];
        $hadRequest = ! empty($metadata['needs_agent']);

        unset($metadata['needs_agent'], $metadata['agent_requested_at'], $metadata['agent_request_source']);

        if ($handledByUserId) {
            $metadata['agent_handled_by'] = $handledByUserId;
            $metadata['agent_handled_at'] = now()->toIso8601String();
        }

        if (! $hadRequest && ! isset($metadata['agent_handled_by'])) {
            return;
        }

        $this->metadata = $metadata;
        $this->save();
    }

    public function wasWelcomedToday(): bool
    {
        $lastWelcomeDate = ($this->metadata ?? [])['last_welcome_date'] ?? null;

        return $lastWelcomeDate === now()->toDateString();
    }

    public function markWelcomedToday(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['last_welcome_date'] = now()->toDateString();
        $this->metadata = $metadata;
        $this->save();
    }

    public function getLastBranchId(): ?int
    {
        $branchId = ($this->metadata ?? [])['last_branch_id'] ?? null;

        return $branchId ? (int) $branchId : null;
    }

    public function rememberBranch(int $branchId): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['last_branch_id'] = $branchId;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * true si ya se le envió el aviso de protección de datos alguna vez
     * (ver WhatsappService::maybeSendPrivacyNotice). Es un consentimiento
     * legal por contacto, no algo ligado a un pedido puntual: NO se borra
     * solo porque su carrito se cancela/expira (AbandonedCartService::close
     * ya no lo toca) -- solo un admin puede reenviarlo a propósito
     * reiniciando la conversación desde el panel.
     */
    public function hasReceivedPrivacyNotice(): bool
    {
        return ! empty($this->metadata['privacy_notice_sent_at']);
    }

    public function markPrivacyNoticeSent(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['privacy_notice_sent_at'] = now()->toIso8601String();
        $this->metadata = $metadata;
        $this->save();
    }

    public function forgetPrivacyNoticeSent(): void
    {
        $metadata = $this->metadata ?? [];
        if (! array_key_exists('privacy_notice_sent_at', $metadata)) {
            return;
        }

        unset($metadata['privacy_notice_sent_at']);
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Olvida en qué nodo del flujo visual (grafo) quedó este contacto, para
     * que su próximo mensaje arranque desde el inicio en vez de seguir
     * "atascado" donde lo dejó. Se usa al cerrar un carrito abandonado/atascado
     * (ver AbandonedCartService), manual o automáticamente.
     */
    public function forgetFlowPosition(): void
    {
        $metadata = $this->metadata ?? [];
        if (! array_key_exists('current_graph_node', $metadata)) {
            return;
        }

        unset($metadata['current_graph_node']);
        $this->metadata = $metadata;
        $this->save();
    }
}
