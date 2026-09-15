<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "desactiva el bot pero no le responde al cliente
 * el mensaje que le programe" -- apagar el bot a mano desde el panel dejaba
 * al cliente en silencio total (mandando mensajes que nadie iba a contestar
 * nunca). Ahora reusa el mismo mensaje configurado para "Derivar a asesor"
 * (el que ya se manda cuando el CLIENTE pide un humano por su cuenta).
 */
class ToggleBotNotifiesCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{WhatsappBusinessProfile, WhatsappContact, User} */
    private function fixture(string $suffix = '01'): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Toggle Aviso', 'slug' => 'empresa-toggle-aviso-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Toggle Aviso', 'display_name' => 'Empresa Toggle Aviso',
            'phone_number' => '593995'.$suffix, 'phone_number_id' => 'TOGGLE-AVISO-'.$suffix,
            'whatsapp_business_id' => 'WABA-TOGGLE-AVISO-'.$suffix, 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '59399920'.$suffix, 'name' => 'Cliente', 'status' => 'active', 'bot_enabled' => true]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$profile, $contact, $user];
    }

    public function test_turning_the_bot_off_notifies_the_customer_with_the_agent_handoff_message(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, $contact, $user] = $this->fixture('01');

        $response = $this->actingAs($user)->postJson(route('admin.contact.toggle-bot', $contact->id), ['enabled' => false]);

        $response->assertOk()->assertJson(['success' => true, 'bot_enabled' => false]);
        Http::assertSent(fn ($request) => isset($request['text']['body'])
            && str_contains($request['text']['body'], 'asesor'));
    }

    public function test_turning_the_bot_back_on_does_not_send_anything_to_the_customer(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, $contact, $user] = $this->fixture('02');
        $contact->update(['bot_enabled' => false]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.toggle-bot', $contact->id), ['enabled' => true]);

        $response->assertOk()->assertJson(['success' => true, 'bot_enabled' => true]);
        Http::assertNotSent(fn ($request) => true);
    }

    public function test_turning_it_off_again_while_already_off_does_not_resend_the_message(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, $contact, $user] = $this->fixture('03');
        $contact->update(['bot_enabled' => false]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.toggle-bot', $contact->id), ['enabled' => false]);

        $response->assertOk();
        Http::assertNotSent(fn ($request) => true);
    }
}
