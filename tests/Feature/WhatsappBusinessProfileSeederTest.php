<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use Database\Seeders\WhatsappBusinessProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: "php artisan db:seed" en producción tumbaba
 * todo el comando -- este seeder revisaba las variables de entorno
 * (WHATSAPP_PHONE_NUMBER_ID, etc.) ANTES de comprobar si ya existía un
 * perfil, así que en cualquier empresa creada desde el panel (que no usa
 * esas variables) siempre terminaba logueando un error, y si el log no se
 * podía escribir por permisos del servidor, eso tumbaba el comando entero.
 */
class WhatsappBusinessProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_a_no_op_when_a_profile_already_exists_even_without_env_vars(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Mi Negocio', 'display_name' => 'Mi Negocio', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-REAL', 'whatsapp_business_id' => 'WABA-REAL', 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        (new WhatsappBusinessProfileSeeder())->run();

        $this->assertSame(1, WhatsappBusinessProfile::count());
        $this->assertSame('PHONE-REAL', $profile->fresh()->phone_number_id);
    }

    public function test_it_does_not_create_a_profile_when_env_credentials_are_missing(): void
    {
        // El .env de este repo trae credenciales de prueba heredadas del
        // modelo de una sola empresa -- se limpian para simular el caso
        // real (una instalación nueva, sin nada en .env todavía).
        $vars = ['WHATSAPP_PHONE_NUMBER', 'WHATSAPP_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_BUSINESS_ID'];
        $originals = [];
        foreach ($vars as $var) {
            $originals[$var] = getenv($var);
            putenv($var);
            unset($_ENV[$var], $_SERVER[$var]);
        }

        try {
            (new WhatsappBusinessProfileSeeder())->run();

            $this->assertSame(0, WhatsappBusinessProfile::count());
        } finally {
            foreach ($originals as $var => $value) {
                if ($value !== false) {
                    putenv("{$var}={$value}");
                    $_ENV[$var] = $value;
                    $_SERVER[$var] = $value;
                }
            }
        }
    }
}
