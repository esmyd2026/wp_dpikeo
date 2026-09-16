<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: "dividiendo en pestañas los clientes que están siendo
 * gestionados por el bot, los que tienen el bot pausado, los que están en
 * un pedido en curso" -- en el listado de Clientes.
 */
class ClientQuickFilterTabsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function companyWithAdmin(): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Tabs', 'slug' => 'empresa-tabs-'.Str::random(6), 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Tabs', 'display_name' => 'Empresa Tabs',
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        return [$company, $profile, $admin];
    }

    private function contactWithMessage(WhatsappBusinessProfile $profile, string $phone, string $name, bool $botEnabled = true): WhatsappContact
    {
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => $phone, 'name' => $name, 'bot_enabled' => $botEnabled,
        ]);
        WhatsappMessage::create([
            'business_profile_id' => $profile->id, 'contact_id' => $contact->id,
            'sender_type' => 'client', 'receiver_type' => 'system', 'content' => 'hola', 'type' => 'text',
            'message_id' => 'wamid.'.Str::random(10), 'status' => 'received',
        ]);

        return $contact;
    }

    public function test_bot_on_tab_only_shows_contacts_with_the_bot_enabled(): void
    {
        [$company, $profile, $admin] = $this->companyWithAdmin();
        $this->contactWithMessage($profile, '593990002001', 'Cliente Bot Activo', true);
        $this->contactWithMessage($profile, '593990002002', 'Cliente Bot Pausado', false);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.clients.index', ['segment' => 'bot_on']));

        $response->assertOk();
        $response->assertSee('Cliente Bot Activo');
        $response->assertDontSee('Cliente Bot Pausado');
    }

    public function test_bot_off_tab_only_shows_paused_contacts(): void
    {
        [$company, $profile, $admin] = $this->companyWithAdmin();
        $this->contactWithMessage($profile, '593990002003', 'Activo Dos', true);
        $this->contactWithMessage($profile, '593990002004', 'Pausado Dos', false);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.clients.index', ['segment' => 'bot_off']));

        $response->assertOk();
        $response->assertSee('Pausado Dos');
        $response->assertDontSee('Activo Dos');
    }

    public function test_order_in_progress_tab_excludes_completed_and_cancelled_orders(): void
    {
        [$company, $profile, $admin] = $this->companyWithAdmin();
        $inProgress = $this->contactWithMessage($profile, '593990002005', 'Con Pedido En Curso');
        WhatsappCart::create(['contact_id' => $inProgress->id, 'status' => WhatsappCart::STATUS_CONFIRMED, 'total' => 20]);

        $onlyCompleted = $this->contactWithMessage($profile, '593990002006', 'Solo Pedidos Cerrados');
        WhatsappCart::create(['contact_id' => $onlyCompleted->id, 'status' => WhatsappCart::STATUS_COMPLETED, 'total' => 20]);

        $noOrders = $this->contactWithMessage($profile, '593990002007', 'Sin Pedidos');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.clients.index', ['segment' => 'order_in_progress']));

        $response->assertOk();
        $response->assertSee('Con Pedido En Curso');
        $response->assertDontSee('Solo Pedidos Cerrados');
        $response->assertDontSee('Sin Pedidos');
    }

    public function test_the_quick_tabs_render_with_counts_and_mark_the_active_one(): void
    {
        [$company, $profile, $admin] = $this->companyWithAdmin();
        $this->contactWithMessage($profile, '593990002008', 'Cliente Uno', true);
        $this->contactWithMessage($profile, '593990002009', 'Cliente Dos', false);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.clients.index', ['segment' => 'bot_off']));

        $response->assertOk();
        $response->assertSee('Bot activo');
        $response->assertSee('Bot pausado');
        $response->assertSee('Pedido en curso');
        $response->assertSee('clients-tab is-active', false);
    }
}
