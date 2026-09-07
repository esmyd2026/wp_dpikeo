<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\BulkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkOrderContactSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_selector_lists_clients_and_searches_by_name_identity_or_phone(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS',
            'display_name' => 'DPIKEOS',
            'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business',
            'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593991234567',
            'name' => 'María Parrales',
            'national_id' => '0912345678',
            'status' => 'active',
        ]);

        WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => 'POS-TEST-1',
            'name' => 'Carlos Gómez',
            'billing_id' => '0999999999',
            'status' => 'active',
        ]);

        $service = app(BulkOrderService::class);

        $this->assertCount(2, $service->searchContacts('', 20, $profile->id));
        $this->assertSame('0912345678', $service->searchContacts('María', 20, $profile->id)[0]['identity']);
        $this->assertSame('María Parrales', $service->searchContacts('1234567', 20, $profile->id)[0]['name']);
        $this->assertSame('Carlos Gómez', $service->searchContacts('0999999999', 20, $profile->id)[0]['name']);
        $this->assertNull($service->searchContacts('Carlos', 20, $profile->id)[0]['phone']);
    }
}
