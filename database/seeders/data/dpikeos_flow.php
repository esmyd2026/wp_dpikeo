<?php

use App\Enums\MarketingStepKey;

/**
 * Flujo guiado de DPIKEOS. No depende de IA: cada avance se realiza mediante
 * botones, listas de WhatsApp o la configuración del producto en el panel.
 */
return [
    MarketingStepKey::WELCOME => [
        'message' => "¡Hola, *{{nombre}}*! 👋\n\nBienvenido a *DPIKEOS* y al *Club Dpikeolovers*.\n\nElige lo que se te antoja y arma tu pedido en pocos pasos. 🍗🍔",
        'type' => 'text',
    ],
    MarketingStepKey::MAIN_MENU => [
        'message' => "¿Qué se te antoja hoy? 😋\n\nMira el menú por categorías o abre el pedido para compartir.",
        'type' => 'button',
        'buttons' => [
            ['id' => 'menu_productos', 'title' => '🍗 Ver menú', 'action' => 'products'],
            ['id' => 'bulk_order_web', 'title' => '🛒 Pedido múltiple'],
        ],
    ],
    MarketingStepKey::PRODUCTS_MENU => [
        'message' => "Elige una categoría. Verás los productos, sus fotos y el precio antes de agregarlo.",
        'type' => 'list',
        'catalog_source' => 'categories',
        'max_product_rows' => 8,
        'include_navigation' => true,
        // Pegar el ID publicado de Meta desde el panel: Flujo del bot.
        'quick_order_flow' => ['flow_id' => null, 'cta' => 'Pedir ahora'],
        'list' => ['button' => 'Ver el menú', 'sections' => []],
    ],
    MarketingStepKey::ORDERS_MENU => [
        'message' => "*Tu pedido DPIKEOS* 📦\n\nAquí puedes consultar el estado de tu pedido. Si necesitas ayuda con una entrega, selecciona *Hablar con el equipo* desde el menú principal.",
        'type' => 'text',
    ],
    MarketingStepKey::INFO_MENU => [
        'message' => "*Club Dpikeolovers* ✨\n\nSelecciona una opción:",
        'type' => 'list',
        'list' => [
            'button' => 'Ver opciones',
            'sections' => [[
                'title' => 'DPIKEOS',
                'rows' => [
                    ['id' => 'promociones', 'title' => '🔥 Promociones', 'description' => 'Ofertas disponibles', 'action' => 'promotions'],
                    ['id' => 'contacto', 'title' => '📲 Contacto', 'description' => 'Habla con el equipo', 'action' => 'contacto'],
                    ['id' => 'redes', 'title' => '📸 Instagram', 'description' => '@dpikeos_', 'action' => 'redes'],
                ],
            ]],
        ],
    ],
    // Ojo: este paso solo se usa con el carrito VACÍO (ver
    // WhatsappService::finalizarCompra/getCartContents) -- con productos
    // agregados, "Ver carrito"/"Finalizar compra" saltan directo al
    // checkout, sin pasar por aquí. El copy y los botones deben tener
    // sentido para "todavía no agregaste nada", no para revisar un pedido.
    MarketingStepKey::CART_SUMMARY => [
        'message' => "Todavía no agregaste nada a tu pedido, *{{nombre}}* 🛒\n\n¿Qué se te antoja hoy?",
        'type' => 'button',
        'buttons' => [
            ['id' => 'menu_productos', 'title' => '🍗 Ver menú', 'action' => 'products'],
            ['id' => 'menu_principal', 'title' => '🏠 Inicio', 'action' => 'main_menu'],
        ],
    ],
    MarketingStepKey::CHECKOUT => [
        'message' => "*Finaliza tu pedido* 🍗\n\nElige tu método de pago. Después confirmaremos contigo los datos de entrega o retiro y el tiempo de preparación.",
        'type' => 'text',
    ],
    MarketingStepKey::PAYMENT_PROOF => [
        'message' => "*Comprobante de pago*\n\nPedido: *{{numero_pedido}}*\nTotal: *{{moneda}} {{total}}*\n\nEnvía la captura o comprobante para verificarlo.",
        'type' => 'text',
        'require_proof' => true,
        'require_for_methods' => ['transferencia', 'tarjeta'],
        'success_message' => "¡Listo, Dpikeolover! ✨\n\nRecibimos tu comprobante del pedido *{{numero_pedido}}*. El equipo lo verificará y te confirmará la preparación por este chat.",
    ],
    MarketingStepKey::AGENT_HANDOFF => [
        'message' => "*Te conectamos con DPIKEOS* 👋\n\nUn miembro del equipo revisará tu mensaje y continuará la atención por este chat.",
        'type' => 'text',
    ],
    MarketingStepKey::FALLBACK_MESSAGE => [
        'message' => "No quiero que te pierdas del menú 😊\n\nUsa los botones para pedir, revisar tu carrito o ver promociones.",
        'type' => 'button',
        'buttons' => [
            ['id' => 'menu_productos', 'title' => '🍗 Ver menú', 'action' => 'products'],
            ['id' => 'ver_carrito', 'title' => '🛒 Mi carrito', 'action' => 'view_cart'],
            ['id' => 'menu_principal', 'title' => '🏠 Inicio', 'action' => 'main_menu'],
        ],
    ],
];
