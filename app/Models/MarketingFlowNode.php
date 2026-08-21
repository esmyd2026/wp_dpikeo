<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFlowNode extends Model
{
    public const TYPE_START = 'start';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_BUTTON_MENU = 'button_menu';
    public const TYPE_LIST_MENU = 'list_menu';
    public const TYPE_CATALOG = 'catalog';
    public const TYPE_CART = 'cart';
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_ORDER_STATUS = 'order_status';
    public const TYPE_PAYMENT_PROOF = 'payment_proof';
    public const TYPE_AGENT_HANDOFF = 'agent_handoff';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CATEGORY = 'category';

    // Tipos "de sistema": envuelven lógica de negocio existente (catálogo real,
    // carrito, checkout, etc.) y no admiten botones/filas editados a mano.
    // Como máximo un nodo habilitado de cada uno de estos tipos por flujo.
    public const SYSTEM_TYPES = [
        self::TYPE_CATALOG,
        self::TYPE_CART,
        self::TYPE_CHECKOUT,
        self::TYPE_ORDER_STATUS,
        self::TYPE_PAYMENT_PROOF,
        self::TYPE_AGENT_HANDOFF,
    ];

    // "Producto" y "Categoría" también están respaldados por datos reales del
    // catálogo, pero a diferencia de los tipos de sistema no son únicos: se
    // pueden crear tantos como se quiera (uno por cada producto/categoría que
    // se quiera destacar en el flujo).
    public const CATALOG_TYPES = [
        self::TYPE_PRODUCT,
        self::TYPE_CATEGORY,
    ];

    protected $fillable = [
        'flow_id',
        'node_uuid',
        'node_type',
        'name',
        'message_template',
        'config',
        'position_x',
        'position_y',
        'is_enabled',
        'is_start',
    ];

    protected $casts = [
        'config' => 'array',
        'is_enabled' => 'boolean',
        'is_start' => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(MarketingFlow::class, 'flow_id');
    }

    public function isSystemNode(): bool
    {
        return in_array($this->node_type, self::SYSTEM_TYPES, true);
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

    public function findMenuRow(string $handleId): ?array
    {
        foreach ($this->getButtons() as $button) {
            if (($button['id'] ?? '') === $handleId) {
                return $button;
            }
        }

        foreach ($this->getListConfig()['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                if (($row['id'] ?? '') === $handleId) {
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

    public static function interpolate(string $template, array $variables = []): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }
}
