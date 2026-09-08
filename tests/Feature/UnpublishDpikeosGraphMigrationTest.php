<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowVersion;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: el flujo visual publicado le seguía mandando
 * al cliente el texto genérico de fábrica (sin imagen, sin marca) mientras
 * el admin ya tenía todo personalizado en el editor clásico. Se despublica
 * el grafo de Dpikeos automáticamente con la migración, para no depender de
 * que se corra un comando aparte.
 */
class UnpublishDpikeosGraphMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPublishedFlow(string $name, string $slug): MarketingFlow
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => $name, 'slug' => $slug, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => $name, 'display_name' => $name,
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $flow = MarketingFlow::create(['business_profile_id' => $profile->id, 'name' => 'Flujo', 'is_active' => true, 'is_default' => true]);
        MarketingFlowVersion::create([
            'flow_id' => $flow->id, 'version_number' => 1, 'is_current' => true, 'published_at' => now(),
            'snapshot' => ['start_node_uuid' => 'x', 'nodes' => [], 'edges' => []],
        ]);

        return $flow;
    }

    public function test_migration_unpublishes_dpikeos_but_leaves_other_companies_untouched(): void
    {
        $dpikeosFlow = $this->makeCompanyWithPublishedFlow('D\'pikeos', 'dpikeos');
        $otherFlow = $this->makeCompanyWithPublishedFlow('Otra Empresa', 'otra-empresa');

        // RefreshDatabase ya corrió esta migración (junto con todas las
        // demás) antes de que existieran estos flujos -- se invoca su up()
        // directo para simular que se corre DESPUÉS de que ya hay datos,
        // como pasaría en producción.
        (require base_path('database/migrations/2026_09_08_190000_unpublish_dpikeos_marketing_flow_graph.php'))->up();

        $this->assertFalse($dpikeosFlow->versions()->where('is_current', true)->exists(), 'El flujo de Dpikeos debe quedar despublicado.');
        $this->assertTrue($otherFlow->versions()->where('is_current', true)->exists(), 'El flujo de otra empresa no debe verse afectado.');
    }
}
