<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappCredentialService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cubre el criterio de aceptación de la arquitectura multiempresa: dos
 * empresas conectadas al mismo deployment nunca deben mezclar credenciales,
 * configuración del bot, catálogo, contactos o resolución de webhook, aunque
 * el mismo número de cliente le escriba a ambas.
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{company: Company, profile: WhatsappBusinessProfile, config: WhatsappChatbotConfig, menu: WhatsappMenu, category: WhatsappMenuItem, product: WhatsappPrice} */
    private function makeCompanyWithCatalog(string $slug, string $phoneNumberId, string $token, string $botName): array
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
            'phone_number_id' => $phoneNumberId,
            'whatsapp_business_id' => "WABA-{$slug}",
            'access_token' => $token,
            'status' => 'connected',
        ]);

        $config = WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['bot_name' => $botName],
        ]);

        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => "Menu {$slug}",
            'type' => 'list',
            'content' => 'contenido',
            'action_id' => 'prices_menu',
            'is_active' => true,
        ]);

        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'business_profile_id' => $profile->id,
            'title' => "Categoria {$slug}",
            'action_id' => "cat_{$slug}",
            'is_active' => true,
        ]);

        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'business_profile_id' => $profile->id,
            'category' => $category->title,
            'sku' => strtoupper($slug) . '-1',
            'name' => "Producto {$slug}",
            'price' => 10,
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 5,
        ]);

        return compact('company', 'profile', 'config', 'menu', 'category', 'product');
    }

    private function invoke(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }

    public function test_company_and_business_profile_belong_to_each_other(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        $this->assertNotEquals($a['company']->id, $b['company']->id);
        $this->assertSame($a['company']->id, $a['profile']->company_id);
        $this->assertSame($b['company']->id, $b['profile']->company_id);
        $this->assertTrue($a['company']->whatsappAccounts->contains($a['profile']));
        $this->assertFalse($a['company']->whatsappAccounts->contains($b['profile']));
    }

    public function test_access_token_is_encrypted_at_rest_but_decrypts_transparently(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');

        $raw = DB::table('whatsapp_business_profiles')->where('id', $a['profile']->id)->value('access_token');

        $this->assertNotSame('TOKEN-A', $raw, 'El token no debe quedar en texto plano en la base.');
        $this->assertSame('TOKEN-A', $a['profile']->fresh()->access_token);
    }

    public function test_credential_service_resolves_the_right_account_per_company(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        $credentials = app(WhatsappCredentialService::class);

        $this->assertSame('TOKEN-A', $credentials->forCompany($a['company'])->access_token);
        $this->assertSame('TOKEN-B', $credentials->forCompany($b['company'])->access_token);
        $this->assertSame($a['profile']->id, $credentials->byPhoneNumberId('PHONE-A')->id);
        $this->assertSame($b['profile']->id, $credentials->byPhoneNumberId('PHONE-B')->id);
    }

    public function test_webhook_phone_number_id_resolves_matching_company_token_and_bot_config(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        $serviceA = app(WhatsappService::class);
        $serviceA->setWebhookPhoneNumberId('PHONE-A');
        $this->assertSame('TOKEN-A', $this->invoke($serviceA, 'apiToken'));
        $this->assertSame('Bot A', $this->invoke($serviceA, 'scopedChatbotConfig')->bot_name);
        $this->assertSame($a['menu']->id, $this->invoke($serviceA, 'menuByActionId', ['prices_menu'])->id);

        $serviceB = app(WhatsappService::class);
        $serviceB->setWebhookPhoneNumberId('PHONE-B');
        $this->assertSame('TOKEN-B', $this->invoke($serviceB, 'apiToken'));
        $this->assertSame('Bot B', $this->invoke($serviceB, 'scopedChatbotConfig')->bot_name);
        $this->assertSame($b['menu']->id, $this->invoke($serviceB, 'menuByActionId', ['prices_menu'])->id);
    }

    public function test_unknown_phone_number_id_is_never_processed_with_another_companys_credentials(): void
    {
        $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-DESCONOCIDO');

        $service->processIncomingMessage([
            'from' => '593999999999',
            'id' => 'wamid.unknown-test',
            'type' => 'text',
            'text' => ['body' => 'hola'],
        ]);

        // Nunca debió crear un contacto ni un mensaje usando el perfil de
        // otra empresa como fallback silencioso.
        $this->assertDatabaseMissing('whatsapp_contacts', ['phone_number' => '593999999999']);
        $this->assertDatabaseMissing('whatsapp_messages', ['message_id' => 'wamid.unknown-test']);
    }

    public function test_same_customer_phone_number_gets_independent_contacts_per_company(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        $sameNumber = '593987654321';

        $contactA = WhatsappContact::firstOrCreate(
            ['phone_number' => $sameNumber, 'business_profile_id' => $a['profile']->id],
            ['name' => 'Cliente en A', 'status' => 'active']
        );
        $contactB = WhatsappContact::firstOrCreate(
            ['phone_number' => $sameNumber, 'business_profile_id' => $b['profile']->id],
            ['name' => 'Cliente en B', 'status' => 'active']
        );

        $this->assertNotEquals($contactA->id, $contactB->id);
        $this->assertSame(2, WhatsappContact::where('phone_number', $sameNumber)->count());

        // Un estado de conversación guardado para uno no debe filtrarse al otro.
        $contactA->forceFill(['metadata' => ['current_graph_node' => 'esperando_pago']])->save();
        $this->assertNull($contactB->fresh()->metadata['current_graph_node'] ?? null);
    }

    /**
     * Bug real encontrado en producción: un cliente que ya había chateado con
     * la empresa A (mismo teléfono) mandaba una respuesta de lista/botón
     * (list_reply) a la empresa B, y handleInteractiveMessage() -- a
     * diferencia de handleTextMessage() -- buscaba el contacto SIN escopar
     * por business_profile_id, así que reusaba el contacto de la empresa A.
     * El pedido terminaba armado (y hasta enviado) con las credenciales de la
     * empresa A en vez de la B.
     */
    public function test_interactive_list_reply_never_reuses_a_contact_from_another_company(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        $sameNumber = '593987654321';

        $contactA = WhatsappContact::create([
            'business_profile_id' => $a['profile']->id,
            'phone_number' => $sameNumber,
            'name' => 'Cliente en A',
            'status' => 'active',
        ]);

        $serviceB = app(WhatsappService::class);
        $serviceB->setWebhookPhoneNumberId('PHONE-B');

        $serviceB->processIncomingMessage([
            'from' => $sameNumber,
            'id' => 'wamid.list-reply-test',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list_reply',
                'list_reply' => ['id' => 'algun_boton_no_reconocido', 'title' => 'Opción'],
            ],
        ]);

        $contactB = WhatsappContact::where('phone_number', $sameNumber)
            ->where('business_profile_id', $b['profile']->id)
            ->first();

        $this->assertNotNull($contactB, 'Debe crearse un contacto propio de la empresa B, no reusar el de A.');
        $this->assertNotEquals($contactA->id, $contactB->id);

        $message = \App\Models\WhatsappMessage::where('message_id', 'wamid.list-reply-test')->first();
        $this->assertSame($b['profile']->id, $message->business_profile_id);
        $this->assertSame($contactB->id, $message->contact_id);
    }

    public function test_catalog_and_prices_are_isolated_per_company(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        $categoriesA = WhatsappMenuItem::catalogCategories($a['profile']->id)->pluck('id')->all();
        $categoriesB = WhatsappMenuItem::catalogCategories($b['profile']->id)->pluck('id')->all();

        $this->assertContains($a['category']->id, $categoriesA);
        $this->assertNotContains($b['category']->id, $categoriesA);
        $this->assertContains($b['category']->id, $categoriesB);
        $this->assertNotContains($a['category']->id, $categoriesB);

        $this->assertSame(1, WhatsappPrice::where('business_profile_id', $a['profile']->id)->count());
        $this->assertSame(1, WhatsappPrice::where('business_profile_id', $b['profile']->id)->count());

        $statsA = WhatsappPrice::summaryStats($a['profile']->id);
        $this->assertSame(1, $statsA['total']);
    }

    public function test_two_companies_can_reuse_the_same_sku_independently(): void
    {
        $a = $this->makeCompanyWithCatalog('empresa-a', 'PHONE-A', 'TOKEN-A', 'Bot A');
        $b = $this->makeCompanyWithCatalog('empresa-b', 'PHONE-B', 'TOKEN-B', 'Bot B');

        // El SKU es único por empresa, no por toda la plataforma.
        $dup = WhatsappPrice::create([
            'menu_item_id' => $b['category']->id,
            'business_profile_id' => $b['profile']->id,
            'category' => $b['category']->title,
            'sku' => $a['product']->sku,
            'name' => 'Mismo SKU que empresa A',
            'price' => 20,
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 3,
        ]);

        $this->assertNotEquals($a['product']->id, $dup->id);
        $this->assertSame($a['product']->sku, $dup->sku);
    }
}
