<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFlowStep extends Model
{
    protected $fillable = [
        'flow_id',
        'step_key',
        'name',
        'message_template',
        'sort_order',
        'is_enabled',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(MarketingFlow::class, 'flow_id');
    }

    public function getInteractiveType(): string
    {
        return $this->config['interactive_type'] ?? 'button';
    }

    public function getButtons(): array
    {
        return $this->config['buttons'] ?? [];
    }

    public function getListConfig(): array
    {
        return $this->config['list'] ?? [];
    }

    public function getFlowConfig(): array
    {
        return $this->config['flow'] ?? [];
    }

    public function getCtaConfig(): array
    {
        return $this->config['cta_url'] ?? [];
    }

    public function getHeaderMode(): string
    {
        if (isset($this->config['header_mode'])) {
            return $this->config['header_mode'];
        }

        $header = $this->config['header'] ?? null;
        if (!$header) {
            return 'default';
        }

        if (($header['type'] ?? null) === 'image' && !empty($header['image_path'])) {
            return 'image';
        }

        if (!empty($header['text'])) {
            return 'text';
        }

        return 'none';
    }

    public function getHeaderImageUrl(): ?string
    {
        $path = $this->config['header']['image_path'] ?? null;

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function getMessageImageUrl(): ?string
    {
        $path = $this->config['message_image_path'] ?? null;

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function getRenderedHeader(array $variables = []): ?array
    {
        $header = $this->config['header'] ?? null;
        if (!$header) {
            return null;
        }

        $type = $header['type'] ?? (!empty($header['text']) ? 'text' : null);

        if ($type === 'image' && !empty($header['image_path'])) {
            return [
                'type' => 'image',
                '_image_path' => $header['image_path'],
            ];
        }

        if (!empty($header['text'])) {
            return [
                'type' => 'text',
                'text' => self::interpolate($header['text'], $variables),
            ];
        }

        return null;
    }

    public function getRenderedFooter(array $variables = []): ?string
    {
        $footer = $this->config['footer'] ?? null;

        return $footer !== null && $footer !== ''
            ? self::interpolate($footer, $variables)
            : null;
    }

    public function findMenuRow(string $buttonId): ?array
    {
        foreach ($this->getButtons() as $button) {
            if (($button['id'] ?? '') === $buttonId) {
                return $button;
            }
        }

        foreach ($this->getListConfig()['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                if (($row['id'] ?? '') === $buttonId) {
                    return $row;
                }
            }
        }

        return null;
    }

    public function renderMessage(array $variables = []): string
    {
        return self::interpolate($this->message_template ?? '', $variables);
    }

    public function getCustomActions(): array
    {
        return $this->config['custom_actions'] ?? [];
    }

    public function isPaymentProofEnabled(): bool
    {
        return (bool) ($this->config['require_proof'] ?? false);
    }

    /** @return string[] */
    public function paymentProofRequiredMethods(): array
    {
        return $this->config['require_for_methods'] ?? ['transferencia', 'tarjeta'];
    }

    public function requiresPaymentProofForMethod(?string $method): bool
    {
        if (!$this->isPaymentProofEnabled() || !$method) {
            return false;
        }

        return in_array($method, $this->paymentProofRequiredMethods(), true);
    }

    public function getPaymentProofSuccessMessage(array $variables = []): string
    {
        $message = $this->config['success_message']
            ?? '✅ Comprobante recibido. Lo verificaremos y te confirmaremos pronto.';

        return self::interpolate($message, $variables);
    }

    public static function interpolate(string $template, array $variables = []): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }
}
