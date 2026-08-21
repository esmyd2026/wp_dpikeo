<?php

return [
    /*
    | Tarifa de delivery por defecto cuando una sucursal no configura la suya.
    | Ecuación: fee = max(minimum_fee, ceil(distance_km / unit_km) * fee_per_unit)
    */
    'fee_per_unit' => env('DELIVERY_FEE_PER_UNIT', 2.00),
    'unit_km' => env('DELIVERY_UNIT_KM', 5.00),
    'minimum_fee' => env('DELIVERY_MINIMUM_FEE', 2.00),
];
