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

    /**
     * $connectionMode distingue únicamente cómo quedó registrada la conexión
     * ('embedded_signup' vs 'whatsapp_business_app_coexistence'); no cambia
     * ningún paso del intercambio con Graph API -- el handshake server-to-server
     * es idéntico para los dos modos hasta que Meta indique lo contrario.
     */
    public function connect(Company $company, string $code, string $wabaId, string $phoneNumberId, string $connectionMode = 'standard'): WhatsappBusinessProfile
    {
        $connectionType = $connectionMode === 'coexistence' ? 'whatsapp_business_app_coexistence' : 'embedded_signup';

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

            // Paso final: sin esto Meta deja el número en "Pendiente" y no
            // habilita envío/recepción real, aunque los pasos de arriba hayan
            // funcionado. Si este número YA tiene un PIN de dos pasos
            // establecido (un registro anterior, exitoso, ya se lo puso a
            // Meta), hay que reenviar ESE MISMO PIN -- Meta rechaza con
            // "Two step verification PIN Mismatch" (133005) si en un
            // reintento se manda uno nuevo al azar. Solo se genera uno nuevo
            // la primera vez que este phone_number_id pasa por acá.
            $twoFactorPin = $existing?->two_factor_pin ?: sprintf('%06d', random_int(0, 999999));
            $this->graph->registerPhoneNumber($phoneNumberId, $token, $twoFactorPin);
        } catch (Throwable $e) {
            Log::error('[MetaEmbeddedSignupService] Falló la conexión de WhatsApp', [
                'company_id' => $company->id,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'connection_mode' => $connectionMode,
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
                    'connection_type' => $connectionType,
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
                'two_factor_pin' => $twoFactorPin,
                'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
                'connection_type' => $connectionType,
                'connected_at' => now(),
                'metadata' => ['last_error' => null],
            ]
        );
    }
}
