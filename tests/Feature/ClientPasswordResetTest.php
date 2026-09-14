<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: un cliente no podía iniciar sesión en "Mi
 * cuenta" del micrositio porque nunca se había puesto contraseña (o la
 * olvidó) y no podía completar el flujo de recuperación por WhatsApp. Se
 * agregó un botón en el panel de administración para restablecerla
 * directamente desde la ficha del cliente.
 */
class ClientPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $suffix): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Reset', 'slug' => 'empresa-reset-'.$suffix, 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593992'.$suffix, 'phone_number_id' => 'PHONE-RESET-'.$suffix,
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);
        $client = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593993'.$suffix,
            'name' => 'Cliente Reset',
        ]);

        return compact('company', 'profile', 'admin', 'client');
    }

    public function test_admin_can_reset_a_clients_password_and_it_is_sent_by_whatsapp(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        ['admin' => $admin, 'client' => $client] = $this->fixture('200001');

        $response = $this->actingAs($admin)->post(route('admin.clients.reset-password', $client));

        $response->assertRedirect(route('admin.clients.show', $client));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('enviada por WhatsApp', session('success'));

        $client->refresh();
        $this->assertTrue($client->hasAccountPassword());

        // Extrae la contraseña del mensaje flash y confirma que sirve para loguearse.
        preg_match('/Nueva contraseña: (\S+)/', session('success'), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $attempted = Auth::guard('storefront_customer')->attempt([
            'business_profile_id' => $client->business_profile_id,
            'phone_number' => $client->phone_number,
            'password' => $matches[1],
        ]);
        $this->assertTrue($attempted);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'restablecida'));
    }

    public function test_reset_still_works_when_the_whatsapp_send_fails(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);
        ['admin' => $admin, 'client' => $client] = $this->fixture('200002');

        $response = $this->actingAs($admin)->post(route('admin.clients.reset-password', $client));

        $response->assertRedirect();
        $this->assertStringContainsString('no se pudo enviar por WhatsApp', session('success'));
        $client->refresh();
        $this->assertTrue($client->hasAccountPassword());
    }

    public function test_a_staff_user_without_the_permission_cannot_reset_a_password(): void
    {
        ['company' => $company, 'client' => $client] = $this->fixture('200003');
        // Personal con acceso al panel (is_admin=true los deja entrar) pero con
        // un rol que no incluye el permiso clients.update.
        $limitedRole = Role::create(['name' => 'Limitado', 'slug' => 'limitado-'.uniqid()]);
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $limitedRole->id]);
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)->post(route('admin.clients.reset-password', $client));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('error');
        $client->refresh();
        $this->assertFalse($client->hasAccountPassword());
    }
}
