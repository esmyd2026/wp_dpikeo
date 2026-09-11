<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappChatbotConfig extends Model
{
    protected $fillable = [
        'business_profile_id',
        'welcome_message',
        'default_response',
        'greetings',
        'menu_commands',
        'metadata',
        'is_active',
        'monitoring_enabled',
        'monitoring_phone_number',
        'monitoring_email',
        'chatgpt_enabled',
        'chatgpt_api_key',
        'chatgpt_model',
        'chatgpt_system_prompt',
        'chatgpt_max_tokens',
        'chatgpt_temperature',
        'chatgpt_additional_params'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monitoring_enabled' => 'boolean',
        'chatgpt_enabled' => 'boolean',
        'greetings' => 'array',
        'menu_commands' => 'array',
        'metadata' => 'array',
        'chatgpt_additional_params' => 'array',
        'chatgpt_max_tokens' => 'integer',
        'chatgpt_temperature' => 'float'
    ];

    public function getBotNameAttribute(): ?string
    {
        return $this->metadata['bot_name'] ?? null;
    }

    /**
     * Cómo le dice esta empresa a sus clientes en mensajes de cierre de
     * pedido (ej. "Dpikeolovers" para dpikeo). Ya existía en metadata desde
     * el seeder pero no estaba conectado a ningún mensaje real -- ver
     * WhatsappService (mensajes de confirmación de pedido masivo). Sin
     * dato propio, null (el mensaje queda neutro, nunca usa el de otra empresa).
     */
    public function getCommunityNameAttribute(): ?string
    {
        $value = trim((string) ($this->metadata['community_name'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Título/subtítulo del dashboard de esta empresa. Sin dato propio, cae a
     * un texto genérico con el nombre real de la empresa -- nunca al texto
     * de otra empresa.
     */
    public function getDashboardTitleAttribute(): string
    {
        $custom = trim((string) ($this->metadata['dashboard_title'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $companyName = $this->businessProfile?->company?->name
            ?? $this->businessProfile?->business_name
            ?? 'Panel';

        return "{$companyName} · Centro de operación";
    }

    public function getDashboardSubtitleAttribute(): string
    {
        $custom = trim((string) ($this->metadata['dashboard_subtitle'] ?? ''));

        return $custom !== '' ? $custom : 'Gestiona pedidos, catálogo y atención por WhatsApp.';
    }

    public function getFallbackMessageAttribute(): ?string
    {
        return $this->default_response;
    }

    public function getResponseDelayAttribute(): int
    {
        return (int) ($this->metadata['response_delay'] ?? 1000);
    }

    /**
     * Los precios del catálogo ya incluyen IVA; esto solo controla si se le
     * muestra al cliente cuánto de cada pedido era IVA (no cambia el total).
     */
    public function getIvaEnabledAttribute(): bool
    {
        return (bool) ($this->metadata['iva_enabled'] ?? false);
    }

    public function getIvaPercentageAttribute(): float
    {
        return (float) ($this->metadata['iva_percentage'] ?? 0);
    }

    /**
     * El mensaje de confirmación del pedido ya trae un enlace para ver/
     * descargar el PDF (ver OrderPdfService::signedDownloadUrl) -- esto
     * controla si, además, se le manda el archivo PDF como documento
     * adjunto de WhatsApp. Activado por defecto (comportamiento de siempre)
     * para no romper nada hasta que el admin lo desactive a propósito.
     */
    public function getSendOrderPdfDocumentAttribute(): bool
    {
        return (bool) ($this->metadata['send_order_pdf_document'] ?? true);
    }

    /**
     * Pedido explícito: "permíteme seleccionar los sonidos desde el panel
     * administrativo". Cada evento de la pantalla de Pedidos (pedido nuevo,
     * comprobante, factura confirmada, pedido de asesor) tiene su propio
     * tono configurable -- ver los presets en public/js/admin-order-alerts.js.
     *
     * @return array<string, string>
     */
    public function getAlertSoundsAttribute(): array
    {
        $defaults = [
            'new_order' => 'fuerte',
            'payment_proof' => 'fuerte',
            'invoice_confirmed' => 'normal',
            'agent_request' => 'urgente',
        ];
        $allowed = ['suave', 'normal', 'fuerte', 'urgente'];
        $stored = is_array($this->metadata['alert_sounds'] ?? null) ? $this->metadata['alert_sounds'] : [];

        foreach (array_keys($defaults) as $key) {
            if (in_array($stored[$key] ?? null, $allowed, true)) {
                $defaults[$key] = $stored[$key];
            }
        }

        return $defaults;
    }

    /**
     * Texto libre (banco, número de cuenta, titular, Zelle, Pago Móvil, etc.)
     * que se le manda al cliente junto con el costo confirmado del pedido
     * cuando pagará por transferencia o depósito.
     */
    public function getBankTransferInstructionsAttribute(): ?string
    {
        $value = trim((string) ($this->metadata['bank_transfer_instructions'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /** Palabra clave que el equipo (no los clientes) escribe para consultar datos de delivery por WhatsApp. */
    public function getDeliveryDispatchKeywordAttribute(): string
    {
        $value = trim((string) ($this->metadata['delivery_dispatch_keyword'] ?? ''));

        return $value !== '' ? $value : '2501';
    }

    /** @return string[] Números autorizados a usar la palabra clave de delivery, ya normalizados (solo dígitos). */
    public function getDeliveryDispatchNumbersAttribute(): array
    {
        $raw = (string) ($this->metadata['delivery_dispatch_numbers'] ?? '');

        return array_values(array_filter(array_map(
            fn ($n) => preg_replace('/\D+/', '', $n),
            explode(',', $raw)
        )));
    }

    public function getPrimaryColorAttribute(): string
    {
        return self::normalizeHexColor($this->metadata['primary_color'] ?? null, '#005c4b');
    }

    public function getSecondaryColorAttribute(): string
    {
        return self::normalizeHexColor($this->metadata['secondary_color'] ?? null, '#075e54');
    }

    /** @return array{r: int, g: int, b: int} */
    public function primaryColorRgb(): array
    {
        return self::hexToRgb($this->primary_color);
    }

    public static function normalizeHexColor(?string $value, string $default): string
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $value = trim($value);

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value, $m)) {
            return '#' . strtolower($m[1]);
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $m)) {
            $c = $m[1];

            return '#' . strtolower($c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2]);
        }

        if (preg_match('/^([0-9a-fA-F]{6})$/', $value, $m)) {
            return '#' . strtolower($m[1]);
        }

        return $default;
    }

    /** @return array{r: int, g: int, b: int} */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim(self::normalizeHexColor($hex, '#000000'), '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    public function getBotAvatarAttribute(): ?string
    {
        return $this->metadata['bot_avatar'] ?? null;
    }

    public function getBotAvatarUrlAttribute(): ?string
    {
        $path = $this->metadata['bot_avatar_path'] ?? null;
        if ($path) {
            return asset('storage/' . ltrim($path, '/'));
        }

        $url = $this->metadata['bot_avatar'] ?? null;

        return $url !== null && $url !== '' ? $url : null;
    }

    public function getFontFamilyAttribute(): string
    {
        return $this->metadata['font_family'] ?? 'Arial';
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class, 'business_profile_id');
    }
}
