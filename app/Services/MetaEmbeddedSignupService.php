<?php

namespace App\Services;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orquesta el alta de un número de WhatsApp para una empresa mediante Meta
 * Embedded Signup: valida lo que llega del frontend, intercambia el código
 * por un token, trae los datos reales del número/WABA, se suscribe al
 * webhook y guarda todo scoped a esa empresa. No toca WhatsappService ni la
 * lógica de envío/recepción de mensajes -- es exclusivamente el handshake
 * inicial de conexión.
 */
class MetaEmbeddedSignupService
{
    public function __construct(private readonly MetaGraphService $graph)
    {
    }

    public function connect(Company $company, string $code, string $wabaId, string $phoneNumberId): WhatsappBusinessProfile
    {
        // phone_number_id es único a nivel de toda la plataforma (índice
        // único en la tabla). Sin este chequeo, si alguien completa el
        // Embedded Signup con un número que Meta ya asoció a OTRA empresa acá,
        // el updateOrCreate de abajo terminaría en una excepción SQL cruda en
        // vez de un error entendible -- y nunca debe quedar reasignado.
        $existing = WhatsappBusinessProfile::where('phone_number_id', $phoneNumberId)->first();
        if ($existing && (int) $existing->company_id !== (int) $company->id) {
            throw new RuntimeException('Este número de WhatsApp ya está conectado a otra empresa en esta plataforma.');
        }

        try {
            $token = $this->graph->exchangeCodeForToken($code);
            $phoneInfo = $this->graph->getPhoneNumber($phoneNumberId, $token);
            $wabaInfo = $this->graph->getWaba($wabaId, $token);
            $this->graph->subscribeApp($wabaId, $token);
        } catch (Throwable $e) {
            Log::error('[MetaEmbeddedSignupService] Falló la conexión de WhatsApp', [
                'company_id' => $company->id,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            // Se deja un registro en estado "error" para que el panel lo
            // muestre, sin credenciales (no llegamos a tener token válido).
            WhatsappBusinessProfile::updateOrCreate(
                ['company_id' => $company->id, 'phone_number_id' => $phoneNumberId],
                [
                    'business_name' => $company->name,
                    'display_name' => $company->name,
                    'whatsapp_business_id' => $wabaId,
                    'status' => WhatsappBusinessProfile::STATUS_ERROR,
                    'connection_type' => 'embedded_signup',
                    'metadata' => ['last_error' => $e->getMessage(), 'failed_at' => now()->toIso8601String()],
                ]
            );

            throw new RuntimeException('No se pudo completar la conexión con Meta: ' . $e->getMessage());
        }

        return WhatsappBusinessProfile::updateOrCreate(
            ['company_id' => $company->id, 'phone_number_id' => $phoneNumberId],
            [
                'business_name' => $company->name,
                'display_name' => $phoneInfo['verified_name'] ?? $company->name,
                'phone_number' => $phoneInfo['display_phone_number'] ?? null,
                'whatsapp_business_id' => $wabaInfo['id'] ?? $wabaId,
                'access_token' => $token,
                'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
                'connection_type' => 'embedded_signup',
                'connected_at' => now(),
                'metadata' => ['last_error' => null],
            ]
        );
    }
}
