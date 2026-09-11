<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageFailure;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappExternalFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_error_is_returned_and_recorded_without_creating_a_false_sent_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['code' => 131000, 'type' => 'OAuthException', 'message' => 'Fallo simulado de Meta'],
            ], 500),
        ]);

        [$service, $contact, $profile] = $this->serviceAndContact();
        $result = $service->sendTextMessage($contact, 'Mensaje estrictamente local');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('500', $result['error']);
        $this->assertDatabaseHas('whatsapp_message_failures', [
            'business_profile_id' => $profile->id,
            'contact_id' => $contact->id,
            'message_type' => 'text',
            'source' => 'sendTextMessage',
        ]);
        $this->assertSame(0, WhatsappMessage::where('contact_id', $contact->id)->count());
    }

    public function test_missing_business_profile_fails_locally_without_attempting_http(): void
    {
        Http::fake();
        $contact = WhatsappContact::create([
            'phone_number' => '593990000010',
            'name' => 'Cliente local',
            'status' => 'active',
        ]);

        $result = (new WhatsappService)->sendTextMessage($contact, 'No debe salir');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no configurado', $result['error']);
        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessageFailure::count());
    }

    /** @return array{WhatsappService, WhatsappContact, WhatsappBusinessProfile} */
    private function serviceAndContact(): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Empresa local',
            'display_name' => 'Empresa local',
            'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-LOCAL-FAILURE',
            'whatsapp_business_id' => 'WABA-LOCAL-FAILURE',
            'access_token' => 'TEST_ONLY_TOKEN',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593990000002',
            'name' => 'Cliente local',
            'status' => 'active',
        ]);
        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId($profile->phone_number_id);

        return [$service, $contact, $profile];
    }
}
