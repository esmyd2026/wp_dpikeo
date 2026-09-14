<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "si podemos detectar para un cliente cuando el
 * asesor responda desde WhatsApp Business... podríamos inhabilitar también
 * el bot para ese cliente para que el bot no le responda cuando esté
 * interactuando." Meta manda estas respuestas por el webhook como
 * "smb_message_echoes" (campo value.message_echoes) cuando la conexión es
 * de coexistencia con la app de WhatsApp Business.
 */
class AgentAppReplyPausesBotTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(string $suffix): WhatsappBusinessProfile
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Echo', 'slug' => 'empresa-echo-'.$suffix, 'status' => 'active']);

        return WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Echo', 'display_name' => 'Empresa Echo',
            'phone_number' => '593995'.$suffix, 'phone_number_id' => 'ECHO-PHONE-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'connection_type' => 'whatsapp_business_app_coexistence',
        ]);
    }

    public function test_an_agent_app_reply_disables_the_bot_for_that_contact_and_logs_the_message(): void
    {
        $profile = $this->makeProfile('100001');
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999111222', 'name' => 'Ariana', 'bot_enabled' => true]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('ECHO-PHONE-100001');
        $service->processAgentAppReply([
            'from' => '593995100001',
            'to' => '593999111222',
            'id' => 'wamid.echo.1',
            'type' => 'text',
            'text' => ['body' => 'Hola, en un momento te atiendo'],
        ]);

        $this->assertFalse($contact->fresh()->bot_enabled);
        $message = WhatsappMessage::where('message_id', 'wamid.echo.1')->firstOrFail();
        $this->assertSame('humano', $message->sender_type);
        $this->assertSame('Hola, en un momento te atiendo', $message->content);
        $this->assertTrue($message->metadata['human_sent']);
        $this->assertSame('whatsapp_business_app', $message->metadata['sent_via']);
    }

    public function test_a_new_customer_not_seen_before_gets_a_contact_created(): void
    {
        $profile = $this->makeProfile('100002');

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('ECHO-PHONE-100002');
        $service->processAgentAppReply([
            'from' => '593995100002',
            'to' => '593999333444',
            'id' => 'wamid.echo.2',
            'type' => 'text',
            'text' => ['body' => 'Hola'],
        ]);

        $contact = WhatsappContact::where('business_profile_id', $profile->id)->where('phone_number', '593999333444')->firstOrFail();
        $this->assertFalse($contact->bot_enabled);
    }

    public function test_redelivering_the_same_echo_does_not_duplicate_the_message_or_re_touch_bot_enabled(): void
    {
        $profile = $this->makeProfile('100003');
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999555666', 'name' => 'Cliente', 'bot_enabled' => false]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('ECHO-PHONE-100003');
        $echo = ['from' => '593995100003', 'to' => '593999555666', 'id' => 'wamid.echo.3', 'type' => 'text', 'text' => ['body' => 'Ya te ayudo']];
        $service->processAgentAppReply($echo);
        $service->processAgentAppReply($echo);

        $this->assertSame(1, WhatsappMessage::where('message_id', 'wamid.echo.3')->count());
    }

    public function test_a_non_text_echo_still_pauses_the_bot_with_a_readable_placeholder(): void
    {
        $profile = $this->makeProfile('100004');
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593999777888', 'name' => 'Cliente', 'bot_enabled' => true]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('ECHO-PHONE-100004');
        $service->processAgentAppReply([
            'from' => '593995100004',
            'to' => '593999777888',
            'id' => 'wamid.echo.4',
            'type' => 'image',
        ]);

        $this->assertFalse($contact->fresh()->bot_enabled);
        $message = WhatsappMessage::where('message_id', 'wamid.echo.4')->firstOrFail();
        $this->assertSame('image', $message->type);
        $this->assertStringContainsString('enviado desde la app de WhatsApp Business', $message->content);
    }
}
