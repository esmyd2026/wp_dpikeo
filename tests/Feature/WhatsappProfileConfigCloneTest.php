<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchDeliveryFeeTier;
use App\Models\BusinessBranchHour;
use App\Models\BusinessFaq;
use App\Models\ChatbotKeywordReply;
use App\Models\Company;
use App\Models\DeliveryDriver;
use App\Models\Franchise;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowEdge;
use App\Models\MarketingFlowNode;
use App\Models\MarketingFlowVersion;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappAction;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappButton;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactNote;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPrice;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: al conectar un número nuevo a la misma empresa,
 * el usuario quiere copiarle toda la configuración de un número ya armado
 * (categorías, productos/inventario, sucursales, franquicias, palabras
 * clave, config del bot + flujo visual, FAQs, botones, repartidores y
 * clientes) SIN tener que rearmar todo desde cero -- pero sin copiar nunca
 * conversaciones/mensajes ni pedidos, que quedan ligados al número real
 * donde ocurrieron.
 */
class WhatsappProfileConfigCloneTest extends TestCase
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

    /** @return array{company: Company, source: WhatsappBusinessProfile, target: WhatsappBusinessProfile, user: User} */
    private function fixture(string $slug): array
    {
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $source = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Origen', 'display_name' => 'Origen',
            'phone_number' => '593990'.random_int(100000, 999999), 'phone_number_id' => 'SRC-'.Str::random(6),
            'access_token' => 'token-src', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $target = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Destino', 'display_name' => 'Destino',
            'phone_number' => '593991'.random_int(100000, 999999), 'phone_number_id' => 'DST-'.Str::random(6),
            'access_token' => 'token-dst', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        // Simula lo que ya hace CompanyWhatsappController al conectar un número.
        BusinessBranch::ensureDefaultForProfile($target);

        $user = $this->adminUser();
        $company->users()->attach($user->id);

        return compact('company', 'source', 'target', 'user');
    }

    private function seedFullConfig(WhatsappBusinessProfile $source): array
    {
        WhatsappChatbotConfig::create([
            'business_profile_id' => $source->id,
            'welcome_message' => 'Hola, bienvenido',
            'chatgpt_enabled' => true,
            'metadata' => ['card_payment_url' => 'https://dpikeos.ec/pagar'],
        ]);

        $franchise = Franchise::create(['business_profile_id' => $source->id, 'name' => 'Club Dpikeolovers', 'slug' => 'club', 'is_default' => true, 'is_active' => true]);
        $menu = WhatsappMenu::create(['business_profile_id' => $source->id, 'title' => 'Menú', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu']);
        $category = WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $source->id, 'franchise_id' => $franchise->id, 'title' => 'Boxes', 'action_id' => 'boxes', 'is_active' => true]);
        $subcategory = WhatsappMenuItem::create(['menu_id' => $menu->id, 'business_profile_id' => $source->id, 'parent_id' => $category->id, 'title' => 'Boxes premium', 'action_id' => 'boxes-premium', 'is_active' => true]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $subcategory->id, 'business_profile_id' => $source->id, 'franchise_id' => $franchise->id,
            'category' => 'Boxes', 'sku' => 'BOX-1', 'name' => 'Box Tender', 'price' => 4.5, 'currency' => 'USD',
            'is_active' => true, 'stock' => 12,
        ]);

        $branch = BusinessBranch::create(['business_profile_id' => $source->id, 'name' => 'Urdesa', 'code' => 'URD', 'is_active' => true, 'orders_enabled' => true]);
        BusinessBranchHour::create(['business_branch_id' => $branch->id, 'day_of_week' => 1, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00']);
        BusinessBranchDeliveryFeeTier::create(['business_branch_id' => $branch->id, 'from_km' => 0, 'to_km' => 5, 'price' => 1.5]);

        $keywordReply = ChatbotKeywordReply::create(['business_profile_id' => $source->id, 'keywords' => ['horario'], 'all_branches' => false, 'response_text' => 'Abrimos a las 10am', 'is_active' => true]);
        DB::table('keyword_reply_branch')->insert(['business_branch_id' => $branch->id, 'chatbot_keyword_reply_id' => $keywordReply->id]);

        BusinessFaq::create(['business_profile_id' => $source->id, 'question' => '¿Hacen delivery?', 'answer' => 'Sí', 'is_active' => true]);
        $action = WhatsappAction::create(['code' => 'ver_menu_'.Str::random(6), 'name' => 'Ver menú', 'type' => 'reply', 'is_active' => true]);
        WhatsappButton::create(['business_profile_id' => $source->id, 'action_id' => $action->id, 'title' => 'Ver menú', 'type' => 'reply', 'is_active' => true, 'order' => 1]);
        DeliveryDriver::create(['business_profile_id' => $source->id, 'first_name' => 'Juan', 'last_name' => 'Pérez', 'phone_number' => '593987654321', 'is_active' => true]);

        $flow = MarketingFlow::create(['business_profile_id' => $source->id, 'name' => 'Flujo principal', 'is_active' => true]);
        $startNode = MarketingFlowNode::create(['flow_id' => $flow->id, 'node_uuid' => 'start-uuid', 'node_type' => 'start', 'name' => 'Inicio', 'is_enabled' => true, 'is_start' => true]);
        $msgNode = MarketingFlowNode::create(['flow_id' => $flow->id, 'node_uuid' => 'msg-uuid', 'node_type' => 'message', 'name' => 'Saludo', 'message_template' => 'Hola', 'is_enabled' => true, 'is_start' => false]);
        MarketingFlowEdge::create(['flow_id' => $flow->id, 'source_node_uuid' => 'start-uuid', 'source_handle' => 'default', 'target_node_uuid' => 'msg-uuid']);
        MarketingFlowVersion::create(['flow_id' => $flow->id, 'version_number' => 1, 'snapshot' => ['start_node_uuid' => 'start-uuid', 'nodes' => [], 'edges' => []], 'is_current' => true, 'published_at' => now()]);

        $contact = WhatsappContact::create(['business_profile_id' => $source->id, 'phone_number' => '593999888777', 'name' => 'Ana Cliente']);
        WhatsappContactNote::create(['contact_id' => $contact->id, 'user_id' => $this->adminUser()->id, 'body' => 'Cliente frecuente']);

        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 20]);
        WhatsappMessage::create(['contact_id' => $contact->id, 'business_profile_id' => $source->id, 'message_id' => 'wamid.'.Str::random(10), 'sender_type' => 'client', 'content' => 'Hola', 'type' => 'text', 'status' => 'received']);

        return compact('franchise', 'menu', 'category', 'subcategory', 'product', 'branch', 'keywordReply', 'flow', 'contact', 'cart');
    }

    public function test_cloning_copies_every_config_table_with_remapped_ids_but_never_messages_or_orders(): void
    {
        ['company' => $company, 'source' => $source, 'target' => $target, 'user' => $user] = $this->fixture('empresa-clone-happy');
        $this->seedFullConfig($source);

        $response = $this->actingAs($user)->post(
            route('admin.empresas.whatsapp.profile.clone-config', [$company, $target]),
            ['source_profile_id' => $source->id]
        );

        $response->assertRedirect(route('admin.empresas.whatsapp', $company));
        $response->assertSessionHas('success');

        // Config básica + flujo visual.
        $config = WhatsappChatbotConfig::where('business_profile_id', $target->id)->firstOrFail();
        $this->assertSame('Hola, bienvenido', $config->welcome_message);
        $this->assertTrue($config->chatgpt_enabled);

        $franchise = Franchise::where('business_profile_id', $target->id)->firstOrFail();
        $this->assertSame('Club Dpikeolovers', $franchise->name);

        $menu = WhatsappMenu::where('business_profile_id', $target->id)->firstOrFail();
        $category = WhatsappMenuItem::where('business_profile_id', $target->id)->whereNull('parent_id')->firstOrFail();
        $subcategory = WhatsappMenuItem::where('business_profile_id', $target->id)->whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($menu->id, $category->menu_id);
        $this->assertSame($category->id, $subcategory->parent_id, 'El parent_id debe apuntar al id NUEVO de la categoría, no al viejo.');
        $this->assertSame($franchise->id, $category->franchise_id);

        $product = WhatsappPrice::where('business_profile_id', $target->id)->firstOrFail();
        $this->assertSame('BOX-1', $product->sku);
        $this->assertSame(12, $product->stock);
        $this->assertSame($subcategory->id, $product->menu_item_id, 'El producto debe apuntar al menu_item_id NUEVO.');
        $this->assertSame($franchise->id, $product->franchise_id);

        // La MATRIZ autogenerada fue reemplazada, no duplicada.
        $branches = BusinessBranch::where('business_profile_id', $target->id)->get();
        $this->assertCount(1, $branches);
        $branch = $branches->first();
        $this->assertSame('URD', $branch->code);
        $this->assertCount(1, BusinessBranchHour::where('business_branch_id', $branch->id)->get());
        $this->assertCount(1, BusinessBranchDeliveryFeeTier::where('business_branch_id', $branch->id)->get());

        $keywordReply = ChatbotKeywordReply::where('business_profile_id', $target->id)->firstOrFail();
        $this->assertTrue(
            DB::table('keyword_reply_branch')->where('business_branch_id', $branch->id)->where('chatbot_keyword_reply_id', $keywordReply->id)->exists(),
            'El pivote debe apuntar a los ids NUEVOS de sucursal y palabra clave.'
        );

        $this->assertSame(1, BusinessFaq::where('business_profile_id', $target->id)->count());
        $this->assertSame(1, WhatsappButton::where('business_profile_id', $target->id)->count());
        $this->assertSame(1, DeliveryDriver::where('business_profile_id', $target->id)->count());

        $flow = MarketingFlow::where('business_profile_id', $target->id)->firstOrFail();
        $this->assertSame(2, MarketingFlowNode::where('flow_id', $flow->id)->count());
        $this->assertSame(1, MarketingFlowEdge::where('flow_id', $flow->id)->count());
        $version = MarketingFlowVersion::where('flow_id', $flow->id)->firstOrFail();
        $this->assertSame(1, $version->version_number);
        $this->assertTrue($version->is_current);

        $contact = WhatsappContact::where('business_profile_id', $target->id)->firstOrFail();
        $this->assertSame('593999888777', $contact->phone_number);
        $this->assertSame('Ana Cliente', $contact->name);
        $this->assertNull($contact->last_inbound_message_id);
        $this->assertSame(1, WhatsappContactNote::where('contact_id', $contact->id)->count());

        // Nunca se copian conversaciones ni pedidos.
        $this->assertSame(0, WhatsappCart::where('branch_id', $branch->id)->count());
        $this->assertSame(0, WhatsappMessage::where('business_profile_id', $target->id)->count());
    }

    public function test_cloning_is_rejected_when_the_target_already_has_its_own_configuration(): void
    {
        ['company' => $company, 'source' => $source, 'target' => $target, 'user' => $user] = $this->fixture('empresa-clone-dirty');
        $this->seedFullConfig($source);
        WhatsappMenu::create(['business_profile_id' => $target->id, 'title' => 'Menú propio', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu']);

        $response = $this->actingAs($user)->post(
            route('admin.empresas.whatsapp.profile.clone-config', [$company, $target]),
            ['source_profile_id' => $source->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, WhatsappMenu::where('business_profile_id', $target->id)->count(), 'No debió agregarse el menú del origen.');
    }

    public function test_a_source_profile_from_another_company_is_rejected(): void
    {
        ['company' => $companyA, 'target' => $target, 'user' => $user] = $this->fixture('empresa-clone-a');
        ['source' => $foreignSource] = $this->fixture('empresa-clone-b');
        WhatsappMenu::create(['business_profile_id' => $foreignSource->id, 'title' => 'Menú ajeno', 'type' => 'list', 'content' => 'x', 'action_id' => 'prices_menu']);

        $response = $this->actingAs($user)->post(
            route('admin.empresas.whatsapp.profile.clone-config', [$companyA, $target]),
            ['source_profile_id' => $foreignSource->id]
        );

        $response->assertRedirect();
        $this->assertSame(0, WhatsappMenu::where('business_profile_id', $target->id)->count());
    }

    public function test_a_contact_that_already_exists_under_the_target_is_left_untouched(): void
    {
        ['company' => $company, 'source' => $source, 'target' => $target, 'user' => $user] = $this->fixture('empresa-clone-contact');
        $this->seedFullConfig($source);
        $existing = WhatsappContact::create(['business_profile_id' => $target->id, 'phone_number' => '593999888777', 'name' => 'Ya me escribió antes']);
        WhatsappContactNote::create(['contact_id' => $existing->id, 'user_id' => $user->id, 'body' => 'Nota propia del destino']);

        $this->actingAs($user)->post(
            route('admin.empresas.whatsapp.profile.clone-config', [$company, $target]),
            ['source_profile_id' => $source->id]
        );

        $this->assertSame(1, WhatsappContact::where('business_profile_id', $target->id)->where('phone_number', '593999888777')->count());
        $this->assertSame('Ya me escribió antes', $existing->fresh()->name, 'No se debe pisar el contacto que ya existía en el destino.');
        $this->assertSame(1, WhatsappContactNote::where('contact_id', $existing->id)->count(), 'No se le deben agregar notas del origen.');
    }
}
