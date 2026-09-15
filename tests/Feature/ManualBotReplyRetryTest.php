<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "podemos desde el panel dispararle la opción si
 * el bot no le responde" -- versión manual, para un contacto puntual, del
 * comando automático whatsapp:retry-pending-replies (que espera al menos 2
 * minutos y corre cada minuto sobre TODA la instalación). Este botón deja al
 * asesor forzarlo de una vez sobre la conversación que tiene abierta, sin
 * esperar ni depender del límite de 3 intentos automáticos.
 */
class ManualBotReplyRetryTest extends TestCase
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
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Retry', 'slug' => 'empresa-retry-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Retry', 'display_name' => 'Empresa Retry',
            'phone_number' => '593994'.$suffix, 'phone_number_id' => 'RETRY-PHONE-'.$suffix,
            'whatsapp_business_id' => 'WABA-RETRY-'.$suffix, 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '59399910'.$suffix, 'name' => 'Cliente', 'status' => 'active', 'bot_enabled' => true]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$profile, $contact, $user];
    }

    public function test_the_panel_button_resends_the_customers_last_message_and_gets_a_reply(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile, $contact, $user] = $this->fixture('01');
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'message_id' => 'wamid.stuck.01', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received',
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.retry-bot-reply', $contact->id));

        $response->assertOk()->assertJson(['success' => true]);
        Http::assertSent(fn ($request) => isset($request['text']) || isset($request['interactive']));
    }

    public function test_it_refuses_when_the_bot_is_disabled_for_that_contact(): void
    {
        [$profile, $contact, $user] = $this->fixture('02');
        $contact->update(['bot_enabled' => false]);
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'message_id' => 'wamid.stuck.02', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received',
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.retry-bot-reply', $contact->id));

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('apagado', $response->json('message'));
    }

    public function test_it_refuses_when_the_last_message_already_has_a_reply(): void
    {
        [$profile, $contact, $user] = $this->fixture('03');
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'message_id' => 'wamid.stuck.03', 'sender_type' => 'client', 'type' => 'text', 'content' => 'hola', 'status' => 'received',
        ]);
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'message_id' => 'wamid.reply.03', 'sender_type' => 'bot', 'type' => 'text', 'content' => 'Ya te respondí', 'status' => 'sent',
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.contact.retry-bot-reply', $contact->id));

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_a_user_without_access_to_the_company_cannot_retry_its_contact(): void
    {
        [, $contact] = $this->fixture('04');
        [, , $outsider] = $this->fixture('05');

        $response = $this->actingAs($outsider)->postJson(route('admin.contact.retry-bot-reply', $contact->id));

        $response->assertStatus(404);
    }
}
