<?php

return [
    'sales_whatsapp' => env('SALES_WHATSAPP_NUMBER', '593994281769'),

    /*
    | Margen sobre tarifa Meta de referencia (gestión, variaciones, imprevistos)
    */
    'meta_markup' => 1.30,

    /*
    | Tipos de conversación Meta visibles para este cliente (dashboard).
    | Se puede cambiar desde el panel Tarifas Meta (super admin).
    */
    'enabled_conversation_categories' => [
        'service' => true,
        'utility' => true,
        'marketing' => false,
        'authentication' => false,
        'campaign_freeform' => true,
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
                'description' => 'Ofertas, recordatorios o campañas masivas a tu lista de contactos. Tú inicias el mensaje.',
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
];
