<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\PermissionService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "ayúdame que este monitoreo solo sea cuando un
 * cliente nuevo escriba por primera vez, cuando llegue un pedido, cuando un
 * cliente pague, cuando solicite hablar con un asesor... coloca ahí las
 * opciones para yo seleccionar qué mensaje quiero que me lleguen. Quiero
 * evitar que me lleguen cada vez que escribe alguien." Antes
 * `monitoring_enabled` mandaba WhatsApp/email en TODO mensaje entrante.
 */
class MonitoringEventNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    /** @return array{WhatsappBusinessProfile, WhatsappChatbotConfig} */
    private function fixture(?array $monitoringEvents = null): array
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-TEST', 'whatsapp_business_id' => 'WABA-TEST', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $metadata = $monitoringEvents === null ? [] : ['monitoring_events' => $monitoringEvents];
        $config = WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'monitoring_enabled' => true,
            'monitoring_phone_number' => '593999999999',
            'metadata' => $metadata,
        ]);

        return [$profile, $config];
    }

    public function test_default_monitoring_events_are_all_four_when_never_configured(): void
    {
        [, $config] = $this->fixture(null);

        $this->assertSame(['new_contact', 'new_order', 'payment_confirmed', 'agent_request'], $config->monitoring_events);
    }

    public function test_saving_with_nothing_checked_means_no_events_not_all_of_them(): void
    {
        [, $config] = $this->fixture([]);

        $this->assertSame([], $config->monitoring_events);
    }

    public function test_a_new_contacts_first_text_message_notifies_but_the_second_one_does_not(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        $this->fixture(['new_contact']);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'processIncomingMessage', [[
            'from' => '593988887777', 'id' => 'wamid.'.uniqid(), 'type' => 'text', 'text' => ['body' => 'hola'],
        ]]);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Cliente nuevo'));

        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        $this->invoke($service, 'processIncomingMessage', [[
            'from' => '593988887777', 'id' => 'wamid.'.uniqid(), 'type' => 'text', 'text' => ['body' => 'otra vez'],
        ]]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Cliente nuevo'));
    }

    public function test_monitoring_disabled_sends_nothing_even_for_a_selected_event(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile] = $this->fixture(['new_contact']);
        WhatsappChatbotConfig::where('business_profile_id', $profile->id)->update(['monitoring_enabled' => false]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'processIncomingMessage', [[
            'from' => '593988887777', 'id' => 'wamid.'.uniqid(), 'type' => 'text', 'text' => ['body' => 'hola'],
        ]]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Cliente nuevo'));
    }

    public function test_an_unselected_event_does_not_notify_while_a_selected_one_still_does(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile] = $this->fixture(['agent_request']); // new_contact NO seleccionado

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593977776666', 'name' => 'Cliente']);

        $this->invoke($service, 'triggerAgentHandoff', [$contact, $contact->phone_number, 'test']);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Solicita asesor'));

        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        $this->invoke($service, 'processIncomingMessage', [[
            'from' => '593966665555', 'id' => 'wamid.'.uniqid(), 'type' => 'text', 'text' => ['body' => 'hola'],
        ]]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Cliente nuevo'));
    }

    public function test_payment_proof_notifies_once_via_alert_staff_of_payment_proof(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile] = $this->fixture(['payment_confirmed']);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593955554444', 'name' => 'Compradora']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => WhatsappCart::STATUS_PAYMENT_PENDING, 'total' => 24.5]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'alertStaffOfPaymentProof', [$cart, $contact]);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Pago confirmado')
            && str_contains(json_encode($request->data()), $cart->getOrderNumber()));
    }

    public function test_a_confirmed_order_notifies_once_and_resyncing_it_does_not_repeat(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile] = $this->fixture(['new_order']);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593944443333', 'name' => 'Cliente Web']);
        $cart = WhatsappCart::create(['contact_id' => $contact->id, 'status' => 'active', 'total' => 15]);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'finalizeBulkWebOrder', [$cart]);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Pedido nuevo'));

        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        $this->invoke($service, 'syncOrderDetails', [$cart->fresh()]);

        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Pedido nuevo'));
    }

    public function test_monitoring_email_is_sent_for_a_selected_event(): void
    {
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200)]);
        [$profile] = $this->fixture(['agent_request']);
        WhatsappChatbotConfig::where('business_profile_id', $profile->id)->update(['monitoring_email' => 'ops@example.test']);
        $contact = WhatsappContact::create(['business_profile_id' => $profile->id, 'phone_number' => '593933332222', 'name' => 'Cliente']);

        $service = app(WhatsappService::class);
        $service->setWebhookPhoneNumberId('PHONE-TEST');
        $this->invoke($service, 'triggerAgentHandoff', [$contact, $contact->phone_number, 'test']);

        Mail::assertSent(\App\Mail\MonitoringNotification::class, fn ($mail) => $mail->messageType === 'Solicita asesor');
    }

    public function test_admin_can_pick_which_events_trigger_monitoring_from_the_panel(): void
    {
        app(PermissionService::class)->syncDefinitions();
        app(PermissionService::class)->syncDefaultRoles();
        $company = Company::create(['uuid' => (string) Str::uuid(), 'name' => 'Empresa Monitoreo', 'slug' => 'empresa-monitoreo', 'status' => 'active']);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'Empresa Monitoreo', 'display_name' => 'Empresa Monitoreo',
            'phone_number' => '593990000009', 'phone_number_id' => 'PHONE-MON', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($admin->id);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.chatbot.config.update'), [
                'monitoring_enabled' => '1',
                'monitoring_phone_number' => '593999999999',
                'monitoring_events' => ['new_order', 'agent_request'],
            ])
            ->assertSessionDoesntHaveErrors();

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile->id)->firstOrFail();
        $this->assertSame(['new_order', 'agent_request'], $config->monitoring_events);
        $this->assertFalse(in_array('new_contact', $config->monitoring_events, true));
    }
}
