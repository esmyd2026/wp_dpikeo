<?php

return [
    'sales_whatsapp' => env('SALES_WHATSAPP_NUMBER', env('DEMO_WHATSAPP_NUMBER', '593994281769')),

    /*
    | Demo pública: bot WhatsApp + acceso al panel
    */
    'demo' => [
        'whatsapp_number' => env('DEMO_WHATSAPP_NUMBER', '593994281769'),
        'whatsapp_message' => '¡Hola! Quiero probar el demo del bot de WhatsApp 🤖',
        'panel_user' => env('DEMO_PANEL_USER', 'gosorio'),
        'panel_password' => env('DEMO_PANEL_PASSWORD', 'go123'),
    ],

    /*
    | Margen sobre tarifa Meta de referencia (gestión, variaciones, imprevistos)
    */
    'meta_markup' => 1.30,

    /*
    | Tipos de conversación Meta visibles para este cliente (dashboard + /planes).
    | Se puede cambiar desde el panel Tarifas Meta (super admin).
    */
    'enabled_conversation_categories' => [
        'service' => true,
        'utility' => true,
        'marketing' => false,
        'authentication' => false,
        'campaign_freeform' => true,
    ],

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'label' => 'Plan Esencial',
            'price' => 60,
            'price_label' => '$60/mes sin IVA',
            'from' => false,
            'cta' => 'Elegir Starter',
            'limits' => [
                'max_products' => 80,
                'max_categories' => 20,
                'storage_gb' => 10,
                'admin_users' => 3,
            ],
            'features' => [
                'bulk_web_order' => false,
            ],
            'includes' => [
                'Bot: menú, catálogo, carrito, notas y método de pago',
                'Bot: info negocio, seguimiento pedidos, asesor y palabras clave',
                'Panel: chats, clientes, pedidos, catálogo, flujo y usuarios',
                'Panel: dashboard, Excel, historial; uso del plan y almacenamiento aparte',
            ],
            'excludes' => [
                'Segmentación CRM automática',
                'Pedidos avanzados (factura, notas internas)',
                'Consumo Meta y colores del bot',
                'Campañas masivas',
                'Imágenes/PDF y comprobantes del cliente',
                'Integraciones y reportes avanzados',
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'label' => 'Plan Profesional',
            'price' => 90,
            'price_label' => '$90/mes sin IVA',
            'from' => false,
            'cta' => 'Elegir Pro',
            'limits' => [
                'max_products' => 200,
                'max_categories' => 50,
                'storage_gb' => 25,
                'admin_users' => 5,
            ],
            'features' => [
                'bulk_web_order' => true,
            ],
            'includes' => [
                'Todo Starter',
                'Bot: pedido masivo por formulario web (enlace desde el chat)',
                'Bot: imágenes/PDF, comprobante, campañas y envío programado',
                'Panel: segmentación, pedidos avanzados, consumo Meta y colores',
                'Panel: alertas externas, reportes y soporte ticket 48 h',
            ],
            'excludes' => [
                'Integraciones ERP / CRM',
                'Reportes ejecutivos',
                'Desarrollo a medida',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'label' => 'Plan Empresarial',
            'price' => 130,
            'price_label' => 'desde $130/mes sin IVA',
            'from' => true,
            'cta' => 'Solicitar cotización',
            'limits' => [
                'max_products' => 500,
                'max_categories' => 100,
                'storage_gb' => 50,
                'admin_users' => null,
            ],
            'features' => [
                'bulk_web_order' => true,
            ],
            'includes' => [
                'Todo Pro',
                'Bot: catálogo ampliable e IA ChatGPT (opcional)',
                'Panel: integraciones, reportes avanzados y dashboard ejecutivo',
                'Panel: usuarios ilimitados, ajustes menores y soporte prioritario',
            ],
            'excludes' => [
                'Desarrollos mayores se cotizan aparte',
            ],
        ],
    ],

    /*
    | Tarifas base Meta (USD) — en la vista se aplican +30% (meta_markup)
    */
    'meta_rates' => [
        'region' => 'Ecuador / Latam',
        'currency' => 'USD',
        'per_conversation' => [
            'service' => [
                'min' => 0.012,
                'max' => 0.022,
                'icon' => '💬',
                'label' => 'Cuando un cliente te escribe',
                'description' => 'Alguien te manda hola, pregunta precios, usa el menú o compra. El chat lo inicia la persona.',
            ],
            'utility' => [
                'min' => 0.028,
                'max' => 0.042,
                'icon' => '📋',
                'label' => 'Avisos que envía el bot',
                'description' => 'Confirmación de pedido, “tu pago fue recibido”, cambio de estado — mensajes informativos, no promociones.',
            ],
            'marketing' => [
                'min' => 0.055,
                'max' => 0.085,
                'icon' => '📢',
                'label' => 'Promociones que tú envías',
                'description' => 'Ofertas, recordatorios o campañas masivas a tu lista de contactos (plan Pro). Tú inicias el mensaje.',
            ],
            'authentication' => [
                'min' => 0.018,
                'max' => 0.032,
                'icon' => '🔐',
                'label' => 'Códigos de verificación',
                'description' => 'OTP o códigos de acceso, si los usas en tu flujo.',
            ],
            'campaign_freeform' => [
                'min' => 0.020,
                'max' => 0.035,
                'icon' => '🏷️',
                'label' => 'Plantillas útiles',
                'description' => 'Campañas de texto o imagen enviadas a contactos que te escribieron en las últimas 24 h. No son plantillas aprobadas por Meta, así que se cobran aparte de las promociones.',
            ],
        ],
    ],

    'meta_scenarios' => [
        [
            'id' => 'small',
            'title' => 'Negocio pequeño',
            'plan_hint' => 'Starter',
            'service_conv' => 250,
            'utility_conv' => 50,
            'marketing_conv' => 0,
            'description' => 'El bot atiende consultas puntuales. Casi nadie te escribe masivamente y no haces envíos promocionales.',
        ],
        [
            'id' => 'medium',
            'title' => 'Negocio en crecimiento',
            'plan_hint' => 'Pro',
            'service_conv' => 600,
            'utility_conv' => 120,
            'marketing_conv' => 200,
            'description' => 'Varios clientes al mes usan el bot para comprar o preguntar, y de vez en cuando mandas ofertas a quien ya te conoce.',
        ],
        [
            'id' => 'active',
            'title' => 'Marketing activo',
            'plan_hint' => 'Pro',
            'service_conv' => 1200,
            'utility_conv' => 300,
            'marketing_conv' => 800,
            'description' => 'Mucho tráfico diario y campañas frecuentes: promos, recordatorios y reactivación de clientes.',
        ],
        [
            'id' => 'enterprise',
            'title' => 'Alto volumen',
            'plan_hint' => 'Enterprise',
            'service_conv' => 2500,
            'utility_conv' => 600,
            'marketing_conv' => 2000,
            'description' => 'Operación grande con cientos de chats y envíos masivos cada mes.',
        ],
    ],
];
