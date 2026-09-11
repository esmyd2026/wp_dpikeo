<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    // La guardia que evita correr pruebas contra la base real vive en
    // CreatesApplication::createApplication() — debe ejecutarse ahí, antes de
    // que RefreshDatabase (u otro trait) llegue a tocar la base de datos.
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba automatizada debe alcanzar Meta, pasarelas ni otros
        // servicios reales. Cada caso que necesite HTTP debe declarar su fake;
        // una llamada olvidada falla inmediatamente y no sale del entorno local.
        Http::preventStrayRequests();
    }
}
