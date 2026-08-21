<?php

namespace App\Enums;

class MarketingStepKey
{
    public const WELCOME = 'welcome';
    public const MAIN_MENU = 'main_menu';
    public const PRODUCTS_MENU = 'products_menu';
    public const ORDERS_MENU = 'orders_menu';
    public const INFO_MENU = 'info_menu';
    public const CART_SUMMARY = 'cart_summary';
    public const CHECKOUT = 'checkout';
    public const PAYMENT_PROOF = 'payment_proof';
    public const AGENT_HANDOFF = 'agent_handoff';
    public const FALLBACK_MESSAGE = 'fallback_message';

    public static function all(): array
    {
        return [
            self::WELCOME => 'Bienvenida inicial',
            self::MAIN_MENU => 'Menú principal',
            self::PRODUCTS_MENU => 'Catálogo de productos',
            self::ORDERS_MENU => 'Estado de pedidos',
            self::INFO_MENU => 'Información y ayuda',
            self::CART_SUMMARY => 'Resumen del carrito',
            self::CHECKOUT => 'Proceso de pago',
            self::PAYMENT_PROOF => 'Comprobante de pago',
            self::AGENT_HANDOFF => 'Derivar a asesor',
            self::FALLBACK_MESSAGE => 'Mensaje no reconocido',
        ];
    }

    public static function scenarioGroups(): array
    {
        return [
            1 => [
                'label' => 'Bienvenida y navegación',
                'color' => '#128c7e',
                'steps' => [self::WELCOME, self::MAIN_MENU],
            ],
            2 => [
                'label' => 'Ventas y catálogo',
                'color' => '#027eb5',
                'steps' => [self::PRODUCTS_MENU, self::CART_SUMMARY, self::CHECKOUT],
            ],
            3 => [
                'label' => 'Pedidos e información',
                'color' => '#6f42c1',
                'steps' => [self::ORDERS_MENU, self::INFO_MENU],
            ],
            4 => [
                'label' => 'Pagos y soporte',
                'color' => '#fd7e14',
                'steps' => [self::PAYMENT_PROOF, self::AGENT_HANDOFF, self::FALLBACK_MESSAGE],
            ],
        ];
    }

    public static function icons(): array
    {
        return [
            self::WELCOME => 'fa-hand-sparkles',
            self::MAIN_MENU => 'fa-house',
            self::PRODUCTS_MENU => 'fa-bag-shopping',
            self::ORDERS_MENU => 'fa-box',
            self::INFO_MENU => 'fa-circle-info',
            self::CART_SUMMARY => 'fa-cart-shopping',
            self::CHECKOUT => 'fa-credit-card',
            self::PAYMENT_PROOF => 'fa-receipt',
            self::AGENT_HANDOFF => 'fa-headset',
            self::FALLBACK_MESSAGE => 'fa-comment-dots',
        ];
    }

    public static function defaultOrder(): array
    {
        return array_keys(self::all());
    }

    public static function templateVariables(string $stepKey): array
    {
        $common = ['nombre', 'nombre_bot', 'nombre_empresa', 'telefono_soporte', 'horario_atencion'];

        return match ($stepKey) {
            self::PRODUCTS_MENU => array_merge($common, ['total_productos', 'total_categorias']),
            self::CART_SUMMARY, self::CHECKOUT => array_merge($common, ['total', 'moneda', 'cantidad_items']),
            self::ORDERS_MENU, self::PAYMENT_PROOF => array_merge($common, ['numero_pedido', 'estado_pedido', 'total', 'moneda', 'metodo_pago']),
            default => $common,
        };
    }
}
