<?php

return [
    'timezone' => 'America/Guayaquil',
    'currency' => 'USD',
    'currency_symbol' => '$',
    'iva_rate' => 0.15,
    'prices_include_iva' => false,
    'document_title' => 'ORDEN DE PEDIDO',
    'document_subtitle' => 'Documento de pedido comercial',

    'company' => [
        'legal_name' => env('APP_NAME', 'Mi Empresa'),
        'trade_name' => '',
        'ruc' => '',
        'address' => '',
        'city' => 'Ecuador',
        'phone' => '',
        'email' => '',
        'website' => '',
    ],

    'status_labels' => [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'En preparación',
        'ready' => 'Listo para entregar',
        'completed' => 'Entregado',
        'cancelled' => 'Cancelado',
        'payment_pending' => 'Pago pendiente',
        'paid' => 'Pagado',
    ],

    'payment_methods' => [
        'transferencia' => 'Transferencia bancaria',
        'efectivo' => 'Efectivo contra entrega',
        'tarjeta' => 'Tarjeta de crédito/débito',
        'cash_on_delivery' => 'Efectivo contra entrega',
    ],

    'legal_footer' => 'Este documento constituye una orden de pedido. No sustituye factura electrónica autorizada por el SRI. '
        . 'Los valores en dólares de los Estados Unidos de América (USD). '
        . 'Documento generado electrónicamente.',

    'signed_url_ttl_days' => 30,
];
