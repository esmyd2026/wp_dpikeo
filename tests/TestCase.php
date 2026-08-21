<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // La guardia que evita correr pruebas contra la base real vive en
    // CreatesApplication::createApplication() — debe ejecutarse ahí, antes de
    // que RefreshDatabase (u otro trait) llegue a tocar la base de datos.
    use CreatesApplication;
}
