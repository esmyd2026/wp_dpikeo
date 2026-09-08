<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Services\PermissionService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ChatGPTConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: "php artisan db:seed" en producción corre
 * TODA la cadena de DatabaseSeeder -- varios de esos seeders se escribieron
 * para el arranque inicial de la plataforma y nunca se actualizaron al
 * pasar a producción multiempresa, así que volver a correrlos pisaba en
 * silencio contenido/credenciales ya personalizadas. Estos cubren los dos
 * más graves: reseteo de contraseñas de admin, y config de ChatGPT.
 */
class SeedersAreSafeToRerunTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_never_resets_an_existing_admins_password(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $adminRole = Role::where('slug', 'super_admin')->firstOrFail();

        $admin = User::create([
            'username' => 'admin', 'name' => 'Gregorio', 'email' => 'admin@siglotecnologico.com',
            'password' => Hash::make('mi-contrasena-real-y-segura'), 'is_admin' => true, 'role' => 'super_admin', 'role_id' => $adminRole->id,
        ]);

        (new AdminUserSeeder())->run();

        $this->assertTrue(Hash::check('mi-contrasena-real-y-segura', $admin->fresh()->password));
    }

    public function test_chatgpt_config_seeder_never_overwrites_an_already_configured_profile(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Mi Negocio', 'display_name' => 'Mi Negocio', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'chatgpt_enabled' => true,
            'chatgpt_model' => 'gpt-4o',
            'chatgpt_system_prompt' => 'Mi prompt personalizado y cuidadosamente escrito.',
        ]);

        (new ChatGPTConfigSeeder())->run();

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile->id)->first();
        $this->assertTrue($config->chatgpt_enabled);
        $this->assertSame('gpt-4o', $config->chatgpt_model);
        $this->assertSame('Mi prompt personalizado y cuidadosamente escrito.', $config->chatgpt_system_prompt);
    }
}
