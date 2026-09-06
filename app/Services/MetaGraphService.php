<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Llamadas a Graph API específicas del alta de una cuenta de WhatsApp vía
 * Embedded Signup (intercambio de código, datos del número/WABA, suscripción
 * del webhook). No reemplaza a WhatsappService::sendMessageToWhatsApp() ni al
 * resto de envíos ya existentes -- esos siguen igual, esto es exclusivamente
 * para la conexión inicial de una cuenta nueva.
 */
class MetaGraphService
{
    private string $baseUrl = 'https://graph.facebook.com';

    private function apiVersion(): string
    {
        return config('services.meta.graph_api_version', 'v26.0');
    }

    /**
     * Intercambia el "code" que devuelve FB.login() (Embedded Signup) por un
     * token de acceso. El app_secret nunca sale del backend.
     */
    public function exchangeCodeForToken(string $code): string
    {
        $response = Http::get("{$this->baseUrl}/{$this->apiVersion()}/oauth/access_token", [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'code' => $code,
        ]);

        $this->throwIfFailed($response, 'No se pudo intercambiar el código de autorización por un token.');

        $token = $response->json('access_token');
        if (!$token) {
            throw new RuntimeException('Meta no devolvió un access_token.');
        }

        return $token;
    }

    /** @return array{id: string, display_phone_number: ?string, verified_name: ?string} */
    public function getPhoneNumber(string $phoneNumberId, string $token): array
    {
        $response = Http::withToken($token)->get("{$this->baseUrl}/{$this->apiVersion()}/{$phoneNumberId}", [
            'fields' => 'id,display_phone_number,verified_name,quality_rating',
        ]);

        $this->throwIfFailed($response, 'No se pudo obtener la información del número de WhatsApp.');

        return $response->json();
    }

    /**
     * Diagnóstico de solo lectura para "Probar conexión" -- NO se usa en el
     * alta de Embedded Signup (esa sigue llamando getPhoneNumber() tal cual
     * estaba). Pide campos extra de solo consulta; si Meta rechaza alguno de
     * estos (p.ej. code_verification_status no disponible para esa versión
     * de la API), la llamada entera falla y el llamador lo reporta como
     * "conexión con problemas" -- no rompe getPhoneNumber() ni el alta real.
     */
    public function inspectPhoneNumber(string $phoneNumberId, string $token): array
    {
        $response = Http::withToken($token)->get("{$this->baseUrl}/{$this->apiVersion()}/{$phoneNumberId}", [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status',
        ]);

        $this->throwIfFailed($response, 'No se pudo consultar el número en Graph API.');

        return $response->json();
    }

    /** @return array{id: string, name: ?string} */
    public function getWaba(string $wabaId, string $token): array
    {
        $response = Http::withToken($token)->get("{$this->baseUrl}/{$this->apiVersion()}/{$wabaId}", [
            'fields' => 'id,name',
        ]);

        $this->throwIfFailed($response, 'No se pudo obtener la información de la cuenta de WhatsApp Business (WABA).');

        return $response->json();
    }

    /**
     * Paso obligatorio de Meta: sin esto la cuenta queda conectada pero no
     * manda webhooks a nuestra app. La app de Meta ("SigloTecnologico") es
     * Tech Provider y sirve a múltiples clientes con distintos backends
     * (p.ej. arka01) -- sin override_callback_uri, una WABA nueva hereda
     * el callback que sea que tenga la app a nivel global, que puede ser el
     * de otro cliente. Por eso siempre se fija explícitamente el callback
     * de esta app para cada WABA que conectamos, sin tocar la config
     * global ni afectar a otros clientes de la misma app de Meta.
     */
    public function subscribeApp(string $wabaId, string $token): void
    {
        $response = Http::withToken($token)->post("{$this->baseUrl}/{$this->apiVersion()}/{$wabaId}/subscribed_apps", [
            'override_callback_uri' => rtrim(config('app.url'), '/') . '/api/whatsapp/webhook',
            'verify_token' => config('whatsapp.verify_token'),
        ]);

        $this->throwIfFailed($response, 'No se pudo suscribir la app a los webhooks de esta cuenta de WhatsApp Business.');
    }

    /**
     * Paso final obligatorio de Cloud API para un número que llega por
     * Embedded Signup/coexistencia: sin esto Meta lo deja en "Pendiente"
     * (WhatsApp Manager) y no puede enviar/recibir mensajes reales, aunque
     * subscribeApp() ya haya funcionado -- subscribeApp() opera sobre la
     * WABA, esto opera sobre el número puntual. El PIN queda asociado al
     * número para la verificación en dos pasos de ahí en adelante.
     */
    public function registerPhoneNumber(string $phoneNumberId, string $token, string $pin): void
    {
        $response = Http::withToken($token)->post("{$this->baseUrl}/{$this->apiVersion()}/{$phoneNumberId}/register", [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);

        $this->throwIfFailed($response, 'No se pudo completar el registro del número en Cloud API.');
    }

    /** Verifica que un token siga vigente y a qué app/usuario pertenece (diagnóstico, nunca loguea el token). */
    public function debugToken(string $token): array
    {
        $appToken = config('services.meta.app_id') . '|' . config('services.meta.app_secret');

        $response = Http::get("{$this->baseUrl}/{$this->apiVersion()}/debug_token", [
            'input_token' => $token,
            'access_token' => $appToken,
        ]);

        $this->throwIfFailed($response, 'No se pudo validar el token con Meta.');

        return $response->json('data', []);
    }

    private function throwIfFailed(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        Log::error('[MetaGraphService] Error de Graph API', [
            'status' => $response->status(),
            'error' => $response->json('error'),
        ]);

        throw new RuntimeException($message);
    }
}
