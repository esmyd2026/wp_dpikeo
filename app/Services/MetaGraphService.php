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
     * manda webhooks a nuestra app.
     */
    public function subscribeApp(string $wabaId, string $token): void
    {
        $response = Http::withToken($token)->post("{$this->baseUrl}/{$this->apiVersion()}/{$wabaId}/subscribed_apps");

        $this->throwIfFailed($response, 'No se pudo suscribir la app a los webhooks de esta cuenta de WhatsApp Business.');
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
