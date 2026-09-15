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
 * Pedido explícito: poder apagar el bot por EMPRESA completa (todos sus
 * números a la vez), sin que eso pise el interruptor que cada número ya
 * tiene por separado (WhatsappChatbotConfig->is_active, ver
 * GlobalBotToggleTest) -- al reactivar la empresa, cada número debe volver
 * exactamente a como estaba antes de apagarla, no todos encendidos de golpe.
 */
class CompanyBotToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithProfiles(string $suffix): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Multi', 'slug' => 'empresa-multi-'.$suffix, 'status' => 'active']);
        $profileA = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Multi', 'display_name' => 'Empresa Multi',
            'phone_number' => '593991'.$suffix, 'phone_number_id' => 'MULTI-A-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $profileB = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Multi', 'display_name' => 'Empresa Multi',
            'phone_number' => '593992'.$suffix, 'phone_number_id' => 'MULTI-B-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        return [$company, $profileA, $profileB];
    }

    private function sendTextAndCheckIfBotReplied(WhatsappBusinessProfile $profile, WhatsappContact $contact): bool
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        (new \ReflectionMethod($service, 'handleTextMessage'))->invokeArgs($service, [[
            'from' => $contact->phone_number, 'id' => 'wamid.'.uniqid(), 'text' => ['body' => 'hola'],
        ]]);

        $replied = false;
        Http::assertSent(function ($request) use (&$replied) {
            if (isset($request['text']) || isset($request['interactive'])) {
                $replied = true;
            }

            return true;
        });

        return $replied;
    }

    public function test_the_bot_stays_silent_on_every_number_when_the_company_is_disabled(): void
    {
        [$company, $profileA, $profileB] = $this->makeCompanyWithProfiles('100001');
        $company->update(['bot_enabled' => false]);
        WhatsappChatbotConfig::create(['business_profile_id' => $profileA->id, 'is_active' => true]);
        WhatsappChatbotConfig::create(['business_profile_id' => $profileB->id, 'is_active' => true]);
        $contactA = WhatsappContact::create(['business_profile_id' => $profileA->id, 'phone_number' => '593999100001', 'name' => 'Cliente A', 'bot_enabled' => true]);
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999100002', 'name' => 'Cliente B', 'bot_enabled' => true]);

        $this->assertFalse($this->sendTextAndCheckIfBotReplied($profileA, $contactA));
        $this->assertFalse($this->sendTextAndCheckIfBotReplied($profileB, $contactB));
    }

    public function test_disabling_the_company_never_touches_each_numbers_own_toggle(): void
    {
        [$company, $profileA, $profileB] = $this->makeCompanyWithProfiles('100002');
        // A tiene su propio interruptor encendido, B apagado -- esto debe
        // seguir siendo cierto después de apagar y reactivar la empresa.
        WhatsappChatbotConfig::create(['business_profile_id' => $profileA->id, 'is_active' => true]);
        WhatsappChatbotConfig::create(['business_profile_id' => $profileB->id, 'is_active' => false]);
        $contactA = WhatsappContact::create(['business_profile_id' => $profileA->id, 'phone_number' => '593999200001', 'name' => 'Cliente A', 'bot_enabled' => true]);
        $contactB = WhatsappContact::create(['business_profile_id' => $profileB->id, 'phone_number' => '593999200002', 'name' => 'Cliente B', 'bot_enabled' => true]);

        $company->update(['bot_enabled' => false]);
        $this->assertFalse($this->sendTextAndCheckIfBotReplied($profileA, $contactA), 'Empresa apagada: A no debe responder.');
        $this->assertFalse($this->sendTextAndCheckIfBotReplied($profileB, $contactB), 'Empresa apagada: B no debe responder.');

        $company->update(['bot_enabled' => true]);
        $this->assertSame(true, WhatsappChatbotConfig::where('business_profile_id', $profileA->id)->value('is_active'));
        $this->assertSame(false, WhatsappChatbotConfig::where('business_profile_id', $profileB->id)->value('is_active'));
        $this->assertTrue($this->sendTextAndCheckIfBotReplied($profileA, $contactA), 'Empresa reactivada: A debe volver a responder (su propio toggle seguía en true).');
        $this->assertFalse($this->sendTextAndCheckIfBotReplied($profileB, $contactB), 'Empresa reactivada: B sigue apagado por su propio toggle.');
    }

    public function test_admin_can_toggle_the_company_bot_from_the_panel(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        [$company] = $this->makeCompanyWithProfiles('100003');
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.empresas.bot.toggle', $company), ['bot_enabled' => '0'])
            ->assertRedirect(route('admin.empresas.whatsapp', $company));
        $this->assertFalse($company->fresh()->bot_enabled);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.empresas.bot.toggle', $company), ['bot_enabled' => '1'])
            ->assertRedirect(route('admin.empresas.whatsapp', $company));
        $this->assertTrue($company->fresh()->bot_enabled);
    }

    public function test_a_user_without_access_to_the_company_cannot_toggle_its_bot(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        [$company] = $this->makeCompanyWithProfiles('100004');
        $role = Role::where('slug', 'admin')->firstOrFail();
        $outsider = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);

        $this->actingAs($outsider)
            ->post(route('admin.empresas.bot.toggle', $company), ['bot_enabled' => '0'])
            ->assertForbidden();
        $this->assertTrue($company->fresh()->bot_enabled);
    }
}
