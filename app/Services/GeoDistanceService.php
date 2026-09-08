<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoDistanceService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Pedido explícito: la distancia real por calle no coincide con la
     * línea recta que usaba antes distanceKm() -- las calles no van en
     * diagonal, así que la línea recta siempre da un número más chico que
     * lo que de verdad hay que recorrer. Se calcula con OSRM (gratis, sin
     * API key -- ver config('delivery.osrm_url')) y se le suma un margen
     * de seguridad fijo (config('delivery.distance_margin_km'), 0.3 km por
     * defecto) para no quedarse corto por variaciones de ruta. Si OSRM no
     * responde (timeout, caído, sin internet), cae a la línea recta con un
     * factor de corrección (las calles rara vez van directo) más el mismo
     * margen, para que el checkout nunca se trabe esperando un servicio
     * externo.
     */
    public function roadDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $margin = (float) config('delivery.distance_margin_km', 0.3);

        $osrmKm = $this->fetchOsrmDistanceKm($lat1, $lon1, $lat2, $lon2);
        if ($osrmKm !== null) {
            return round($osrmKm + $margin, 2);
        }

        // Respaldo: línea recta corregida por un factor de "circuidad" --
        // las calles casi nunca van directo, así que la distancia real
        // suele ser bastante mayor a la línea recta (el factor típico en
        // zonas urbanas ronda 1.3-1.4).
        $straightLineKm = $this->distanceKm($lat1, $lon1, $lat2, $lon2);

        return round(($straightLineKm * 1.3) + $margin, 2);
    }

    private function fetchOsrmDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): ?float
    {
        $baseUrl = config('delivery.osrm_url', 'https://router.project-osrm.org');

        try {
            $response = Http::timeout(4)->retry(1, 200)
                ->get("{$baseUrl}/route/v1/driving/{$lon1},{$lat1};{$lon2},{$lat2}", [
                    'overview' => 'false',
                ]);

            if (! $response->successful()) {
                Log::warning('[GeoDistanceService] OSRM respondió con error, se usa línea recta como respaldo', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $meters = $response->json('routes.0.distance');
            if (! is_numeric($meters)) {
                Log::warning('[GeoDistanceService] OSRM no devolvió una ruta válida, se usa línea recta como respaldo', [
                    'response' => $response->json(),
                ]);

                return null;
            }

            return ((float) $meters) / 1000;
        } catch (\Throwable $e) {
            Log::warning('[GeoDistanceService] No se pudo consultar OSRM, se usa línea recta como respaldo', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Distancia en línea recta ("como vuela el pájaro"), sin calles -- ver roadDistanceKm() para la distancia real. */
    public function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
