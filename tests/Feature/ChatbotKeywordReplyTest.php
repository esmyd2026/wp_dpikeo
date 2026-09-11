<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\ChatbotKeywordReply;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "una lista de bocablos que podamos configurar...
 * a medida que lo configuremos coloquemos lo que queramos mostrar cuando el
 * cliente lo escriba... que los bocablos no se repitan y que sean por
 * sucursales o por todos. y por empresas." Ver ChatbotKeywordReply,
 * ChatbotKeywordController y el enganche en
 * WhatsappService::generateChatbotResponse().
 */
class ChatbotKeywordReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function adminFixture(string $slug = 'empresa-kw'): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa KW', 'slug' => $slug, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa KW', 'display_name' => 'Empresa KW',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-KW-'.$slug, 'access_token' => 'token',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $admin];
    }

    public function test_an_admin_can_create_a_keyword_reply_from_the_panel(): void
    {
        [$company, $profile, $admin] = $this->adminFixture();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.chatbot-keywords.store'), [
                'keywords' => 'direcciones, ubicaciones, sucursales, horarios',
                'response_text' => "*Nuestras Ubicaciones*\nCentro: L-D 9am-9pm",
                'is_active' => '1',
                'all_branches' => '1',
            ]);

        $response->assertRedirect();
        $entry = ChatbotKeywordReply::where('business_profile_id', $profile->id)->firstOrFail();
        $this->assertSame(['direcciones', 'ubicaciones', 'sucursales', 'horarios'], $entry->keywords);
        $this->assertTrue($entry->all_branches);
        $this->assertStringContainsString('Nuestras Ubicaciones', $entry->response_text);
    }

    public function test_the_same_keyword_cannot_be_configured_twice_for_the_same_company(): void
    {
        [$company, $profile, $admin] = $this->adminFixture();
        ChatbotKeywordReply::create([
            'business_profile_id' => $profile->id,
            'keywords' => ['horarios'],
            'all_branches' => true,
            'response_text' => 'Ya existe',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.chatbot-keywords.store'), [
                'keywords' => 'Horarios',
                'response_text' => 'Otro texto',
                'all_branches' => '1',
            ]);

        $response->assertSessionHasErrors('keywords');
        $this->assertSame(1, ChatbotKeywordReply::where('business_profile_id', $profile->id)->count());
    }

    public function test_a_keyword_scoped_to_one_branch_does_not_block_the_same_keyword_on_another_company(): void
    {
        [, $profileA] = $this->adminFixture('empresa-kw-a');
        [, $profileB, $adminB] = $this->adminFixture('empresa-kw-b');
        ChatbotKeywordReply::create([
            'business_profile_id' => $profileA->id,
            'keywords' => ['horarios'],
            'all_branches' => true,
            'response_text' => 'Horario de la empresa A',
        ]);
        $companyB = $adminB->companies()->first();

        $response = $this->actingAs($adminB)
            ->withSession(['active_company_id' => $companyB->id])
            ->post(route('admin.chatbot-keywords.store'), [
                'keywords' => 'horarios',
                'response_text' => 'Horario de la empresa B',
                'all_branches' => '1',
            ]);

        $response->assertRedirect();
        $this->assertSame(1, ChatbotKeywordReply::where('business_profile_id', $profileB->id)->where('keywords', json_encode(['horarios']))->count());
    }

    public function test_creating_a_branch_restricted_keyword_without_selecting_a_branch_is_rejected(): void
    {
        [$company, $profile, $admin] = $this->adminFixture();
        BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Centro', 'code' => 'CTR', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.chatbot-keywords.store'), [
                'keywords' => 'promo',
                'response_text' => 'Promo exclusiva',
            ]);

        $response->assertSessionHasErrors('branch_ids');
    }

    public function test_the_bot_replies_with_the_configured_text_when_the_customer_types_the_keyword(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [, $profile] = $this->adminFixture();
        ChatbotKeywordReply::create([
            'business_profile_id' => $profile->id,
            'keywords' => ['direcciones', 'ubicaciones', 'horarios'],
            'all_branches' => true,
            'response_text' => '*Nuestras Ubicaciones* Centro y Norte, L-D 9am-9pm.',
            'is_active' => true,
        ]);
        WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000099', 'name' => 'Cliente', 'last_inbound_at' => now()]);

        app(WhatsappService::class)->useBusinessProfile($profile);
        $reply = $this->invokeGenerateChatbotResponse('¿cuáles son sus horarios?', '593990000099');

        $this->assertSame('text', $reply['type']);
        $this->assertStringContainsString('Nuestras Ubicaciones', $reply['text']['body']);
    }

    public function test_a_branch_only_keyword_does_not_reply_to_a_customer_of_another_branch(): void
    {
        [, $profile] = $this->adminFixture();
        $branchCentro = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Centro', 'code' => 'CTR', 'is_active' => true]);
        $branchNorte = BusinessBranch::create(['business_profile_id' => $profile->id, 'name' => 'Norte', 'code' => 'NRT', 'is_active' => true]);
        $entry = ChatbotKeywordReply::create([
            'business_profile_id' => $profile->id,
            'keywords' => ['promo'],
            'all_branches' => false,
            'response_text' => 'Promo exclusiva de Centro',
            'is_active' => true,
        ]);
        $entry->branches()->attach($branchCentro->id);

        $contactCentro = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000098', 'name' => 'Cliente Centro']);
        $contactCentro->rememberBranch($branchCentro->id);
        $contactNorte = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990000097', 'name' => 'Cliente Norte']);
        $contactNorte->rememberBranch($branchNorte->id);

        $this->assertNotNull(ChatbotKeywordReply::findMatch($profile->id, 'promo', $branchCentro->id));
        $this->assertNull(ChatbotKeywordReply::findMatch($profile->id, 'promo', $branchNorte->id));
    }

    /** Invoca el método privado generateChatbotResponse() vía reflexión, como el resto de la suite. */
    private function invokeGenerateChatbotResponse(string $message, string $from): array
    {
        $service = app(WhatsappService::class);
        $method = new \ReflectionMethod($service, 'generateChatbotResponse');
        $method->setAccessible(true);

        return $method->invoke($service, $message, $from);
    }
}
