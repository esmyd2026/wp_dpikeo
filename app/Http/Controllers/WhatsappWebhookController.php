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
                Log::info("Respondiendo con challenge: " . $desafio);
                return response($desafio, 200);
            }
            return response("Token inválido", 403)->header('Content-Type', 'text/plain');
        } catch (\Throwable $excepcion) {
            Log::error("Error en la verificación del webhook: " . $excepcion->getMessage());
            return response()->json([
                'estado' => false,
                'mensaje' => $excepcion->getMessage()
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
            // Configuración pendiente: se deja pasar para no romper el bot en
            // producción, pero queda registrado para que se complete el setup.
            Log::warning('WHATSAPP_APP_SECRET no configurado: el webhook está aceptando payloads sin verificar su firma.');
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256');
        if (!str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, substr($header, 7));
    }

    public function webhook(Request $request)
    {
        try {
            if (!$this->hasValidSignature($request)) {
                Log::warning('Webhook de WhatsApp rechazado: firma X-Hub-Signature-256 inválida o ausente.');
                return response()->json(['estado' => false, 'mensaje' => 'Firma inválida'], 403);
            }

            // Decodificar el JSON recibido
            $data = json_decode($request->getContent(), true);
            //Log::info('Webhook payload:', $data);

            // Extraer la variable 'object'
            $object = $data['object'] ?? null;
            //Log::info("Objeto recibido: " . $object);

            // Extraer la primera entrada (entry)
            $entry = $data['entry'][0] ?? null;
            if (!$entry) {
                Log::warning("No se encontró entry en el payload");
                return response()->json(['estado' => false, 'mensaje' => 'No entry encontrada'], 400);
            }

            $entryId = $entry['id'] ?? null;
            //Log::info("ID de la entrada: " . $entryId);

            // Extraer el primer cambio dentro de la entrada
            $change = $entry['changes'][0] ?? null;
            if (!$change) {
                Log::warning("No se encontró change en la entry");
                return response()->json(['estado' => false, 'mensaje' => 'No change encontrado'], 400);
            }

            $value = $change['value'] ?? null;
            if (!$value) {
                Log::warning("No se encontró value en el change");
                return response()->json(['estado' => false, 'mensaje' => 'No value encontrado'], 400);
            }

            // Extraer variables desde 'value'
            $messagingProduct = $value['messaging_product'] ?? null;
            $metadata = $value['metadata'] ?? [];
            $contactsArray = $value['contacts'] ?? [];
            $messagesArray = $value['messages'] ?? [];
            $statusesArray = $value['statuses'] ?? [];

            //Log::info("Producto de mensajería: " . $messagingProduct);
            //Log::info("Metadata: " . json_encode($metadata, JSON_UNESCAPED_UNICODE));

            // Extraer y desglosar el primer contacto
            $contact = $contactsArray[0] ?? [];
            $contactName = $contact['profile']['name'] ?? null;
            $contactWaId = $contact['wa_id'] ?? null;
            Log::info("Contacto recibido - Nombre: " . $contactName . ", WA_ID: " . $contactWaId);

            // Si hay mensajes, procesarlos
            if (!empty($messagesArray)) {
                $message = $messagesArray[0];
                $from = $message['from'] ?? null;
                $messageId = $message['id'] ?? null;
                $timestamp = $message['timestamp'] ?? null;
                $type = $message['type'] ?? null;
                $mensajeContent = null;

                // Procesar según el tipo de mensaje
                switch ($type) {
                    case 'text':
                        $mensajeContent = $message['text']['body'] ?? null;
                        break;
                    case 'image':
                        $mensajeContent = $message['image'] ?? null;
                        break;
                    case 'audio':
                        $mensajeContent = $message['audio'] ?? null;
                        break;
                    case 'video':
                        $mensajeContent = $message['video'] ?? null;
                        break;
                    case 'document':
                        $mensajeContent = $message['document'] ?? null;
                        break;
                    case 'sticker':
                        $mensajeContent = $message['sticker'] ?? null;
                        break;
                    case 'location':
                        $mensajeContent = $message['location'] ?? null;
                        break;
                    case 'contact':
                        $mensajeContent = $message['contact'] ?? null;
                        break;
                    case 'interactive':
                        $interactive = $message['interactive'] ?? null;
                        if ($interactive) {
                            if (isset($interactive['button_reply'])) {
                                $mensajeContent = $interactive['button_reply'];
                            } elseif (isset($interactive['list_reply'])) {
                                $mensajeContent = $interactive['list_reply'];
                            }
                        }
                        break;
                    case 'order':
                        $mensajeContent = $message['order'] ?? null;
                        break;
                    default:
                        $mensajeContent = "Tipo de mensaje no soportado";
                        break;
                }

                //Log::info("Mensaje recibido desde: " . $from);
                //Log::info("ID del mensaje: " . $messageId);
                //Log::info("Timestamp: " . $timestamp);
                Log::info("Tipo de mensaje: " . $type);
                Log::info("Contenido del mensaje: " . json_encode($mensajeContent, JSON_UNESCAPED_UNICODE));

                if (!empty($metadata['phone_number_id'])) {
                    $this->whatsappService->setWebhookPhoneNumberId($metadata['phone_number_id']);
                }

                // Preparar datos para el servicio
                $messageData = [
                    'from' => $from,
                    'id' => $messageId,
                    'type' => $type,
                    'timestamp' => $timestamp,
                    'text' => $mensajeContent,
                    'contacts' => $contactsArray,
                    'interactive' => $message['interactive'] ?? null,
                    'order' => $message['order'] ?? null,
                    'location' => $message['location'] ?? null,
                ];

                // Los carritos del catálogo nativo deben recibir un 200 lo más
                // rápido posible. Crear el pedido también puede enviar una
                // respuesta a WhatsApp y tardar varios segundos; por eso se
                // procesa después de responder al webhook. Así Meta no marca
                // la solicitud del cliente como fallida por tiempo de espera.
                if ($type === 'order') {
                    dispatch(function () use ($messageData, $metadata) {
                        $service = app(WhatsappService::class);

                        if (!empty($metadata['phone_number_id'])) {
                            $service->setWebhookPhoneNumberId($metadata['phone_number_id']);
                        }

                        $service->processIncomingMessage($messageData);
                    })->afterResponse();
                } else {
                    // Los demás mensajes conservan su comportamiento actual,
                    // para que el cliente siga recibiendo navegación inmediata.
                    $this->whatsappService->processIncomingMessage($messageData);
                }
            }
            // Si hay actualizaciones de estado, procesarlas
            elseif (!empty($statusesArray)) {
                $status = $statusesArray[0];
                Log::info("Actualización de estado recibida:", $status);
                // Aquí puedes procesar las actualizaciones de estado si es necesario
            }
            else {
                Log::info("No hay mensajes ni actualizaciones de estado para procesar");
            }

            return response()->json([
                'estado' => true,
                'mensaje' => 'Webhook procesado correctamente',
                'datos' => [
                    'object' => $object,
                    'entry_id' => $entryId,
                    'messaging_product' => $messagingProduct,
                    'metadata' => $metadata,
                    'contact' => [
                        'nombre' => $contactName,
                        'wa_id' => $contactWaId,
                    ],
                    'from' => $from ?? null,
                    'message_id' => $messageId ?? null,
                    'timestamp' => $timestamp ?? null,
                    'type' => $type ?? null,
                    'contenido' => $mensajeContent ?? null,
                ]
            ], 200);
        } catch (\Throwable $excepcion) {
            Log::error("Error en el procesamiento del webhook: " . $excepcion->getMessage());
            return response()->json([
                'estado' => false,
                'mensaje' => $excepcion->getMessage()
            ], 500);
        }
    }
}
