<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeliveryDriver;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: "colócame aquí la opción de poder avisar también al
 * cliente que su pedido va en camino. Pero si ya el repartidor lo hizo que
 * me salga [...], pero en este caso que le permita a la operaria enviar
 * por si el delivery no lo hizo." -- en el modal "Cambiar etapa del
 * pedido" de Pedidos, como respaldo manual del aviso que normalmente
 * manda el propio repartidor desde su enlace.
 */
class OrderNotifyOnTheWayBackupButtonTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, User, WhatsappContact} */
    private function fixture(): array
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Aviso', 'slug' => 'empresa-aviso-'.Str::random(6), 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Aviso', 'display_name' => 'Empresa Aviso',
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'test', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593990005001', 'name' => 'Cliente']);

        return [$company, $profile, $admin, $contact];
    }

    public function test_the_stage_button_carries_the_dispatched_driver_id_for_the_backup_action(): void
    {
        [$company, $profile, $admin, $contact] = $this->fixture();
        $driver = DeliveryDriver::create(['business_profile_id' => $profile->id, 'first_name' => 'Pedro', 'phone_number' => '593991111111', 'is_active' => true]);
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 15,
            'metadata' => ['pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driver->id, 'order_details' => ['order_number' => 'ORD-950']],
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertSee("data-last-dispatch-driver-id=\"{$driver->id}\"", false);
        $response->assertSee('id="statusNotifyOnTheWayBtn"', false);
    }

    public function test_the_stage_button_carries_the_already_notified_timestamp_when_present(): void
    {
        [$company, $profile, $admin, $contact] = $this->fixture();
        $driver = DeliveryDriver::create(['business_profile_id' => $profile->id, 'first_name' => 'Pedro', 'phone_number' => '593991111112', 'is_active' => true]);
        $notifiedAt = now()->toIso8601String();
        $order = WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 15,
            'metadata' => [
                'pickup_mode' => 'delivery', 'last_dispatch_driver_id' => $driver->id,
                'on_the_way_notified_at' => $notifiedAt, 'order_details' => ['order_number' => 'ORD-951'],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertSee("data-on-the-way-notified-at=\"{$notifiedAt}\"", false);
    }

    public function test_no_driver_id_attribute_is_empty_when_nothing_was_dispatched_yet(): void
    {
        [$company, , $admin, $contact] = $this->fixture();
        WhatsappCart::create([
            'contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_READY, 'total' => 15,
            'metadata' => ['pickup_mode' => 'delivery', 'order_details' => ['order_number' => 'ORD-952']],
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.orders'));

        $response->assertOk();
        $response->assertSee('data-last-dispatch-driver-id=""', false);
    }
}
