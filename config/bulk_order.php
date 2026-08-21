<?php

return [
    /*
    | Dirección que se comparte por WhatsApp para abrir el micrositio.
    | En local use 127.0.0.1:8001; para clientes reales cámbiela por el
    | dominio HTTPS de producción o por el túnel temporal de ngrok.
    */
    'public_url' => env('BULK_ORDER_PUBLIC_URL', env('APP_URL', 'http://127.0.0.1:8001')),

    /*
    | Mínimo de ítems (líneas) en el carrito para sugerir el formulario web.
    */
    'min_cart_lines' => (int) env('BULK_ORDER_MIN_LINES', 3),

    /*
    | Horas de validez del enlace del formulario.
    */
    'token_ttl_hours' => (int) env('BULK_ORDER_TOKEN_TTL', 24),
];
