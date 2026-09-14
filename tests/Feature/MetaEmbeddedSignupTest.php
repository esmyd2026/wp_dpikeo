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

        // El PIN de registro se generó, se usó en /register, y quedó
        // guardado cifrado (igual que el token).
        $this->assertNotNull($profile->two_factor_pin);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $profile->two_factor_pin);
        $rawPin = DB::table('whatsapp_business_profiles')->where('id', $profile->id)->value('two_factor_pin');
        $this->assertNotSame($profile->two_factor_pin, $rawPin);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/register')
            && ($request['pin'] ?? null) === $profile->two_factor_pin
            && ($request['messaging_product'] ?? null) === 'whatsapp');
    }

    /**
     * Bug real encontrado en producción: Meta rechaza /register para
     * números en coexistencia ("Register endpoint is not available for SMB
     * businesses", code 100) -- ese paso debe omitirse por completo para
     * este modo, no reintentarse con datos distintos.
     */
    public function test_coexistence_mode_skips_the_register_step_that_meta_rejects_for_smb_numbers(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'COEX-TOKEN'], 200),
            'graph.facebook.com/*/COEX-PHONE-ID*' => Http::response([
                'id' => 'COEX-PHONE-ID',
                'display_phone_number' => '+593 95 952 0743',
                'verified_name' => 'Dpikeo',
            ], 200),
            'graph.facebook.com/*/COEX-WABA*' => Http::response(['id' => 'COEX-WABA', 'name' => 'Dpikeo WABA'], 200),
            'graph.facebook.com/*/subscribed_apps*' => Http::response(['success' => true], 200),
        ]);

        $company = $this->makeCompany('Dpikeo Coexistencia Test');

        $profile = app(MetaEmbeddedSignupService::class)->connect(
            $company, 'auth-code-coex', 'COEX-WABA', 'COEX-PHONE-ID', 'coexistence'
        );

        $this->assertSame('whatsapp_business_app_coexistence', $profile->connection_type);
        $this->assertSame(WhatsappBusinessProfile::STATUS_CONNECTED, $profile->status);

        // Handshake de coexistencia: exchange, getPhoneNumber, getWaba,
        // subscribed_apps -- SIN /register, que Meta rechaza para este modo.
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '/register'));
        $this->assertNull($profile->two_factor_pin);
    }

    /**
     * Bug real encontrado en producción: un reintento de conexión estándar
     * sobre un número que YA tiene un PIN de dos pasos establecido (de un
     * registro anterior exitoso) generaba un PIN nuevo al azar -- Meta lo
     * rechaza con "Two step verification PIN Mismatch" (133005) porque no
     * coincide con el que ya quedó puesto la primera vez. Esto solo aplica
     * al modo estándar -- coexistencia nunca llama a /register.
     */
    public function test_retrying_standard_connect_on_an_already_registered_number_reuses_the_stored_pin_instead_of_generating_a_new_one(): void
    {
        $company = $this->makeCompany('Dpikeo Retry Test');

        WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'Dpikeo',
            'display_name' => 'Dpikeo',
            'phone_number' => '593959520743',
            'phone_number_id' => 'RETRY-PHONE-ID',
            'whatsapp_business_id' => 'RETRY-WABA',
            'access_token' => 'old-token',
            'two_factor_pin' => '482913',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'connection_type' => 'embedded_signup',
        ]);

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'NEW-TOKEN'], 200),
            'graph.facebook.com/*/RETRY-PHONE-ID*' => Http::response([
                'id' => 'RETRY-PHONE-ID',
                'display_phone_number' => '+593 95 952 0743',
                'verified_name' => 'Dpikeo',
            ], 200),
            'graph.facebook.com/*/RETRY-WABA*' => Http::response(['id' => 'RETRY-WABA', 'name' => 'Dpikeo WABA'], 200),
            'graph.facebook.com/*/subscribed_apps*' => Http::response(['success' => true], 200),
        ]);

        $profile = app(MetaEmbeddedSignupService::class)->connect(
            $company, 'auth-code-retry', 'RETRY-WABA', 'RETRY-PHONE-ID', 'standard'
        );

        $this->assertSame('482913', $profile->two_factor_pin);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/register')
            && ($request['pin'] ?? null) === '482913');
    }

    /**
     * Bug real en producción: cuando Graph API rechaza el paso final de
     * /register (número ya obtenido, WABA ya obtenida), el catch guardaba un
     * registro "error" sin la columna phone_number (NOT NULL + única, sin
     * default) -- el INSERT crudo tiraba una SQLSTATE que tapaba por completo
     * el mensaje real de Meta ("No se pudo completar el registro del número
     * en Cloud API").
     */
    public function test_a_register_failure_saves_an_error_row_without_crashing_and_keeps_the_real_message(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'FAILING-TOKEN'], 200),
            'graph.facebook.com/*/FAIL-PHONE-ID/register*' => Http::response(['error' => ['message' => 'Two step verification PIN Mismatch']], 400),
            'graph.facebook.com/*/FAIL-PHONE-ID*' => Http::response([
                'id' => 'FAIL-PHONE-ID',
                'display_phone_number' => "D'pikeos",
                'verified_name' => "D'pikeos",
            ], 200),
            'graph.facebook.com/*/FAIL-WABA*' => Http::response(['id' => 'FAIL-WABA', 'name' => "D'pikeos WABA"], 200),
            'graph.facebook.com/*/subscribed_apps*' => Http::response(['success' => true], 200),
        ]);

        $company = $this->makeCompany("D'pikeos Register Fail Test");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo completar el registro del número en Cloud API.');

        try {
            app(MetaEmbeddedSignupService::class)->connect($company, 'auth-code-fail', 'FAIL-WABA', 'FAIL-PHONE-ID', 'standard');
        } finally {
            $profile = WhatsappBusinessProfile::where('company_id', $company->id)->where('phone_number_id', 'FAIL-PHONE-ID')->first();
            $this->assertNotNull($profile, 'Debe quedar un registro de error, no una excepción SQL cruda.');
            $this->assertSame(WhatsappBusinessProfile::STATUS_ERROR, $profile->status);
            $this->assertNotNull($profile->phone_number);
            $this->assertStringContainsString('No se pudo completar el registro del número en Cloud API.', $profile->metadata['last_error'] ?? '');
        }
    }
}
