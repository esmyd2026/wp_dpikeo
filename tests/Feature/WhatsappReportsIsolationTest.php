<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\WhatsappReportsController;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\ConsumptionReportService;
use App\Services\PermissionService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * WhatsappReportsController (métricas + ConsumptionReportService) agrega
 * sobre whatsapp_messages/whatsapp_contacts/whatsapp_campaigns sin pasar por
 * ningún modelo con "scope" propio -- es el tipo de código donde un filtro
 * olvidado mezcla en silencio los números de dos empresas. Estas pruebas
 * arman datos reales para dos empresas y verifican que cada métrica,
 * agregado y filtro (fecha, conversación/respuesta, contacto) devuelva
 * exclusivamente lo de la empresa activa.
 */
class WhatsappReportsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    private function adminUser(): User
    {
        $role = Role::where('slug', 'admin')->firstOrFail();

        return User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    }

    /** @return array{company: Company, profile: WhatsappBusinessProfile, user: User} */
    private function makeCompany(string $slug): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => $slug,
            'display_name' => $slug,
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => strtoupper($slug) . '-PHONE',
            'access_token' => 'token-' . $slug,
            'status' => 'connected',
        ]);

        $user = $this->adminUser();
        $company->users()->attach($user->id);

        return compact('company', 'profile', 'user');
    }

    private function seedConversation(WhatsappBusinessProfile $profile, string $phone, int $clientMessages, bool $withReplies, Carbon $at): WhatsappContact
    {
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => $phone,
            'name' => 'Cliente ' . $phone,
            'status' => 'active',
        ]);

        for ($i = 0; $i < $clientMessages; $i++) {
            $sentAt = $at->copy()->addMinutes($i * 10);

            // created_at/updated_at no son mass-assignable en WhatsappMessage
            // (a propósito): se crea normal y se pisa la fecha aparte, para
            // poder simular mensajes de hace días/semanas en la prueba.
            $client = WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $profile->id,
                'message_id' => 'wamid.' . Str::random(12),
                'sender_type' => 'client',
                'content' => "Mensaje cliente {$i}",
                'type' => 'text',
                'status' => 'received',
            ]);
            $client->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();

            if ($withReplies) {
                $replyAt = $sentAt->copy()->addMinutes(5);
                $reply = WhatsappMessage::create([
                    'contact_id' => $contact->id,
                    'business_profile_id' => $profile->id,
                    'message_id' => 'wamid.' . Str::random(12),
                    'sender_type' => 'system',
                    'content' => "Respuesta bot {$i}",
                    'type' => 'text',
                    'status' => 'sent',
                ]);
                $reply->forceFill(['created_at' => $replyAt, 'updated_at' => $replyAt])->save();
            }
        }

        return $contact;
    }

    private function reportFor(Company $company): array
    {
        CompanyContext::switchTo($company);
        $response = app(WhatsappReportsController::class)->index(
            Request::create('/admin/reports/whatsapp', 'GET', ['period' => 'all']),
            app(ConsumptionReportService::class)
        );

        return $response->getData(true);
    }

    public function test_each_company_sees_only_its_own_message_counts(): void
    {
        $a = $this->makeCompany('piqueo');
        $b = $this->makeCompany('zapatos-demo');

        $now = Carbon::now()->subDays(2);

        // Piqueo: 5 mensajes de cliente, todos respondidos.
        $this->seedConversation($a['profile'], '593900000001', 5, true, $now);
        // Zapatos Demo: 3 mensajes de cliente, sin respuesta.
        $this->seedConversation($b['profile'], '593900000002', 3, false, $now);

        $this->actingAs($a['user']);
        $reportA = $this->reportFor($a['company']);
        // Piqueo: 5 del cliente + 5 respuestas = 10.
        $this->assertSame(10, $reportA['metrics']['period_messages']);
        $this->assertSame(5, $reportA['metrics']['received']);
        $this->assertSame(5, $reportA['metrics']['sent']);
        $this->assertSame(1, $reportA['metrics']['new_contacts']);

        $this->actingAs($b['user']);
        $reportB = $this->reportFor($b['company']);
        // Zapatos Demo: solo 3 mensajes de cliente, cero respuestas.
        $this->assertSame(3, $reportB['metrics']['period_messages']);
        $this->assertSame(3, $reportB['metrics']['received']);
        $this->assertSame(0, $reportB['metrics']['sent']);
        $this->assertSame(1, $reportB['metrics']['new_contacts']);
    }

    public function test_piqueo_never_pulls_in_zapatos_demo_records_and_vice_versa(): void
    {
        $a = $this->makeCompany('piqueo');
        $b = $this->makeCompany('zapatos-demo');
        $now = Carbon::now()->subDay();

        $this->seedConversation($a['profile'], '593900000001', 4, true, $now);
        $this->seedConversation($b['profile'], '593900000002', 7, true, $now);

        $this->actingAs($a['user']);
        $reportA = $this->reportFor($a['company']);
        $this->assertSame(8, $reportA['metrics']['period_messages']); // 4+4, nunca 7+7

        $this->actingAs($b['user']);
        $reportB = $this->reportFor($b['company']);
        $this->assertSame(14, $reportB['metrics']['period_messages']); // 7+7, nunca 4+4
    }

    public function test_same_phone_number_in_both_companies_counts_as_two_separate_contacts(): void
    {
        $a = $this->makeCompany('piqueo');
        $b = $this->makeCompany('zapatos-demo');
        $now = Carbon::now();
        $samePhone = '593987654321';

        $this->seedConversation($a['profile'], $samePhone, 2, true, $now);
        $this->seedConversation($b['profile'], $samePhone, 1, false, $now);

        $this->actingAs($a['user']);
        $reportA = $this->reportFor($a['company']);
        $this->assertSame(1, $reportA['metrics']['new_contacts']);
        $this->assertSame(4, $reportA['metrics']['period_messages']);

        $this->actingAs($b['user']);
        $reportB = $this->reportFor($b['company']);
        $this->assertSame(1, $reportB['metrics']['new_contacts']);
        $this->assertSame(1, $reportB['metrics']['period_messages']);
    }

    public function test_date_range_filter_is_combined_with_company_scope_not_replacing_it(): void
    {
        $a = $this->makeCompany('piqueo');
        $b = $this->makeCompany('zapatos-demo');

        // Piqueo: 2 mensajes dentro del rango, 3 fuera (hace 60 días).
        $this->seedConversation($a['profile'], '593900000001', 2, false, Carbon::now()->subDays(2));
        $this->seedConversation($a['profile'], '593900000003', 3, false, Carbon::now()->subDays(60));
        // Zapatos Demo: 9 mensajes dentro del mismo rango de fecha.
        $this->seedConversation($b['profile'], '593900000002', 9, false, Carbon::now()->subDays(2));

        $this->actingAs($a['user']);
        CompanyContext::switchTo($a['company']);
        $response = app(WhatsappReportsController::class)->index(
            Request::create('/admin/reports/whatsapp', 'GET', ['period' => '7d']),
            app(ConsumptionReportService::class)
        );
        $report = $response->getData(true);

        // Con el filtro de 7 días: solo los 2 recientes de Piqueo -- ni los
        // 3 viejos de Piqueo (fuera de rango) ni los 9 de Zapatos Demo
        // (dentro de rango pero de otra empresa).
        $this->assertSame(2, $report['metrics']['period_messages']);
    }

    public function test_response_rate_never_matches_a_reply_from_the_other_company(): void
    {
        $a = $this->makeCompany('piqueo');
        $b = $this->makeCompany('zapatos-demo');
        $now = Carbon::now()->subHours(2);

        // Piqueo: el cliente escribe pero NADIE de Piqueo responde.
        $this->seedConversation($a['profile'], '593900000001', 1, false, $now);
        // Zapatos Demo: sí responde a su propio cliente en la misma ventana.
        $this->seedConversation($b['profile'], '593900000002', 1, true, $now);

        $this->actingAs($a['user']);
        $reportA = $this->reportFor($a['company']);
        // Si la respuesta de Zapatos Demo se filtrara para acá, esto daría 100.
        $this->assertSame(0.0, $reportA['metrics']['response_rate']);

        $this->actingAs($b['user']);
        $reportB = $this->reportFor($b['company']);
        $this->assertSame(100.0, $reportB['metrics']['response_rate']);
    }

    public function test_consumption_report_campaign_categories_are_isolated_per_company(): void
    {
        // "marketing" no está en las categorías habilitadas por defecto
        // (solo service/utility); se activa para poder medirla en esta prueba.
        \App\Models\PricingSetting::current()->update([
            'enabled_categories' => ['service', 'utility', 'marketing'],
        ]);

        $a = $this->makeCompany('piqueo');
        $b = $this->makeCompany('zapatos-demo');
        $now = Carbon::now()->subDay();

        WhatsappCampaign::create([
            'business_profile_id' => $a['profile']->id,
            'name' => 'Campaña Piqueo',
            'message_type' => 'template',
            'status' => 'completed',
            'sent_count' => 40,
            'sent_at' => $now,
            'recipient_type' => 'all',
        ]);
        WhatsappCampaign::create([
            'business_profile_id' => $b['profile']->id,
            'name' => 'Campaña Zapatos Demo',
            'message_type' => 'template',
            'status' => 'completed',
            'sent_count' => 15,
            'sent_at' => $now,
            'recipient_type' => 'all',
        ]);

        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        $reportA = app(ConsumptionReportService::class)->build($from, $to, $a['profile']->id);
        $reportB = app(ConsumptionReportService::class)->build($from, $to, $b['profile']->id);

        $this->assertSame(40, $reportA['categories']['marketing']['count'] ?? null);
        $this->assertSame(15, $reportB['categories']['marketing']['count'] ?? null);
    }
}
