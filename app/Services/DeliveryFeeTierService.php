<?php

namespace App\Services;

use App\Models\BusinessBranch;

/**
 * Calcula el costo de envío a partir de la distancia (km) y la tabla de
 * tramos de la sucursal ("desde X hasta Y km = $"), configurada desde el
 * panel (ver BusinessBranchController::syncDeliveryFeeTiers). Reemplaza la
 * confirmación manual del vendedor: si la distancia cae dentro de un tramo
 * configurado, el costo queda fijo de una vez.
 */
class DeliveryFeeTierService
{
    /** null si no hay tabla configurada o la distancia no cae en ningún tramo (fuera de cobertura). */
    public function feeForDistance(BusinessBranch $branch, float $km): ?float
    {
        $tier = $branch->deliveryFeeTiers
            ->first(fn ($tier) => $km >= (float) $tier->from_km && ($tier->to_km === null || $km <= (float) $tier->to_km));

        return $tier ? (float) $tier->price : null;
    }
}
