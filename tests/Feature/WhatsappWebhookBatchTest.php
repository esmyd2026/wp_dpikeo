<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class WhatsappWebhookBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Secreto sintético exclusivo de estas pruebas locales.
        config()->set('whatsapp.app_secret', 'TEST_LOCAL_SECRET');
    }

    private function postSignedJson(array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, (string) config('whatsapp.app_secret'));

        return $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$signature,
            ],
            $json
        );
    }

    public function test_webhook_processes_every_message_from_every_change_and_entry(): void
    {
        $service = Mockery::mock(WhatsappService::class);
        $service->shouldReceive('setWebhookPhoneNumberId')->once()->with('PHONE-A');
        $service->shouldReceive('setWebhookPhoneNumberId')->once()->with('PHONE-B');
        $service->shouldReceive('processIncomingMessage')->once()->with(Mockery::on(
            fn (array $message): bool => $message['id'] === 'wamid.batch.1'
                && $message['text'] === 'Primero'
        ));
        $service->shouldReceive('processIncomingMessage')->once()->with(Mockery::on(
            fn (array $message): bool => $message['id'] === 'wamid.batch.2'
                && $message['text'] === 'Segundo'
        ));
        $service->shouldReceive('processIncomingMessage')->once()->with(Mockery::on(
            fn (array $message): bool => $message['id'] === 'wamid.batch.3'
                && $message['text'] === 'Tercero'
        ));
        $this->app->instance(WhatsappService::class, $service);

        $value = static fn (string $phoneNumberId, array $messages): array => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['phone_number_id' => $phoneNumberId],
            'contacts' => [['wa_id' => '593999999999', 'profile' => ['name' => 'Cliente']]],
            'messages' => $messages,
        ];
        $message = static fn (string $id, string $body): array => [
            'from' => '593999999999',
            'id' => $id,
            'timestamp' => '1789000000',
            'type' => 'text',
            'text' => ['body' => $body],
        ];

        $response = $this->postSignedJson([
            'object' => 'whatsapp_business_account',
            'entry' => [
                ['id' => 'WABA-A', 'changes' => [
                    ['field' => 'messages', 'value' => $value('PHONE-A', [
                        $message('wamid.batch.1', 'Primero'),
                        $message('wamid.batch.2', 'Segundo'),
                    ])],
                ]],
                ['id' => 'WABA-B', 'changes' => [
                    ['field' => 'messages', 'value' => $value('PHONE-B', [
                        $message('wamid.batch.3', 'Tercero'),
                    ])],
                ]],
            ],
        ]);

        $response->assertOk()->assertJson(['estado' => true]);
    }

    public function test_webhook_applies_all_message_status_updates(): void
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa webhook',
            'slug' => 'empresa-webhook',
            'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'Empresa webhook',
            'display_name' => 'Empresa webhook',
            'phone_number' => '593999999998',
            'phone_number_id' => 'PHONE-STATUS',
            'whatsapp_business_id' => 'WABA-STATUS',
            'access_token' => 'TEST_TOKEN',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593999999999',
            'name' => 'Cliente',
            'status' => 'active',
        ]);

        foreach (['wamid.status.1', 'wamid.status.2'] as $id) {
            WhatsappMessage::create([
                'business_profile_id' => $profile->id,
                'contact_id' => $contact->id,
                'message_id' => $id,
                'sender_type' => 'system',
                'receiver_type' => 'client',
                'content' => 'Mensaje local',
                'type' => 'text',
                'status' => 'sent',
            ]);
        }

        $response = $this->postSignedJson([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-STATUS',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'PHONE-STATUS'],
                        'statuses' => [
                            ['id' => 'wamid.status.1', 'status' => 'delivered', 'timestamp' => (string) now()->addMinute()->timestamp],
                            ['id' => 'wamid.status.2', 'status' => 'read', 'timestamp' => (string) now()->addMinute()->timestamp],
                        ],
                    ],
                ]],
            ]],
        ]);

        $response->assertOk();
        $this->assertSame('delivered', WhatsappMessage::where('message_id', 'wamid.status.1')->value('status'));
        $this->assertSame('read', WhatsappMessage::where('message_id', 'wamid.status.2')->value('status'));

        // Un reintento atrasado no debe devolver el mensaje a un estado viejo.
        app(WhatsappService::class)->processMessageStatus([
            'id' => 'wamid.status.2',
            'status' => 'sent',
            'timestamp' => (string) now()->timestamp,
        ]);
        $this->assertSame('read', WhatsappMessage::where('message_id', 'wamid.status.2')->value('status'));
    }

    public function test_invalid_json_is_rejected_without_exposing_an_exception(): void
    {
        $invalidJson = '{json-incompleto';
        $response = $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $invalidJson, 'TEST_LOCAL_SECRET'),
            ],
            $invalidJson
        );

        $response->assertStatus(400)->assertJson([
            'estado' => false,
            'mensaje' => 'JSON inválido',
        ]);
    }

    public function test_invalid_signature_is_rejected_before_processing_payload(): void
    {
        $response = $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256='.str_repeat('0', 64),
        ])->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => 'WABA', 'changes' => []]],
        ]);

        $response->assertForbidden()->assertJson([
            'estado' => false,
            'mensaje' => 'Firma inválida',
        ]);
    }

    public function test_missing_app_secret_keeps_legacy_webhook_available(): void
    {
        config()->set('whatsapp.app_secret', '');

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => 'WABA', 'changes' => []]],
        ])->assertOk()->assertJson(['estado' => true]);
    }

    public function test_one_broken_message_does_not_prevent_the_next_one_from_being_processed(): void
    {
        $processed = [];
        $service = Mockery::mock(WhatsappService::class);
        $service->shouldReceive('setWebhookPhoneNumberId')->once()->with('PHONE-CONTINUE');
        $service->shouldReceive('processIncomingMessage')->twice()->andReturnUsing(
            function (array $message) use (&$processed): void {
                $processed[] = $message['id'];
                if ($message['id'] === 'wamid.broken') {
                    throw new \RuntimeException('Fallo local simulado');
                }
            }
        );
        $this->app->instance(WhatsappService::class, $service);

        $response = $this->postSignedJson([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-CONTINUE',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE-CONTINUE'],
                        'contacts' => [],
                        'messages' => [
                            ['from' => '593999999999', 'id' => 'wamid.broken', 'type' => 'text', 'text' => ['body' => 'Uno']],
                            ['from' => '593999999999', 'id' => 'wamid.good', 'type' => 'text', 'text' => ['body' => 'Dos']],
                        ],
                    ],
                ]],
            ]],
        ]);

        $response->assertOk()->assertJsonPath('procesados.mensajes', 1);
        $this->assertSame(['wamid.broken', 'wamid.good'], $processed);
        $response->assertDontSee('Uno')->assertDontSee('Dos');
    }

    public function test_shared_contact_is_saved_and_gets_a_recovery_menu_instead_of_silence(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply.contacts']]], 200),
        ]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Empresa contacto',
            'display_name' => 'Empresa contacto',
            'phone_number' => '593999999997',
            'phone_number_id' => 'PHONE-CONTACT',
            'whatsapp_business_id' => 'WABA-CONTACT',
            'access_token' => 'TEST_TOKEN',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $service->processIncomingMessage([
            'from' => '593999999996',
            'id' => 'wamid.inbound.contacts',
            'type' => 'contacts',
            'contacts' => [['wa_id' => '593999999996', 'profile' => ['name' => 'Cliente']]],
            'shared_contacts' => [[
                'name' => ['formatted_name' => 'Persona compartida'],
                'phones' => [['phone' => '+593 999 999 995']],
            ]],
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'message_id' => 'wamid.inbound.contacts',
            'business_profile_id' => $profile->id,
            'type' => 'contacts',
            'content' => 'Contacto compartido: Persona compartida',
        ]);
        Http::assertSent(fn ($request): bool => str_contains(
            (string) ($request['interactive']['body']['text'] ?? $request['text']['body'] ?? ''),
            'Recibí el contacto'
        ));
    }

    public function test_empty_text_gets_recovery_and_duplicate_delivery_has_no_second_effect(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply.empty']]], 200),
        ]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Empresa mensaje vacío',
            'display_name' => 'Empresa mensaje vacío',
            'phone_number' => '593999999994',
            'phone_number_id' => 'PHONE-EMPTY',
            'whatsapp_business_id' => 'WABA-EMPTY',
            'access_token' => 'TEST_TOKEN',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);
        $message = [
            'from' => '593999999993',
            'id' => 'wamid.inbound.empty',
            'type' => 'text',
            'text' => '   ',
            'contacts' => [['wa_id' => '593999999993', 'profile' => ['name' => 'Cliente']]],
        ];

        $service->processIncomingMessage($message);
        $requestsAfterFirstDelivery = count(Http::recorded());
        $service->processIncomingMessage($message);

        $this->assertSame(1, WhatsappMessage::where('message_id', 'wamid.inbound.empty')->count());
        $this->assertSame($requestsAfterFirstDelivery, count(Http::recorded()));
        Http::assertSent(fn ($request): bool => str_contains(
            (string) ($request['interactive']['body']['text'] ?? $request['text']['body'] ?? ''),
            'No pude leer esa respuesta'
        ));
    }
}
