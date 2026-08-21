<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Guardia de seguridad: debe correr ANTES de que RefreshDatabase (u otro
        // trait) toque la base de datos. Si bootstrap/cache/config.php existe
        // (ej. tras `php artisan config:cache` tal como hace deploy.sh),
        // env('DB_...') deja de leerse y el DB_CONNECTION=sqlite de phpunit.xml
        // no se aplica: las pruebas terminarían corriendo (y con
        // RefreshDatabase, BORRANDO) la base de datos real. Abortar fuerte.
        $connection = $app['config']->get('database.default');
        $driver = $app['config']->get("database.connections.{$connection}.driver");
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            fwrite(STDERR, "\n\n🛑 ABORTADO: las pruebas intentaron usar la conexión '{$connection}' ({$driver}, database=\"{$database}\") en vez de sqlite en memoria.\n"
                . "Esto casi siempre significa que la config está cacheada (ejecuta: php artisan config:clear) y env('DB_CONNECTION') de phpunit.xml no se está aplicando.\n"
                . "Las pruebas NUNCA deben correr contra la base real.\n\n");

            exit(1);
        }

        return $app;
    }
}
