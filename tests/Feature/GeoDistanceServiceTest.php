<?php

namespace Tests\Feature;

use App\Services\GeoDistanceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito: la distancia calculada (línea recta) no coincidía con
 * lo que muestra Google Maps para la misma ruta -- porque las calles no van
 * en diagonal. Ahora se usa OSRM (gratis, sin API key) para la distancia
 * real por calle, con un margen de seguridad fijo, y un respaldo (línea
 * recta corregida) si el servicio no responde.
 */
class GeoDistanceServiceTest extends TestCase
{
    private const LAT1 = -2.1500;
    private const LON1 = -79.9000;
    private const LAT2 = -2.1600;
    private const LON2 = -79.9100;

    public function test_uses_the_real_road_distance_from_osrm_plus_the_safety_margin(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 2500, 'duration' => 400]], // 2.5 km reales
            ], 200),
        ]);

        $km = app(GeoDistanceService::class)->roadDistanceKm(self::LAT1, self::LON1, self::LAT2, self::LON2);

        // 2.5 km (OSRM) + 0.3 km (margen por defecto) = 2.8 km.
        $this->assertEqualsWithDelta(2.8, $km, 0.001);
    }

    public function test_falls_back_to_a_corrected_straight_line_when_osrm_is_unreachable(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        $geo = app(GeoDistanceService::class);
        $straightLine = $geo->distanceKm(self::LAT1, self::LON1, self::LAT2, self::LON2);
        $km = $geo->roadDistanceKm(self::LAT1, self::LON1, self::LAT2, self::LON2);

        // Línea recta * 1.3 (factor de corrección) + 0.3 km de margen.
        $this->assertEqualsWithDelta(($straightLine * 1.3) + 0.3, $km, 0.01);
        $this->assertGreaterThan($straightLine, $km, 'El respaldo debe dar una distancia mayor a la línea recta, nunca menor.');
    }

    public function test_falls_back_when_osrm_returns_an_error_status(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response(['message' => 'error'], 500)]);

        $geo = app(GeoDistanceService::class);
        $straightLine = $geo->distanceKm(self::LAT1, self::LON1, self::LAT2, self::LON2);
        $km = $geo->roadDistanceKm(self::LAT1, self::LON1, self::LAT2, self::LON2);

        $this->assertEqualsWithDelta(($straightLine * 1.3) + 0.3, $km, 0.01);
    }

    public function test_the_safety_margin_is_configurable(): void
    {
        config(['delivery.distance_margin_km' => 0.5]);
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 1000, 'duration' => 120]],
            ], 200),
        ]);

        $km = app(GeoDistanceService::class)->roadDistanceKm(self::LAT1, self::LON1, self::LAT2, self::LON2);

        $this->assertEqualsWithDelta(1.5, $km, 0.001);
    }
}
