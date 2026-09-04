<?php

namespace Tests\Feature;

use App\Exceptions\WhatsappBusinessProfileUnavailableException;
use App\Exceptions\WhatsappPrimaryProfileNotConfiguredException;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use App\Services\WhatsappService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Selección segura del perfil de WhatsApp cuando una empresa tiene varios
 * WhatsappBusinessProfile: nunca se elige el primero de la tabla. Se prioriza
 * is_primary; con exactamente un usable se resuelve solo; con 2+ usables y
 * ninguno marcado principal, falla explícito en vez de adivinar.
 */
class WhatsappPrimaryProfileResolutionTest extends TestCase
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

    private function makeCompany(string $slug): Company
    {
        return Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    /**
     * Ejecuta la lógica REAL de la migración de backfill (no una reimplementación)
     * contra los datos ya insertados por el test -- simula exactamente lo que
     * pasaría al correrla contra producción con datos existentes, sin tocar
     * producción. `require` (no `require_once`) porque el archivo devuelve una
     * clase anónima nueva cada vez que se ejecuta.
     */
    private function runBackfillMigration(): void
    {
        $migration = require database_path('migrations/2026_09_05_000002_backfill_whatsapp_business_profiles_primary_and_legacy_status.php');
        $migration->up();
    }

    private function makeProfile(Company $company, string $suffix, array $overrides = []): WhatsappBusinessProfile
    {
        return WhatsappBusinessProfile::create(array_merge([
            'company_id' => $company->id,
            'business_name' => $company->name,
            'display_name' => $company->name . " {$suffix}",
            'phone_number' => '593' . random_int(100000000, 999999999),
            'phone_number_id' => strtoupper($company->slug) . "-{$suffix}",
            'access_token' => "token-{$suffix}",
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ], $overrides));
    }

    public function test_one_usable_profile_resolves_automatically_without_being_marked_primary(): void
    {
        $company = $this->makeCompany('empresa-unica');
        $profile = $this->makeProfile($company, 'A');

        $context = CompanyContext::forCompany($company);

        $this->assertSame($profile->id, $context->businessProfile->id);
    }

    public function test_two_usable_profiles_with_one_primary_uses_the_primary(): void
    {
        $company = $this->makeCompany('empresa-dos');
        $this->makeProfile($company, 'A');
        $primary = $this->makeProfile($company, 'B', ['is_primary' => true]);

        $context = CompanyContext::forCompany($company);

        $this->assertSame($primary->id, $context->businessProfile->id);
    }

    public function test_two_usable_profiles_with_no_primary_fails_closed(): void
    {
        $company = $this->makeCompany('empresa-ambigua');
        $this->makeProfile($company, 'A');
        $this->makeProfile($company, 'B');

        $this->expectException(WhatsappPrimaryProfileNotConfiguredException::class);
        $this->expectExceptionMessage('WHATSAPP_PRIMARY_PROFILE_NOT_CONFIGURED');

        CompanyContext::forCompany($company);
    }

    public function test_changing_primary_unmarks_the_previous_one_only_within_the_same_company(): void
    {
        $company = $this->makeCompany('empresa-switch');
        $oldPrimary = $this->makeProfile($company, 'A', ['is_primary' => true]);
        $newPrimary = $this->makeProfile($company, 'B');

        $otherCompany = $this->makeCompany('empresa-ajena');
        $otherPrimary = $this->makeProfile($otherCompany, 'C', ['is_primary' => true]);

        $user = $this->adminUser();
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)->post(
            route('admin.empresas.whatsapp.profile.set-primary', [$company, $newPrimary])
        );

        $response->assertRedirect();
        $this->assertFalse($oldPrimary->fresh()->is_primary);
        $this->assertTrue($newPrimary->fresh()->is_primary);

        // La empresa ajena ni se tocó.
        $this->assertTrue($otherPrimary->fresh()->is_primary);
    }

    public function test_a_disconnected_profile_can_never_be_the_effective_primary(): void
    {
        $company = $this->makeCompany('empresa-desconectada');
        // is_primary=true forzado a mano (fila vieja/inconsistente) sobre un
        // perfil desconectado -- forCompany() debe ignorarlo igual, porque
        // primero filtra usable() y recién ahí mira is_primary.
        $disconnectedButFlagged = $this->makeProfile($company, 'A', [
            'is_primary' => true,
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);
        $onlyUsable = $this->makeProfile($company, 'B');

        $context = CompanyContext::forCompany($company);

        $this->assertSame($onlyUsable->id, $context->businessProfile->id);
        $this->assertNotSame($disconnectedButFlagged->id, $context->businessProfile->id);
    }

    public function test_set_primary_action_rejects_a_disconnected_profile(): void
    {
        $company = $this->makeCompany('empresa-set-primary-invalido');
        $disconnected = $this->makeProfile($company, 'A', [
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);

        $user = $this->adminUser();
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)->post(
            route('admin.empresas.whatsapp.profile.set-primary', [$company, $disconnected])
        );

        $response->assertStatus(422);
        $this->assertFalse($disconnected->fresh()->is_primary);
    }

    public function test_a_company_with_context_but_no_business_profile_never_falls_back_to_the_global_env_token(): void
    {
        $service = app(WhatsappService::class);

        try {
            $service->useBusinessProfile(null);
            $this->fail('Se esperaba WhatsappBusinessProfileUnavailableException.');
        } catch (WhatsappBusinessProfileUnavailableException $e) {
            // esperado
        }

        // apiToken() es protegido; se invoca a través de un método público
        // que lo usa (buildTemplateVariables no aplica -- probamos vía
        // reflection para no depender de un envío real).
        $reflection = new \ReflectionMethod($service, 'apiToken');
        $reflection->setAccessible(true);

        $this->expectException(WhatsappBusinessProfileUnavailableException::class);
        $reflection->invoke($service);
    }

    public function test_job_loop_never_reuses_the_previous_contacts_credentials_when_resolution_fails_for_the_next_one(): void
    {
        $companyA = $this->makeCompany('empresa-loop-a');
        $profileA = $this->makeProfile($companyA, 'A');

        $companyB = $this->makeCompany('empresa-loop-b');
        $disconnectedB = $this->makeProfile($companyB, 'B', [
            'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);

        // Misma instancia reutilizada entre "contactos" de A y B, igual que
        // SendWhatsAppTemplate/PendingReplyRecoveryService.
        $service = app(WhatsappService::class);

        $service->useBusinessProfile($profileA);
        $this->assertSame($profileA->id, $service->getBusinessProfile()->id);

        try {
            $service->useBusinessProfile($disconnectedB);
            $this->fail('Se esperaba WhatsappBusinessProfileUnavailableException.');
        } catch (WhatsappBusinessProfileUnavailableException $e) {
            // esperado
        }

        // Ni token de A (ya no corresponde a este intento) ni token nulo
        // convertido en fallback global: la instancia queda sin perfil.
        $this->assertNull($service->getBusinessProfile());
    }

    /**
     * Simulación exacta del escenario real de producción antes del deploy:
     * dpikeo con DPIKEOS (manual, el phone_number_id que YA estaba en el
     * .env desde antes de multiempresa) + Dpikeo2 (embedded signup, número
     * de prueba de Meta, otro phone_number_id). La migración debe marcar
     * principal a DPIKEOS -- nunca a Dpikeo2 -- porque coincide de forma
     * inequívoca con WHATSAPP_PHONE_NUMBER_ID.
     */
    public function test_backfill_migration_marks_the_env_matching_manual_profile_primary_not_the_embedded_signup_one(): void
    {
        $legacyPhoneNumberId = config('whatsapp.phone_number_id');
        $this->assertNotEmpty($legacyPhoneNumberId, 'Este test necesita WHATSAPP_PHONE_NUMBER_ID configurado en el entorno de test.');

        $dpikeo = $this->makeCompany('dpikeo-simulacion');

        $dpikeosManual = $this->makeProfile($dpikeo, 'DPIKEOS', [
            'display_name' => 'DPIKEOS',
            'connection_type' => 'manual',
            'phone_number_id' => $legacyPhoneNumberId,
        ]);

        $dpikeo2Embedded = $this->makeProfile($dpikeo, 'DPIKEO2', [
            'display_name' => 'Dpikeo2',
            'connection_type' => 'embedded_signup',
            'phone_number_id' => '1258310880705178', // número de prueba real de Meta, distinto del .env
        ]);

        // Nadie marcado principal todavía -- así llegaría producción hoy.
        $this->assertFalse((bool) $dpikeosManual->fresh()->is_primary);
        $this->assertFalse((bool) $dpikeo2Embedded->fresh()->is_primary);

        $this->runBackfillMigration();

        $this->assertTrue($dpikeosManual->fresh()->is_primary, 'DPIKEOS (coincide con .env) debía quedar principal.');
        $this->assertFalse($dpikeo2Embedded->fresh()->is_primary, 'Dpikeo2 (número de prueba) NUNCA debe quedar principal.');

        // Y la resolución real de la empresa ya no es ambigua.
        $context = CompanyContext::forCompany($dpikeo);
        $this->assertSame($dpikeosManual->id, $context->businessProfile->id);
    }

    /**
     * Si por alguna razón NINGUNO de los perfiles coincide con el
     * .env (ej. el número real cambió y todavía no se actualizó
     * WHATSAPP_PHONE_NUMBER_ID, o los dos son números nuevos), la migración
     * NUNCA debe adivinar -- la empresa queda sin principal y
     * CompanyContext::forCompany() falla explícito hasta que un admin elija
     * desde el panel.
     */
    public function test_backfill_migration_leaves_the_company_without_a_primary_when_neither_profile_matches_env(): void
    {
        $dpikeo = $this->makeCompany('dpikeo-simulacion-sin-match');

        $profileA = $this->makeProfile($dpikeo, 'X', ['phone_number_id' => 'NINGUNO-COINCIDE-A']);
        $profileB = $this->makeProfile($dpikeo, 'Y', ['phone_number_id' => 'NINGUNO-COINCIDE-B']);

        $this->runBackfillMigration();

        $this->assertFalse($profileA->fresh()->is_primary);
        $this->assertFalse($profileB->fresh()->is_primary);

        $this->expectException(WhatsappPrimaryProfileNotConfiguredException::class);
        CompanyContext::forCompany($dpikeo);
    }
}
