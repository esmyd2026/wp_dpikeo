<?php

namespace App\Services;

use App\Models\BusinessBranch;

class DeliveryFeeCalculator
{
    public function __construct(private readonly GeoDistanceService $geoDistanceService)
    {
    }

    /**
     * @return array{distance_km: ?float, fee: float}
     */
    public function calculate(BusinessBranch $branch, float $customerLat, float $customerLon): array
    {
        $minimum = (float) ($branch->delivery_fee_minimum ?? config('delivery.minimum_fee'));

        if ($branch->latitude === null || $branch->longitude === null) {
            return ['distance_km' => null, 'fee' => $minimum];
        }

        $distanceKm = $this->geoDistanceService->distanceKm(
            (float) $branch->latitude,
            (float) $branch->longitude,
            $customerLat,
            $customerLon
        );

        $perUnit = (float) ($branch->delivery_fee_per_unit ?? config('delivery.fee_per_unit'));
        $unitKm = (float) ($branch->delivery_fee_km_unit ?? config('delivery.unit_km'));
        $unitKm = $unitKm > 0 ? $unitKm : (float) config('delivery.unit_km');

        // round() absorbe el error de punto flotante del cálculo de Haversine
        // (p.ej. 5.000000000000001 km no debe empujar a la siguiente unidad).
        $units = (int) ceil(round($distanceKm / $unitKm, 6));
        $fee = max($minimum, $units * $perUnit);

        return ['distance_km' => round($distanceKm, 2), 'fee' => round($fee, 2)];
    }
}
