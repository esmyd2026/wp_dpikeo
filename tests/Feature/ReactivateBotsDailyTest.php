<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: "que diariamente se active [el bot] para todos los
 * clientes excepto a los de la lista negra." Este comando (whatsapp:
 * reactivate-bots-daily, programado a las 06:00 en Kernel.php) solo cambia
 * el interruptor -- nunca reprocesa ni reenvía ningún mensaje, así que no
 * corre el mismo riesgo que llevó a desactivar whatsapp:retry-pending-
 * replies (ver ScheduledCommandsSafetyTest).
 */
class ReactivateBotsDailyTest extends TestCase
{
    use RefreshDatabase;

    private function makeContact(string $suffix, bool $botEnabled, bool $blacklisted = false): WhatsappContact
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Reactivar', 'slug' => 'empresa-reactivar-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Reactivar', 'display_name' => 'Empresa Reactivar',
            'phone_number' => '593996'.$suffix, 'phone_number_id' => 'REACT-PHONE-'.$suffix,
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);

        return WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593999300'.$suffix, 'name' => 'Cliente',
            'bot_enabled' => $botEnabled, 'bot_blacklisted' => $blacklisted,
        ]);
    }

    public function test_it_reactivates_paused_contacts_that_are_not_blacklisted(): void
    {
        $contact = $this->makeContact('01', botEnabled: false);

        $this->artisan('whatsapp:reactivate-bots-daily')->assertExitCode(0);

        $this->assertTrue($contact->fresh()->bot_enabled);
    }

    public function test_it_never_touches_blacklisted_contacts(): void
    {
        $contact = $this->makeContact('02', botEnabled: false, blacklisted: true);

        $this->artisan('whatsapp:reactivate-bots-daily')->assertExitCode(0);

        $this->assertFalse($contact->fresh()->bot_enabled);
    }

    public function test_it_leaves_already_enabled_contacts_alone(): void
    {
        $contact = $this->makeContact('03', botEnabled: true);
        $updatedAt = $contact->updated_at;

        $this->artisan('whatsapp:reactivate-bots-daily')->assertExitCode(0);

        $this->assertTrue($contact->fresh()->bot_enabled);
        $this->assertEquals($updatedAt, $contact->fresh()->updated_at);
    }
}
