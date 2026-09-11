<?php

namespace App\Http\Controllers;

use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function verify(Request $request)
    {
        Log::info('Parámetros del Webhook:', $request->query());

        $modo = $request->query('hub_mode');
        $tokenRecibido = (string) $request->query('hub_verify_token');
        $desafio = $request->query('hub_challenge');

        $tokenEsperado = (string) config('whatsapp.verify_token');

        try {
            if ($tokenEsperado === '') {
                Log::error('WHATSAPP_VERIFY_TOKEN/WEBHOOK_VERIFY_TOKEN no está configurado; rechazando verificación del webhook.');

                return response('Verify token no configurado', 500)->header('Content-Type', 'text/plain');
            }

            if ($modo === 'subscribe' && hash_equals($tokenEsperado, $tokenRecibido)) {
                Log::info('Respondiendo con challenge: '.$desafio);

                return response($desafio, 200);
            }

            return response('Token inválido', 403)->header('Content-Type', 'text/plain');
        } catch (\Throwable $excepcion) {
            Log::error('Error en la verificación del webhook: '.$excepcion->getMessage());

            return response()->json([
                'estado' => false,
                'mensaje' => $excepcion->getMessage(),
            ], 500);
        }
    }

    /**
     * Valida que el payload venga firmado por Meta con el App Secret
     * (cabecera X-Hub-Signature-256). Sin esto, cualquiera que conozca la
     * URL del webhook puede inyectar mensajes/pedidos falsos.
     */
    private function hasValidSignature(Request $request): bool
    {
        $appSecret = (string) config('whatsapp.app_secret');
        if ($appSecret === '') {
            // Compatibilidad con instalaciones existentes: mientras se configura
            // el secreto, conservamos el comportamiento previo del webhook.
            Log::warning('WHATSAPP_APP_SECRET no configurado: webhook procesado sin validar firma.');

            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, substr($header, 7));
    }

    public function webhook(Request $request)
    {
        try {
            if (! $this->hasValidSignature($request)) {
                Log::warning('Webhook de WhatsApp rechazado: firma X-Hub-Signature-256 inválida o ausente.');

                return response()->json(['estado' => false, 'mensaje' => 'Firma inválida'], 403);
            }

            $data = json_decode($request->getContent(), true);
            if (! is_array($data)) {
                Log::warning('Webhook de WhatsApp rechazado: JSON inválido.');

                return response()->json(['estado' => false, 'mensaje' => 'JSON inválido'], 400);
            }

            $entries = $data['entry'] ?? [];
            if (! is_array($entries) || $entries === []) {
                Log::warning('No se encontró entry en el payload');

                return response()->json(['estado' => false, 'mensaje' => 'No entry encontrada'], 400);
            }

            $processedMessages = 0;
            $processedStatuses = 0;

            // Meta puede agrupar varias cuentas, cambios y mensajes en una
            // misma petición. Recorrer todos evita perder mensajes silenciosamente.
            foreach ($entries as $entry) {
                foreach (($entry['changes'] ?? []) as $change) {
                    $value = $change['value'] ?? null;
                    if (! is_array($value)) {
                        Log::warning('Cambio de webhook omitido: no contiene value.');

                        continue;
                    }

                    $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
                    $contacts = is_array($value['contacts'] ?? null) ? $value['contacts'] : [];
                    $phoneNumberId = $metadata['phone_number_id'] ?? null;

                    if ($phoneNumberId) {
                        $this->whatsappService->setWebhookPhoneNumberId($phoneNumberId);
                    }

                    foreach (($value['messages'] ?? []) as $message) {
                        if (! is_array($message)) {
                            continue;
                        }

                        $messageData = $this->normalizeIncomingMessage($message, $contacts);
                        $type = $messageData['type'];

                        try {
                            if ($type === 'order') {
                                dispatch(function () use ($messageData, $phoneNumberId) {
                                    $service = app(WhatsappService::class);
                                    if ($phoneNumberId) {
                                        $service->setWebhookPhoneNumberId($phoneNumberId);
                                    }
                                    $service->processIncomingMessage($messageData);
                                })->afterResponse();
                            } else {
                                $this->whatsappService->processIncomingMessage($messageData);
                            }
                            $processedMessages++;
                        } catch (\Throwable $exception) {
                            Log::error('Error procesando mensaje individual del webhook', [
                                'message_id' => $messageData['id'],
                                'type' => $type,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }

                    foreach (($value['statuses'] ?? []) as $status) {
                        if (! is_array($status)) {
                            continue;
                        }
                        $this->whatsappService->processMessageStatus($status);
                        $processedStatuses++;
                    }
                }
            }

            Log::info('Webhook de WhatsApp procesado', [
                'entries' => count($entries),
                'messages' => $processedMessages,
                'statuses' => $processedStatuses,
            ]);

            return response()->json([
                'estado' => true,
                'mensaje' => 'Webhook procesado correctamente',
                'procesados' => [
                    'mensajes' => $processedMessages,
                    'estados' => $processedStatuses,
                ],
            ], 200);
        } catch (\Throwable $excepcion) {
            Log::error('Error en el procesamiento del webhook: '.$excepcion->getMessage());

            return response()->json([
                'estado' => false,
                'mensaje' => $excepcion->getMessage(),
            ], 500);
        }
    }

    /** @return array<string, mixed> */
    private function normalizeIncomingMessage(array $message, array $contacts): array
    {
        $type = (string) ($message['type'] ?? 'unknown');
        $typePayload = is_array($message[$type] ?? null) ? $message[$type] : null;

        $normalized = [
            'from' => $message['from'] ?? null,
            'id' => $message['id'] ?? null,
            'type' => $type,
            'timestamp' => $message['timestamp'] ?? null,
            'text' => $type === 'text' ? ($message['text']['body'] ?? null) : $typePayload,
            'contacts' => $contacts,
        ];

        // Los manejadores de multimedia, ubicación, respuestas interactivas
        // y pedidos esperan además la carga bajo su nombre de tipo.
        foreach (['image', 'audio', 'video', 'document', 'sticker', 'location', 'interactive', 'order', 'button'] as $payloadKey) {
            if (array_key_exists($payloadKey, $message)) {
                $normalized[$payloadKey] = $message[$payloadKey];
            }
        }

        if (array_key_exists('contacts', $message)) {
            $normalized['shared_contacts'] = $message['contacts'];
        }

        return $normalized;
    }
}
