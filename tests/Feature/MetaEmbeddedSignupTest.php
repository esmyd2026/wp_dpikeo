<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Services\MetaEmbeddedSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * El handshake de Meta Embedded Signup nunca debe poder "robar" un número ya
 * conectado a otra empresa, y el resultado final debe quedar asociado
 * exclusivamente a la empresa correcta, con el token cifrado en la base.
 */
class MetaEmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $name): Company
    {
        return Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
        ]);
    }

    public function test_connecting_a_phone_number_id_already_owned_by_another_company_is_rejected(): void
    {
        $owner = $this->makeCompany('Piqueo');
        WhatsappBusinessProfile::create([
            'company_id' => $owner->id,
            'business_name' => 'Piqueo',
            'display_name' => 'Piqueo',
            'phone_number' => '593900000001',
            'phone_number_id' => 'SHARED-PHONE-ID',
            'access_token' => 'token-owner',
            'status' => 'connected',
        ]);

        $intruder = $this->makeCompany('Zapatos Demo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya está conectado a otra empresa');

        app(MetaEmbeddedSignupService::class)->connect($intruder, 'some-code', 'WABA-X', 'SHARED-PHONE-ID');

        // No debe haberse creado ninguna fila para el intruso ni haberse
        // llamado a Graph API (la excepción se lanza antes de intentarlo).
        $this->assertNull(
            WhatsappBusinessProfile::where('company_id', $intruder->id)->where('phone_number_id', 'SHARED-PHONE-ID')->first()
        );
    }

    public function test_successful_connect_scopes_the_new_profile_to_the_selected_company_and_encrypts_the_token(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'REAL-BUSINESS-TOKEN'], 200),
            'graph.facebook.com/*/NEW-PHONE-ID*' => Http::response([
                'id' => 'NEW-PHONE-ID',
                'display_phone_number' => '+593 99 000 0002',
                'verified_name' => 'Zapatos Demo Oficial',
            ], 200),
            'graph.facebook.com/*/WABA-NEW*' => Http::response(['id' => 'WABA-NEW', 'name' => 'Zapatos Demo WABA'], 200),
            'graph.facebook.com/*/subscribed_apps*' => Http::response(['success' => true], 200),
        ]);

        $company = $this->makeCompany('Zapatos Demo');

        $profile = app(MetaEmbeddedSignupService::class)->connect($company, 'auth-code-123', 'WABA-NEW', 'NEW-PHONE-ID');

        $this->assertSame($company->id, $profile->company_id);
        $this->assertSame('NEW-PHONE-ID', $profile->phone_number_id);
        $this->assertSame('WABA-NEW', $profile->whatsapp_business_id);
        $this->assertSame(WhatsappBusinessProfile::STATUS_CONNECTED, $profile->status);
        $this->assertSame('embedded_signup', $profile->connection_type);

        // El cast 'encrypted' descifra en memoria; lo que importa es que en
        // la columna cruda de la base nunca quede el token en texto plano.
        $this->assertSame('REAL-BUSINESS-TOKEN', $profile->access_token);
        $rawColumn = DB::table('whatsapp_business_profiles')->where('id', $profile->id)->value('access_token');
        $this->assertNotSame('REAL-BUSINESS-TOKEN', $rawColumn);
    }
}
