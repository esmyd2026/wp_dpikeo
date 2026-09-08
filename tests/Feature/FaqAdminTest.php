<?php

namespace Tests\Feature;

use App\Models\BusinessFaq;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Panel de administración de preguntas frecuentes ("¿qué pasa si ya no
 * quiero mi pedido?", devoluciones, contacto de vendedoras, etc.) --
 * mismo patrón que el panel de Sucursales: un listado con formularios
 * embebidos, sin pantallas de crear/editar aparte.
 */
class FaqAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, WhatsappBusinessProfile, User} */
    private function makeCompanyAndUser(): array
    {
        $company = Company::create([
            'uuid' => (string) Str::uuid(), 'name' => 'empresa-test',
            'slug' => 'empresa-test-'.Str::random(6), 'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'empresa-test', 'display_name' => 'empresa-test',
            'phone_number' => '593'.random_int(100000000, 999999999), 'phone_number_id' => 'PHONE-'.Str::random(8),
            'access_token' => 'token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
        $company->users()->attach($user->id);

        return [$company, $profile, $user];
    }

    public function test_admin_can_create_a_faq(): void
    {
        [$company, , $user] = $this->makeCompanyAndUser();

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('admin.faqs.store'), [
                'question' => '¿Qué pasa si ya no quiero mi pedido?',
                'answer' => 'Puedes cancelarlo sin costo mientras no esté confirmado.',
                'is_active' => '1',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('business_faqs', [
            'question' => '¿Qué pasa si ya no quiero mi pedido?',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_and_deactivate_a_faq(): void
    {
        [$company, $profile, $user] = $this->makeCompanyAndUser();
        $faq = BusinessFaq::create([
            'business_profile_id' => $profile->id, 'question' => 'Vieja', 'answer' => 'Vieja respuesta', 'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('admin.faqs.update', $faq), [
                'question' => 'Nueva pregunta',
                'answer' => 'Nueva respuesta',
                'is_active' => '0',
            ]);

        $response->assertRedirect();
        $faq->refresh();
        $this->assertSame('Nueva pregunta', $faq->question);
        $this->assertFalse($faq->is_active);
    }

    public function test_admin_cannot_edit_a_faq_belonging_to_another_company(): void
    {
        [, , $userA] = $this->makeCompanyAndUser();
        [, $profileB] = $this->makeCompanyAndUser();
        $faqB = BusinessFaq::create([
            'business_profile_id' => $profileB->id, 'question' => 'De otra empresa', 'answer' => 'Respuesta', 'is_active' => true,
        ]);

        $companyA = Company::first();
        $response = $this->actingAs($userA)
            ->withSession(['active_company_id' => $companyA->id])
            ->put(route('admin.faqs.update', $faqB), ['question' => 'Hackeado', 'answer' => 'x']);

        $response->assertNotFound();
        $this->assertSame('De otra empresa', $faqB->fresh()->question);
    }

    public function test_admin_can_delete_a_faq(): void
    {
        [$company, $profile, $user] = $this->makeCompanyAndUser();
        $faq = BusinessFaq::create([
            'business_profile_id' => $profile->id, 'question' => 'Q', 'answer' => 'A', 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('admin.faqs.destroy', $faq))
            ->assertRedirect();

        $this->assertDatabaseMissing('business_faqs', ['id' => $faq->id]);
    }
}
