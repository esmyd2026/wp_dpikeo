<?php

return [
    'modules' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'fa-chart-line',
            'permissions' => [
                'dashboard.menu' => ['label' => 'Ver inicio en menú', 'type' => 'menu'],
                'dashboard.view' => ['label' => 'Ver inicio y reportes WhatsApp', 'type' => 'action'],
            ],
        ],
        'chats' => [
            'label' => 'Chats WhatsApp',
            'icon' => 'fa-comments',
            'permissions' => [
                'chats.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'chats.view' => ['label' => 'Ver conversaciones', 'type' => 'action'],
                'chats.open' => ['label' => 'Abrir chat', 'type' => 'action'],
                'chats.send' => ['label' => 'Enviar mensajes', 'type' => 'action'],
                'chats.toggle_bot' => ['label' => 'Activar / desactivar bot', 'type' => 'action'],
                'whatsapp_reports.menu' => ['label' => 'Reportes de WhatsApp', 'type' => 'submenu'],
            ],
        ],
        'clients' => [
            'label' => 'Clientes',
            'icon' => 'fa-users',
            'permissions' => [
                'clients.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'clients.view' => ['label' => 'Ver listado de clientes', 'type' => 'action'],
                'clients.detail' => ['label' => 'Ver detalle del cliente', 'type' => 'action'],
                'clients.update' => ['label' => 'Editar datos del cliente', 'type' => 'action'],
                'clients.notes' => ['label' => 'Agregar observaciones', 'type' => 'action'],
            ],
        ],
        'orders' => [
            'label' => 'Pedidos',
            'icon' => 'fa-shopping-cart',
            'permissions' => [
                'orders.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'orders.view' => ['label' => 'Ver pedidos y reportes de ventas', 'type' => 'action'],
                'orders.update' => ['label' => 'Cambiar estado de pedidos', 'type' => 'action'],
                'orders.delete' => ['label' => 'Eliminar pedidos definitivamente', 'type' => 'action'],
                'orders.billing' => ['label' => 'Ver y gestionar facturación del pedido', 'type' => 'action'],
                'orders.internal_notes' => ['label' => 'Ver y gestionar notas para el equipo', 'type' => 'action'],
                'orders.followup' => ['label' => 'Ver y gestionar conversaciones y seguimiento', 'type' => 'action'],
                'kitchen.menu' => ['label' => 'Comandas', 'type' => 'submenu'],
                'delivery.menu' => ['label' => 'Delivery', 'type' => 'submenu'],
                'orders_reports.menu' => ['label' => 'Reporte de pedidos', 'type' => 'submenu'],
            ],
        ],
        'marketing_flow' => [
            'label' => 'Flujo del bot',
            'icon' => 'fa-project-diagram',
            'permissions' => [
                'marketing_flow.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'marketing_flow.view' => ['label' => 'Ver flujo', 'type' => 'action'],
                'marketing_flow.update' => ['label' => 'Editar flujo', 'type' => 'action'],
            ],
        ],
        'campaigns' => [
            'label' => 'Campañas masivas',
            'icon' => 'fa-bullhorn',
            'permissions' => [
                'campaigns.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'campaigns.view' => ['label' => 'Ver campañas', 'type' => 'action'],
                'campaigns.manage' => ['label' => 'Crear / editar campañas y sincronizar plantillas', 'type' => 'action'],
                'campaigns.send' => ['label' => 'Enviar / reprogramar campañas', 'type' => 'action'],
            ],
        ],
        'menus' => [
            'label' => 'Categorías',
            'icon' => 'fa-folder-open',
            'permissions' => [
                'menus.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'menus.view' => ['label' => 'Ver categorías', 'type' => 'action'],
                'menus.update' => ['label' => 'Crear / editar categorías', 'type' => 'action'],
            ],
        ],
        'products' => [
            'label' => 'Productos',
            'icon' => 'fa-box-open',
            'permissions' => [
                'products.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'products.view' => ['label' => 'Ver productos', 'type' => 'action'],
                'products.update' => ['label' => 'Crear / editar productos', 'type' => 'action'],
                'inventory.menu' => ['label' => 'Inventario', 'type' => 'submenu'],
            ],
        ],
        'chatbot' => [
            'label' => 'Configuración del bot',
            'icon' => 'fa-sliders-h',
            'permissions' => [
                'chatbot.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'chatbot.view' => ['label' => 'Ver configuración', 'type' => 'action'],
                'chatbot.update' => ['label' => 'Editar configuración', 'type' => 'action'],
                'companies.menu' => ['label' => 'Empresas y conexión Meta', 'type' => 'submenu'],
            ],
        ],
        'pricing_settings' => [
            'label' => 'Parámetros de plataforma',
            'icon' => 'fa-sliders-h',
            'permissions' => [
                'pricing_settings.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'pricing_settings.view' => ['label' => 'Ver parámetros internos', 'type' => 'action'],
                'pricing_settings.update' => ['label' => 'Editar parámetros internos', 'type' => 'action'],
                'franchises.menu' => ['label' => 'Franquicias', 'type' => 'submenu'],
                'branches.menu' => ['label' => 'Sucursales', 'type' => 'submenu'],
            ],
        ],
        'users' => [
            'label' => 'Usuarios',
            'icon' => 'fa-user-gear',
            'permissions' => [
                'users.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'users.view' => ['label' => 'Ver usuarios', 'type' => 'action'],
                'users.create' => ['label' => 'Crear usuario', 'type' => 'action'],
                'users.update' => ['label' => 'Editar usuario', 'type' => 'action'],
                'users.delete' => ['label' => 'Eliminar usuario', 'type' => 'action'],
            ],
        ],
        'roles' => [
            'label' => 'Roles y permisos',
            'icon' => 'fa-key',
            'permissions' => [
                'roles.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'roles.view' => ['label' => 'Ver roles', 'type' => 'action'],
                'roles.update' => ['label' => 'Configurar permisos', 'type' => 'action'],
            ],
        ],
        'bulk_orders' => [
            'label' => 'Pedido masivo web',
            'icon' => 'fa-list-check',
            'permissions' => [
                'bulk_orders.create' => ['label' => 'Crear pedidos desde el panel (formulario web)', 'type' => 'action'],
                'bulk_orders.manage' => ['label' => 'Activar / desactivar formulario en WhatsApp', 'type' => 'action'],
            ],
        ],
        'message_failures' => [
            'label' => 'Fallos de envío',
            'icon' => 'fa-triangle-exclamation',
            'permissions' => [
                'message_failures.menu' => ['label' => 'Ver en menú', 'type' => 'menu'],
                'message_failures.view' => ['label' => 'Ver fallos de envío', 'type' => 'action'],
                'message_failures.manage' => ['label' => 'Marcar fallos como resueltos', 'type' => 'action'],
            ],
        ],
    ],

    'default_roles' => [
        'super_admin' => [
            'name' => 'Super Administrador',
            'description' => 'Acceso total, incluyendo tarifas Meta internas.',
            'is_system' => true,
            'permissions' => '*',
        ],
        'admin' => [
            'name' => 'Administrador',
            'description' => 'Gestión completa del bot, catálogo y operaciones.',
            'is_system' => true,
            'permissions' => [
                'dashboard.menu', 'dashboard.view',
                'chats.menu', 'chats.view', 'chats.open', 'chats.send', 'chats.toggle_bot',
                'whatsapp_reports.menu',
                'clients.menu', 'clients.view', 'clients.detail', 'clients.update', 'clients.notes',
                'orders.menu', 'orders.view', 'orders.update',
                'kitchen.menu', 'delivery.menu', 'orders_reports.menu',
                'bulk_orders.create', 'bulk_orders.manage',
                'marketing_flow.menu', 'marketing_flow.view', 'marketing_flow.update',
                'campaigns.menu', 'campaigns.view', 'campaigns.manage', 'campaigns.send',
                'menus.menu', 'menus.view', 'menus.update',
                'products.menu', 'products.view', 'products.update',
                'inventory.menu',
                'chatbot.menu', 'chatbot.view', 'chatbot.update',
                'companies.menu',
                'users.menu', 'users.view', 'users.create', 'users.update',
                'message_failures.menu', 'message_failures.view', 'message_failures.manage',
            ],
        ],
        'agent' => [
            'name' => 'Agente de ventas',
            'description' => 'Atiende chats y gestiona pedidos.',
            'is_system' => true,
            'permissions' => [
                'dashboard.menu', 'dashboard.view',
                'chats.menu', 'chats.view', 'chats.open', 'chats.send', 'chats.toggle_bot',
                'whatsapp_reports.menu',
                'clients.menu', 'clients.view', 'clients.detail', 'clients.update', 'clients.notes',
                'orders.menu', 'orders.view', 'orders.update',
                'kitchen.menu', 'delivery.menu', 'orders_reports.menu',
            ],
        ],
        'viewer' => [
            'name' => 'Consultor / Solo lectura',
            'description' => 'Consulta reportes sin modificar configuración.',
            'is_system' => true,
            'permissions' => [
                'dashboard.menu', 'dashboard.view',
                'chats.menu', 'chats.view', 'chats.open',
                'whatsapp_reports.menu',
                'clients.menu', 'clients.view', 'clients.detail',
                'orders.menu', 'orders.view',
                'kitchen.menu', 'delivery.menu', 'orders_reports.menu',
            ],
        ],
    ],
];
