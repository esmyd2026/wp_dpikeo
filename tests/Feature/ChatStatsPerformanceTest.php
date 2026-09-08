<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pedido explícito: "el módulo de WhatsApp... esta muy pesado y eso que no
 * hay muchas conversaciones". Causa real: abrir un chat disparaba una
 * consulta a la base de datos POR CADA mensaje del cliente (para calcular
 * "tiempo de respuesta"), repetido además dentro de un loop de 7 días y
 * multiplicado otra vez a nivel global (calculateGlobalStats corre en cada
 * apertura de chat, no es un dashboard aparte) -- con una tabla de apenas
 * ~1000 mensajes, esto eran fácilmente miles de consultas por click. Ahora
 * todo se calcula en memoria sobre los mensajes ya cargados.
 */
class ChatStatsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Company, User, WhatsappContact} */
    private function makeCompanyAndContact(): array
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
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id, 'phone_number' => '593990000002', 'name' => 'Cliente',
        ]);

        return [$company, $user, $contact];
    }

    private function seedMessages(WhatsappContact $contact, int $businessProfileId): void
    {
        $t = now()->subDays(3)->startOfDay();
        // Cliente escribe, el sistema responde 5 minutos después -- repetido
        // varias veces, como una conversación real de ida y vuelta.
        for ($i = 0; $i < 15; $i++) {
            WhatsappMessage::create([
                'contact_id' => $contact->id, 'business_profile_id' => $businessProfileId,
                'message_id' => 'wamid.client.'.$i, 'sender_type' => 'client', 'receiver_type' => 'system',
                'content' => 'Hola '.$i, 'type' => 'text', 'status' => 'received',
            ])->forceFill(['created_at' => $t->copy()->addMinutes($i * 10)])->saveQuietly();
            WhatsappMessage::create([
                'contact_id' => $contact->id, 'business_profile_id' => $businessProfileId,
                'message_id' => 'wamid.system.'.$i, 'sender_type' => 'system', 'receiver_type' => 'client',
                'content' => 'Respuesta '.$i, 'type' => 'text', 'status' => 'sent',
            ])->forceFill(['created_at' => $t->copy()->addMinutes($i * 10 + 5)])->saveQuietly();
        }
    }

    public function test_opening_a_chat_runs_far_fewer_queries_than_one_per_message(): void
    {
        [$company, $user, $contact] = $this->makeCompanyAndContact();
        $this->seedMessages($contact, $contact->business_profile_id);

        DB::enableQueryLog();
        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('admin.chat', $contact->id));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        // Antes de este fix, 30 mensajes (15 clientes) generaban cientos de
        // consultas extra (una por mensaje, duplicada a nivel global). Con
        // el fix, todo sale de un puñado de consultas fijas sin importar
        // cuántos mensajes haya.
        $this->assertLessThan(70, $queryCount, "Se ejecutaron {$queryCount} consultas al abrir el chat -- debería ser un número fijo y chico, no uno por mensaje.");
    }

    public function test_average_response_time_is_calculated_correctly_from_the_in_memory_pass(): void
    {
        [$company, $user, $contact] = $this->makeCompanyAndContact();
        $this->seedMessages($contact, $contact->business_profile_id);

        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('admin.chat', $contact->id));

        $response->assertOk();
        // Cada respuesta llega 5 minutos después del mensaje del cliente.
        $response->assertJsonPath('stats.avgResponseTime', '5m');
        $response->assertJsonPath('stats.totalMessages', 30);
    }
}
