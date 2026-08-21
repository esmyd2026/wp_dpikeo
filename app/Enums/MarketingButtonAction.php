<?php

namespace App\Enums;

class MarketingButtonAction
{
    public const PRODUCTS = 'products';
    public const ORDERS = 'orders';
    public const INFO = 'info';
    public const MAIN_MENU = 'main_menu';
    public const AGENT = 'agent';
    public const VIEW_CART = 'view_cart';
    public const CHECKOUT = 'checkout';
    public const CATALOG = 'catalog';

    public static function labels(): array
    {
        return [
            self::PRODUCTS => 'Abrir catálogo de productos',
            self::ORDERS => 'Ver estado de pedidos',
            self::INFO => 'Información y ayuda',
            self::MAIN_MENU => 'Volver al menú principal',
            self::AGENT => 'Derivar a asesor humano',
            self::VIEW_CART => 'Ver carrito de compras',
            self::CHECKOUT => 'Iniciar proceso de pago',
            self::CATALOG => 'Mostrar categorías del catálogo',
        ];
    }

    public static function legacyIdMap(): array
    {
        return [
            'menu_productos' => self::PRODUCTS,
            'productos' => self::PRODUCTS,
            'menu_pedido' => self::ORDERS,
            'menu_info' => self::INFO,
            'menu_principal' => self::MAIN_MENU,
            'return_to_menu' => self::MAIN_MENU,
            'ver_carrito' => self::VIEW_CART,
            'checkout' => self::CHECKOUT,
            'catalogo' => self::CATALOG,
            'agent' => self::AGENT,
            'menu_agent' => self::AGENT,
        ];
    }

    public static function resolve(string $buttonId, ?string $configuredAction = null): string
    {
        if ($configuredAction && str_starts_with($configuredAction, 'custom:')) {
            return $configuredAction;
        }

        if ($configuredAction && array_key_exists($configuredAction, self::labels())) {
            return $configuredAction;
        }

        return self::legacyIdMap()[$buttonId] ?? $buttonId;
    }
}
