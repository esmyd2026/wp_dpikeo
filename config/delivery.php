<?php

return [
    /*
    | Tarifa de delivery por defecto cuando una sucursal no configura la suya.
    | Ecuación: fee = max(minimum_fee, ceil(distance_km / unit_km) * fee_per_unit)
    */
    'fee_per_unit' => env('DELIVERY_FEE_PER_UNIT', 2.00),
    'unit_km' => env('DELIVERY_UNIT_KM', 5.00),
    'minimum_fee' => env('DELIVERY_MINIMUM_FEE', 2.00),

    /*
    | Servidor OSRM (gratis, sin API key) para calcular la distancia real
    | por calle entre la sucursal y el cliente -- ver GeoDistanceService::roadDistanceKm().
    | Por defecto usa el servidor público de demostración; se puede apuntar
    | a un servidor propio con OSRM_URL si el público queda lento/caído.
    */
    'osrm_url' => env('OSRM_URL', 'https://router.project-osrm.org'),

    /*
    | Margen de seguridad que se le suma a la distancia calculada (real, o
    | línea recta corregida si OSRM falla), para no quedarse corto por
    | variaciones de ruta.
    */
    'distance_margin_km' => env('DELIVERY_DISTANCE_MARGIN_KM', 0.3),
];
