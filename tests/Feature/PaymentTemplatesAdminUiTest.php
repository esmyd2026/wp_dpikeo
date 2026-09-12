<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "ayúdame que sea más fácil desde la parte admin
 * para yo modificar la plantilla. Y abajo colócame de cada variable el
 * botón de ? info y me dices que es cada variable. Y dónde la encuentro si
 * es de otra que se construye." También: avisar cuando una plantilla ya
 * tiene texto propio guardado, porque eso es justo lo que hacía que un
 * cambio del texto de fábrica pasara desapercibido para el cliente.
 */
class PaymentTemplatesAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_panel_shows_an_info_button_and_a_warning_when_a_template_has_a_custom_override(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Plantillas', 'slug' => 'empresa-plantillas', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Plantillas', 'display_name' => 'Empresa Plantillas',
            'phone_number' => '593990000001', 'phone_number_id' => 'PHONE-TPL', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['payment_templates' => ['order_confirmed' => 'Texto viejo personalizado que ya no cambia solo.']],
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chatbot.config'));

        $response->assertOk();
        // Botón "?" reutilizable (mismo mecanismo de inserción de variables).
        $response->assertSee('data-template-info-for', false);
        $response->assertSee('data-template-info-text', false);
        // Explica de dónde sale una variable armada por otra plantilla/campo.
        $response->assertSee('Datos para transferencias o depósitos');
        // Aviso de que order_confirmed tiene texto propio guardado.
        $response->assertSee('Este mensaje tiene un texto propio guardado');
    }

    public function test_message_templates_section_also_gets_clickable_variables_with_info(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Plantillas 2', 'slug' => 'empresa-plantillas-2', 'status' => 'active']);
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Plantillas 2', 'display_name' => 'Empresa Plantillas 2',
            'phone_number' => '593990000002', 'phone_number_id' => 'PHONE-TPL2', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chatbot.config'));

        $response->assertOk();
        // El ticket de confirmación (order_confirmation_ticket) ahora tiene
        // botones para insertar {{shipping_line}}/{{fulfillment}} con su "?".
        $response->assertSee('data-template-variable="&#123;&#123;shipping_line&#125;&#125;"', false);
        $response->assertSee('Se arma sola con el pedido', false);
    }
}
