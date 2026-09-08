<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderSegmentationAndNotificationControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{Company, WhatsappBusinessProfile, WhatsappChatbotConfig, User} */
    private function fixture(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Pedidos',
            'slug' => 'empresa-pedidos',
            'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => $company->name,
            'display_name' => $company->name,
            'phone_number' => '593990001111',
            'phone_number_id' => 'ORDERS-PHONE',
            'access_token' => 'token-orders',
            'status' => 'connected',
        ]);
        $config = WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => [],
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $profile, $config, $user];
    }

    public function test_orders_are_segmented_by_status_before_pagination(): void
    {
        [$company, $profile, , $user] = $this->fixture();
        $kitchenCustomer = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593990001112',
            'name' => 'Cliente Cocina',
        ]);
        $newCustomer = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593990001113',
            'name' => 'Cliente Nuevo',
        ]);
        WhatsappCart::create(['contact_id' => $kitchenCustomer->id, 'status' => 'preparing', 'total' => 12]);
        WhatsappCart::create(['contact_id' => $newCustomer->id, 'status' => 'pending', 'total' => 8]);

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders', ['segment' => 'preparing']))
            ->assertOk()
            ->assertSee('Cliente Cocina')
            ->assertDontSee('Cliente Nuevo')
            ->assertSee('En cocina');
    }

    public function test_each_status_notification_can_be_disabled_for_one_company(): void
    {
        [$company, , $config, $user] = $this->fixture();
        $template = MessageTemplate::where('key', 'order_status_changed')->firstOrFail();
        $statusNotifications = array_fill_keys(
            ['pending', 'confirmed', 'payment_pending', 'paid', 'preparing', 'ready', 'completed', 'cancelled'],
            1
        );
        $statusNotifications['preparing'] = 0;

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.chatbot.message-templates.update', $template), [
                'body' => $template->body,
                'is_enabled' => 1,
                'status_notifications' => $statusNotifications,
            ])
            ->assertRedirect();

        $freshConfig = $config->fresh();
        $this->assertTrue(MessageTemplate::isEnabledFor($freshConfig, 'order_status_changed'));
        $this->assertFalse(MessageTemplate::isStatusEnabledFor($freshConfig, 'preparing'));
        $this->assertTrue(MessageTemplate::isStatusEnabledFor($freshConfig, 'ready'));
    }

    public function test_an_automatic_message_template_can_be_silenced_without_deleting_its_text(): void
    {
        [$company, , $config, $user] = $this->fixture();
        $template = MessageTemplate::where('key', 'order_on_the_way')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.chatbot.message-templates.update', $template), [
                'body' => $template->body,
                'is_enabled' => 0,
            ])
            ->assertRedirect();

        $this->assertFalse(MessageTemplate::isEnabledFor($config->fresh(), 'order_on_the_way'));
        $this->assertSame($template->body, $template->fresh()->body);
    }

    public function test_template_variables_are_displayed_as_readable_tags_not_php_code(): void
    {
        [$company, , , $user] = $this->fixture();

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chatbot.config'))
            ->assertOk()
            ->assertSee('{{order_number}}', false)
            ->assertDontSee('<?php echo e(order_number); ?>', false);
    }
}
