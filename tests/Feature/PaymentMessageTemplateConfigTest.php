<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Services\PermissionService;
use App\Support\PaymentMessageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentMessageTemplateConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
    }

    /** @return array{0: Company, 1: WhatsappChatbotConfig, 2: User} */
    private function companyFixture(string $name): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => $name,
            'display_name' => $name,
            'phone_number' => '593'.random_int(100000000, 999999999),
            'phone_number_id' => Str::upper(Str::slug($name)).'-PHONE',
            'access_token' => 'token-'.Str::slug($name),
            'status' => 'connected',
        ]);
        $config = WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => [],
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $config, $user];
    }

    public function test_payment_templates_are_saved_only_for_the_active_company_and_render_variables(): void
    {
        [$companyA, $configA, $userA] = $this->companyFixture('Empresa A');
        [, $configB] = $this->companyFixture('Empresa B');

        $this->actingAs($userA)
            ->withSession(['active_company_id' => $companyA->id])
            ->put(route('admin.chatbot.config.update'), [
                'payment_templates' => [
                    'proof_request' => 'Pedido {{order_number}}: paga {{currency}} {{total}} y adjunta tu comprobante.',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            'Pedido ORD-123: paga USD 18.50 y adjunta tu comprobante.',
            PaymentMessageTemplates::render($configA->fresh(), 'proof_request', [
                'order_number' => 'ORD-123',
                'currency' => 'USD',
                'total' => '18.50',
            ])
        );
        $this->assertArrayNotHasKey('payment_templates', $configB->fresh()->metadata ?? []);
    }

    public function test_unknown_payment_template_variables_are_rejected(): void
    {
        [$company, , $user] = $this->companyFixture('Empresa Variables');

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->from(route('admin.chatbot.config'))
            ->put(route('admin.chatbot.config.update'), [
                'payment_templates' => [
                    'proof_request' => 'Pedido {{variable_inventada}}',
                ],
            ])
            ->assertRedirect(route('admin.chatbot.config'))
            ->assertSessionHasErrors('payment_templates.proof_request');
    }
}
