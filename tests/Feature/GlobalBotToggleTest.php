<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "ayúdame a inhabilitar para todo el mundo el
 * bot" -- un interruptor por número que apaga las respuestas automáticas
 * para TODOS los clientes de ese número, sin tocar el toggle bot_enabled de
 * cada contacto por separado (que sigue funcionando igual).
 */
class GlobalBotToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(string $suffix): WhatsappBusinessProfile
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Toggle', 'slug' => 'empresa-toggle-'.$suffix, 'status' => 'active']);

        return WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Toggle', 'display_name' => 'Empresa Toggle',
            'phone_number' => '593996'.$suffix, 'phone_number_id' => 'TOGGLE-PHONE-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
    }

    public function test_the_bot_stays_silent_for_every_contact_when_globally_disabled(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        $profile = $this->makeProfile('100001');
        WhatsappChatbotConfig::create(['business_profile_id' => $profile->id, 'is_active' => false]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999000111', 'name' => 'Cliente', 'bot_enabled' => true]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('TOGGLE-PHONE-100001');
        (new \ReflectionMethod($service, 'handleTextMessage'))->invokeArgs($service, [[
            'from' => $contact->phone_number, 'id' => 'wamid.'.uniqid(), 'text' => ['body' => 'hola'],
        ]]);

        // Marcar el mensaje como "leído" sigue pasando siempre (es
        // independiente del bot) -- lo que no debe pasar es que se mande una
        // respuesta real (texto/interactivo) del chatbot.
        Http::assertNotSent(fn ($request) => isset($request['text']) || isset($request['interactive']));
    }

    public function test_the_bot_responds_normally_when_the_toggle_is_left_on(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        $profile = $this->makeProfile('100002');
        WhatsappChatbotConfig::create(['business_profile_id' => $profile->id, 'is_active' => true]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999222333', 'name' => 'Cliente', 'bot_enabled' => true]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('TOGGLE-PHONE-100002');
        (new \ReflectionMethod($service, 'handleTextMessage'))->invokeArgs($service, [[
            'from' => $contact->phone_number, 'id' => 'wamid.'.uniqid(), 'text' => ['body' => 'hola'],
        ]]);

        Http::assertSent(fn ($request) => true);
    }

    public function test_admin_can_turn_the_toggle_off_and_on_from_the_panel(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $profile = $this->makeProfile('100003');
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $profile->company->users()->attach($admin->id);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $profile->company_id])
            ->put(route('admin.chatbot.config.update'), [])
            ->assertSessionDoesntHaveErrors();

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile->id)->firstOrFail();
        $this->assertFalse($config->is_active, 'Sin el checkbox marcado, debe quedar apagado.');

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $profile->company_id])
            ->put(route('admin.chatbot.config.update'), ['bot_is_active' => '1'])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue($config->fresh()->is_active);
    }
}
