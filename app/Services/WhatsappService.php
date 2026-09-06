<?php

namespace App\Services;

use App\Enums\MarketingButtonAction;
use App\Enums\MarketingStepKey;
use App\Exceptions\WhatsappBusinessProfileUnavailableException;
use App\Mail\MonitoringNotification;
use App\Models\BusinessBranch;
use App\Models\DeliveryDriver;
use App\Models\MarketingFlowStep;
use App\Models\MessageTemplate;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappChatbotResponse;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageFailure;
use App\Models\WhatsappPrice;
use App\Models\WhatsappTemplate;
use App\Services\Concerns\UsesMarketingFlow;
use App\Services\Concerns\UsesMarketingFlowGraph;
use App\Services\Whatsapp\WhatsappMessagePayload;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsappService
{
    use UsesMarketingFlow;
    use UsesMarketingFlowGraph;

    protected $baseUrl;

    protected $apiVersion;

    protected $businessPhone;

    protected $businessProfile;

    protected $lastMessage;

    /** @var string|null Phone Number ID del webhook actual (prioridad sobre BD) */
    protected $webhookPhoneNumberId = null;

    protected bool $webhookProfileKnown = true;

    protected bool $inboundMarkedRead = false;

    /**
     * true en cuanto alguien intentó explícitamente resolver un tenant para
     * esta instancia (useBusinessProfile() o setWebhookPhoneNumberId()), sin
     * importar si el intento tuvo éxito. Distingue "nadie pidió un tenant
     * concreto" (modo legacy de instalación mono-empresa, ver apiToken())
     * de "alguien pidió un tenant y no se pudo resolver" (nunca debe caer al
     * token global de otra empresa).
     */
    protected bool $tenantResolutionAttempted = false;

    protected function humanTrackingPayload(bool $humanSent): array
    {
        if (! $humanSent) {
            return ['admin_user_id' => null, 'metadata_extra' => []];
        }

        $adminId = auth()->id();

        return [
            'admin_user_id' => $adminId,
            'metadata_extra' => array_filter([
                'human_sent' => true,
                'human_sent_at' => now()->toIso8601String(),
                'admin_user_id' => $adminId,
            ]),
        ];
    }

    protected function botMayRespondToContact($contact): bool
    {
        return app(PlatformBillingService::class)->botMayRespondToContact($contact);
    }

    protected function logBotBlocked(string $context, $contact, array $extra = []): void
    {
        $billing = app(PlatformBillingService::class);

        Log::info("[{$context}] 🛑 Bot no responde", array_merge([
            'reason' => $billing->botBlockReason($contact),
            'contact_id' => $contact->id ?? null,
            'bot_enabled' => $contact->bot_enabled ?? null,
        ], $extra));
    }

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.api_url', 'https://graph.facebook.com');
        $this->apiVersion = config('whatsapp.api_version', 'v22.0');
        $this->businessPhone = config('whatsapp.phone_number');
        // Artisan resuelve algunos comandos al iniciar. En una instalación nueva
        // todavía no existe la tabla, por lo que no debemos impedir migraciones.
        // Sin ningún tenant explícito todavía (nadie llamó useBusinessProfile()
        // ni setWebhookPhoneNumberId()), solo se adopta un perfil "por
        // defecto" cuando es inequívoco -- exactamente uno usable en toda la
        // tabla (instalación mono-empresa clásica). Con 0 o 2+ no se adivina.
        $this->businessProfile = Schema::hasTable('whatsapp_business_profiles')
            ? $this->resolveUnambiguousLegacyProfile()
            : null;
        $this->lastMessage = null;

        if (empty($this->businessPhone)) {
            Log::error('WhatsApp phone number is not configured');
        }

        if (! $this->businessProfile) {
            Log::warning('No business profile found in database');
        }
    }

    private function resolveUnambiguousLegacyProfile(): ?WhatsappBusinessProfile
    {
        $usable = WhatsappBusinessProfile::usable()->get();

        return $usable->count() === 1 ? $usable->first() : null;
    }

    /**
     * El token siempre se resuelve desde $this->businessProfile en vez de
     * cachearse en una propiedad: setWebhookPhoneNumberId() puede cambiar el
     * perfil activo a mitad de request (multiempresa / multi-número), y este
     * método asegura que cada llamada a Graph API use el token del perfil
     * correcto en ese momento.
     *
     * Nunca cae al token global de .env cuando alguien ya intentó resolver un
     * tenant explícito para esta instancia (ver $tenantResolutionAttempted):
     * "empresa B sin perfil" jamás debe terminar enviando con las
     * credenciales de otra empresa. El fallback a config('whatsapp.token')
     * sigue existiendo SOLO para el modo legacy -- una instancia a la que
     * nunca se le pidió ningún tenant concreto (instalación mono-empresa
     * antigua, comandos de consola que todavía no pasan por CompanyContext).
     */
    protected function apiToken(): ?string
    {
        if ($this->businessProfile) {
            return $this->businessProfile->access_token;
        }

        if ($this->tenantResolutionAttempted) {
            throw new WhatsappBusinessProfileUnavailableException(
                'No hay un perfil de WhatsApp válido para esta operación (sin perfil, desconectado, o principal no configurado). Se rechaza el envío en vez de usar el token global de otra empresa.'
            );
        }

        return config('whatsapp.token');
    }

    /**
     * Config del chatbot (nombre del bot, IVA, links de pago, etc.) del
     * negocio activo. Si ese negocio todavía no tiene su propia fila, cae al
     * primer registro global como valor por defecto (mismo comportamiento de
     * antes de tener multiempresa).
     */
    /**
     * Los menús del bot (menú principal, productos, pedidos, info, etc.) se
     * identifican por un action_id fijo (ej. "main_menu") que se repite
     * igual en cada empresa -- sin este filtro, dos empresas con el mismo
     * action_id competirían por cuál aparece primero y una heredaría el menú
     * de la otra.
     */
    private function menuByActionId(string $actionId): ?WhatsappMenu
    {
        return WhatsappMenu::where('action_id', $actionId)
            ->where('business_profile_id', $this->businessProfile?->id)
            ->first();
    }

    /** Producto del catálogo, siempre acotado al negocio activo -- nunca de otra empresa. */
    private function findCatalogProduct(int $id): ?WhatsappPrice
    {
        return WhatsappPrice::where('id', $id)
            ->where('business_profile_id', $this->businessProfile?->id)
            ->first();
    }

    /** Categoría del catálogo, siempre acotada al negocio activo. */
    private function findCatalogCategory(int $id): ?WhatsappMenuItem
    {
        return WhatsappMenuItem::where('id', $id)
            ->where('business_profile_id', $this->businessProfile?->id)
            ->first();
    }

    private function scopedChatbotConfig(): ?WhatsappChatbotConfig
    {
        if ($this->businessProfile) {
            $config = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first();
            if ($config) {
                return $config;
            }
        }

        return WhatsappChatbotConfig::first();
    }

    /**
     * Registra un envío fallido (tras agotar los reintentos) para el módulo
     * de "Fallos de envío" del panel: quién no recibió el mensaje y por qué,
     * para que un admin lo pueda revisar y resolver. Nunca debe tumbar el
     * flujo de envío — si falla el registro, solo se loguea.
     *
     * @param  WhatsappContact|string|null  $contactOrPhone  Contacto o número (para cuando aún no se resolvió el contacto).
     */
    private function recordSendFailure($contactOrPhone, string $type, string $errorMessage, array $context = [], ?string $source = null): void
    {
        try {
            $contact = $contactOrPhone instanceof WhatsappContact
                ? $contactOrPhone
                : (is_string($contactOrPhone) ? $this->findContactByPhone($contactOrPhone) : null);

            WhatsappMessageFailure::create([
                'business_profile_id' => $this->businessProfile?->id,
                'contact_id' => $contact?->id,
                'phone_number' => $contact?->phone_number ?? (is_string($contactOrPhone) ? $contactOrPhone : null),
                'message_type' => $type,
                'source' => $source,
                'error_message' => Str::limit($errorMessage, 2000, ''),
                'context' => $context ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo registrar el fallo de envío en el módulo de fallos', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendTemplateMessage(WhatsappContact $contact, WhatsappTemplate $template, array $variables = [])
    {
        try {
            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact->phone_number,
                    'type' => 'template',
                    'template' => [
                        'name' => $template->name,
                        'language' => [
                            'code' => $template->language,
                        ],
                        'components' => $this->prepareTemplateComponents($template, $variables),
                    ],
                ]);

            if ($response->successful()) {
                $messageId = $response->json()['messages'][0]['id'];

                // Save the message in our database
                WhatsappMessage::create([
                    'business_profile_id' => $this->businessProfile ? $this->businessProfile->id : null,
                    'contact_id' => $contact->id,
                    'message_id' => $messageId,
                    'content' => $template->content,
                    'type' => 'template',
                    'status' => 'sent',
                    'metadata' => [
                        'template_name' => $template->name,
                        'variables' => $variables,
                    ],
                ]);

                return true;
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Error desconocido al enviar plantilla';
            $errorCode = $errorData['error']['code'] ?? null;
            $errorType = $errorData['error']['type'] ?? null;
            $fullError = "Error {$errorCode}: {$errorMessage}";

            Log::error('WhatsApp API Error', [
                'response' => $errorData,
                'contact' => $contact->phone_number,
                'template' => $template->name,
            ]);

            $this->recordSendFailure($contact, 'template', $fullError, ['template' => $template->name], 'sendTemplateMessage');

            // Retornar array con detalles para campañas, false para compatibilidad
            return [
                'success' => false,
                'error' => $fullError,
                'error_code' => $errorCode,
                'error_type' => $errorType,
                'boolean' => false,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error', [
                'error' => $e->getMessage(),
                'contact' => $contact->phone_number,
                'template' => $template->name,
            ]);

            $this->recordSendFailure($contact, 'template', $e->getMessage(), ['template' => $template->name], 'sendTemplateMessage');

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'boolean' => false,
            ];
        }
    }

    public function sendTextMessage(WhatsappContact $contact, string $message, bool $humanSent = false)
    {
        try {
            if (! $this->businessProfile || ! $this->businessProfile->phone_number_id) {
                Log::error('No business profile or phone_number_id found');

                return [
                    'success' => false,
                    'error' => 'Perfil de negocio o phone_number_id no configurado',
                    'boolean' => false,
                ];
            }

            if ($humanSent) {
                $this->sendTypingIndicatorForContact($contact);
            }

            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/messages";

            Log::info('Enviando mensaje de texto a WhatsApp API', [
                'url' => $url,
                'to' => $contact->phone_number,
                'message_length' => strlen($message),
                'human_sent' => $humanSent,
            ]);

            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact->phone_number,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $messageId = $responseData['messages'][0]['id'] ?? null;

                if ($messageId) {
                    $tracking = $this->humanTrackingPayload($humanSent);

                    WhatsappMessage::create([
                        'business_profile_id' => $this->businessProfile ? $this->businessProfile->id : null,
                        'contact_id' => $contact->id,
                        'admin_user_id' => $tracking['admin_user_id'],
                        'message_id' => $messageId,
                        'content' => $message,
                        'type' => 'text',
                        'status' => 'sent',
                        'sender_type' => $humanSent ? 'humano' : 'system',
                        'receiver_type' => 'client',
                        'metadata' => ! empty($tracking['metadata_extra']) ? $tracking['metadata_extra'] : null,
                    ]);

                    Log::info('Mensaje de texto guardado en BD', [
                        'message_id' => $messageId,
                        'contact_id' => $contact->id,
                    ]);
                }

                return true;
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Error desconocido al enviar mensaje de texto';
            $errorCode = $errorData['error']['code'] ?? null;
            $errorType = $errorData['error']['type'] ?? null;
            $fullError = "Error {$errorCode}: {$errorMessage}";

            Log::error('WhatsApp API Error al enviar texto', [
                'status' => $response->status(),
                'response' => $errorData,
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'text', $fullError, ['preview' => Str::limit($message, 200)], 'sendTextMessage');

            // Retornar array con detalles para campañas, false para compatibilidad
            return [
                'success' => false,
                'error' => $fullError,
                'error_code' => $errorCode,
                'error_type' => $errorType,
                'boolean' => false,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error al enviar texto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'text', $e->getMessage(), ['preview' => Str::limit($message, 200)], 'sendTextMessage');

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'boolean' => false,
            ];
        }
    }

    public function sendImageMessage(WhatsappContact $contact, $imagePath, ?string $caption = null, bool $humanSent = false)
    {
        try {
            if (! $this->businessProfile) {
                Log::error('No business profile found');

                return false;
            }

            if ($humanSent) {
                $this->sendTypingIndicatorForContact($contact);
            }

            // Primero subir la imagen a WhatsApp Media API
            $uploadResponse = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => mime_content_type($imagePath),
                ]);

            if (! $uploadResponse->successful()) {
                Log::error('WhatsApp Media Upload Error', [
                    'response' => $uploadResponse->json(),
                    'contact' => $contact->phone_number,
                ]);
                $this->recordSendFailure($contact, 'image', 'Error al subir la imagen a WhatsApp: '.json_encode($uploadResponse->json()), [], 'sendImageMessage');

                return false;
            }

            $mediaId = $uploadResponse->json()['id'];

            // Ahora enviar el mensaje con la imagen
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone_number,
                'type' => 'image',
                'image' => [
                    'id' => $mediaId,
                ],
            ];

            if ($caption) {
                $payload['image']['caption'] = $caption;
            }

            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json()['messages'][0]['id'];

                $tracking = $this->humanTrackingPayload($humanSent);
                $metadata = array_merge([
                    'media_id' => $mediaId,
                    'has_caption' => ! empty($caption),
                ], $tracking['metadata_extra']);

                WhatsappMessage::create([
                    'business_profile_id' => $this->businessProfile ? $this->businessProfile->id : null,
                    'contact_id' => $contact->id,
                    'admin_user_id' => $tracking['admin_user_id'],
                    'message_id' => $messageId,
                    'content' => $caption ?? '',
                    'type' => 'image',
                    'status' => 'sent',
                    'sender_type' => $humanSent ? 'humano' : 'system',
                    'receiver_type' => 'client',
                    'metadata' => $metadata,
                ]);

                return true;
            }

            Log::error('WhatsApp API Error', [
                'response' => $response->json(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'image', 'Error al enviar imagen: '.json_encode($response->json()), [], 'sendImageMessage');

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error', [
                'error' => $e->getMessage(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'image', $e->getMessage(), [], 'sendImageMessage');

            return false;
        }
    }

    /**
     * Imagen + un botón interactivo que apunta a un producto real del
     * catálogo (id 'producto_{id}'), el mismo id que ya reconoce el switch
     * de handleInteractiveMessage. Así, al tocar el botón, el cliente entra
     * exactamente al mismo flujo de ficha de producto / agregar al carrito
     * que ya existe — no hay una ruta de compra paralela que mantener.
     *
     * Usa la URL pública de la imagen (ya servida por storage:link) en vez
     * de subirla a la API de Media de WhatsApp: evita resubir el mismo
     * archivo una vez por cada destinatario de la campaña.
     */
    public function sendImageWithButtonMessage(
        WhatsappContact $contact,
        string $imageUrl,
        string $bodyText,
        int $productId,
        string $buttonTitle
    ) {
        try {
            if (! $this->businessProfile || ! $this->businessProfile->phone_number_id) {
                Log::error('No business profile or phone_number_id found');

                return ['success' => false, 'error' => 'Perfil de negocio o phone_number_id no configurado', 'boolean' => false];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone_number,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'header' => [
                        'type' => 'image',
                        'image' => ['link' => $imageUrl],
                    ],
                    'body' => ['text' => $bodyText],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'producto_'.$productId,
                                    'title' => Str::limit($buttonTitle, 20, ''),
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json()['messages'][0]['id'] ?? null;

                WhatsappMessage::create([
                    'business_profile_id' => $this->businessProfile->id,
                    'contact_id' => $contact->id,
                    'message_id' => $messageId,
                    'content' => $bodyText,
                    'type' => 'interactive',
                    'status' => 'sent',
                    'sender_type' => 'system',
                    'receiver_type' => 'client',
                    'metadata' => [
                        'image_url' => $imageUrl,
                        'product_id' => $productId,
                        'button_title' => $buttonTitle,
                    ],
                ]);

                return true;
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Error desconocido al enviar imagen con botón';
            $errorCode = $errorData['error']['code'] ?? null;

            Log::error('WhatsApp API Error', [
                'response' => $errorData,
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'interactive', "Error {$errorCode}: {$errorMessage}", ['product_id' => $productId], 'sendImageWithButtonMessage');

            return [
                'success' => false,
                'error' => "Error {$errorCode}: {$errorMessage}",
                'boolean' => false,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error', [
                'error' => $e->getMessage(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'interactive', $e->getMessage(), ['product_id' => $productId], 'sendImageWithButtonMessage');

            return ['success' => false, 'error' => $e->getMessage(), 'boolean' => false];
        }
    }

    public function handleWebhook(array $payload)
    {
        try {
            Log::info('[handleWebhook] 📱 Webhook recibido', [
                'tipo' => $payload['object'] ?? 'desconocido',
                'entry_id' => $payload['entry'][0]['id'] ?? 'sin_id',
                'company_id' => $this->businessProfile?->company_id,
                'business_profile_id' => $this->businessProfile?->id,
            ]);

            $entry = $payload['entry'][0] ?? null;
            if (! $entry) {
                Log::error('❌ Webhook inválido: No se encontró entry');

                return;
            }

            $changes = $entry['changes'][0] ?? null;
            if (! $changes) {
                Log::error('❌ Webhook inválido: No se encontraron cambios');

                return;
            }

            $value = $changes['value'] ?? null;
            if (! $value) {
                Log::error('❌ Webhook inválido: No se encontró value');

                return;
            }

            $this->setWebhookPhoneNumberId($value['metadata']['phone_number_id'] ?? null);
            $this->inboundMarkedRead = false;

            // Procesar mensajes entrantes
            if (isset($value['messages']) && is_array($value['messages'])) {
                foreach ($value['messages'] as $message) {
                    try {
                        // Validar estructura del mensaje
                        if (! isset($message['from']) || ! isset($message['id'])) {
                            Log::warning('⚠️ Estructura de mensaje inválida', [
                                'tiene_from' => isset($message['from']),
                                'tiene_id' => isset($message['id']),
                                'mensaje' => $message,
                            ]);

                            continue;
                        }

                        // Extraer datos del mensaje
                        $messageData = [
                            'from' => $message['from'],
                            'id' => $message['id'],
                            'type' => $message['type'] ?? 'text',
                            'timestamp' => $message['timestamp'] ?? null,
                            'text' => $message['text']['body'] ?? null,
                            'contacts' => $value['contacts'] ?? [],
                        ];

                        // Si es una imagen, procesar los datos de la imagen
                        if ($message['type'] === 'image' && isset($message['image'])) {
                            $messageData['image'] = [
                                'id' => $message['image']['id'] ?? null,
                                'mime_type' => $message['image']['mime_type'] ?? null,
                                'sha256' => $message['image']['sha256'] ?? null,
                                'caption' => $message['image']['caption'] ?? null,
                            ];

                            // Verificar que tenemos los datos mínimos necesarios de la imagen
                            if (empty($messageData['image']['id']) || empty($messageData['image']['mime_type'])) {
                                Log::warning('⚠️ Datos de imagen incompletos', [
                                    'tiene_id' => ! empty($messageData['image']['id']),
                                    'tiene_mime_type' => ! empty($messageData['image']['mime_type']),
                                    'mensaje' => $message,
                                ]);

                                continue;
                            }

                            Log::info('📸 Datos de imagen recibidos', [
                                'id' => $messageData['image']['id'],
                                'mime_type' => $messageData['image']['mime_type'],
                                'sha256' => $messageData['image']['sha256'],
                            ]);
                        }

                        $this->processIncomingMessage($messageData);
                    } catch (\Exception $e) {
                        Log::error('❌ Error procesando mensaje individual', [
                            'error' => $e->getMessage(),
                            'linea' => $e->getLine(),
                            'mensaje' => $message,
                        ]);
                    }
                }
            }

            // Procesar actualizaciones de estado
            if (isset($value['statuses']) && is_array($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    if (isset($status['id']) && isset($status['status'])) {
                        $this->updateMessageStatus($status);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ Error en webhook', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);
        }
    }

    public function processIncomingMessage(array $message) // principal
    {
        Log::info('[inicio] processIncomingMessage');
        try {
            // Validar datos requeridos
            if (empty($message['from']) || empty($message['id'])) {
                Log::warning('⚠️ Datos de mensaje incompletos', [
                    'tiene_from' => ! empty($message['from']),
                    'tiene_id' => ! empty($message['id']),
                ]);

                return;
            }

            // Siempre actualizar wamid entrante (también en reintentos del webhook)
            $this->rememberInboundFromWebhook($message);

            if ($this->webhookPhoneNumberId && ! $this->webhookProfileKnown) {
                Log::warning('[processIncomingMessage] Mensaje ignorado: línea WhatsApp no registrada en la app', [
                    'phone_number_id' => $this->webhookPhoneNumberId,
                    'from' => substr($message['from'], 0, 4).'****'.substr($message['from'], -4),
                ]);

                return;
            }

            // Meta puede reintentar la entrega del mismo webhook (o dos workers
            // podrían recibirlo casi al mismo tiempo). Sin este lock, ambos
            // procesos pasaban la verificación de "¿ya existe?" antes de que
            // cualquiera guardara el mensaje, duplicando efectos secundarios
            // (doble alta al carrito, doble consulta a ChatGPT, doble envío).
            $lock = Cache::lock('wa-inbound-message:'.$message['id'], 30);
            if (! $lock->get()) {
                Log::info('[processIncomingMessage] ⏭️ Mensaje en procesamiento simultáneo, se omite duplicado', [
                    'message_id' => $message['id'],
                ]);

                return;
            }

            try {
                // Verificar si el mensaje ya fue procesado
                $existingMessage = WhatsappMessage::where('message_id', $message['id'])->first();
                if ($existingMessage) {
                    return;
                }

                // Log::info('[processIncomingMessage] 📥 Mensaje recibido', [
                //    'de' => substr($message['from'], 0, 4) . '****' . substr($message['from'], -4),
                //    'tipo' => $message['type'],
                //    'id' => $message['id'],
                //    'contenido' => $message['text'] ?? ($message['interactive'] ?? null)
                // ]);

                // Procesar según el tipo de mensaje
                if ($message['type'] === 'text') {
                    $this->handleTextMessage($message);
                } elseif ($message['type'] === 'interactive') {
                    $this->handleInteractiveMessage($message);
                } elseif ($message['type'] === 'order') {
                    $this->handleNativeCatalogOrder($message);
                } elseif ($message['type'] === 'image') {
                    $this->handleImageMessage($message);
                } elseif ($message['type'] === 'audio') {
                    $this->handleAudioMessage($message);
                } elseif ($message['type'] === 'video') {
                    $this->handleVideoMessage($message);
                } elseif ($message['type'] === 'document') {
                    $this->handleDocumentMessage($message);
                } elseif ($message['type'] === 'location') {
                    $this->handleLocationMessage($message);
                } elseif ($message['type'] === 'sticker') {
                    $this->handleStickerMessage($message);
                } elseif ($message['type'] === 'button') {
                    $this->handleButtonMessage($message);
                }

                // Marcar como leído (si el typing no lo hizo ya)
                if (! $this->inboundMarkedRead && ! empty($message['id']) && ! empty($message['from'])) {
                    $this->markMessageAsRead($message['id'], $message['from']);
                }

                // Enviar notificaciones de monitoreo
                $this->sendMonitoringNotifications($message);
            } finally {
                $lock->release();
            }
        } catch (\Exception $e) {
            Log::error('❌ Error procesando mensaje', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'mensaje' => $message,
            ]);
        }
    }

    protected function handleButtonMessage($message)
    {
        try {
            Log::info('[handleButtonMessage] 🔘 Button message received', [
                'from' => substr($message['from'], 0, 4).'****'.substr($message['from'], -4),
                'message_id' => $message['id'],
            ]);

            // Marcar el mensaje como leído
            $this->markMessageAsRead($message['id'], $message['from']);

            // Obtener los menús desde la base de datos
            $productosMenu = $this->menuByActionId('menu_productos');
            $pedidosMenu = $this->menuByActionId('menu_pedido');
            $infoMenu = $this->menuByActionId('menu_info');

            $menuMessage = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => '¿En qué más puedo ayudarte?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_pedido',
                                    'title' => $pedidosMenu ? $pedidosMenu->button_text : '📦 Ver Pedidos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_info',
                                    'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $contact = $this->findContactByPhone($message['from']);
            $this->sendMessage($message['from'], $this->getMainMenu(null, $contact));

        } catch (\Exception $e) {
            Log::error('Error al manejar mensaje de botón', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'message' => $message,
            ]);
        }
    }

    protected function handleAudioMessage($message)
    {
        try {
            $from = $message['from'];
            $messageId = $message['id'];

            Log::info('[handleAudioMessage] 🎵 Audio message received', [
                'from' => substr($from, 0, 4).'****'.substr($from, -4),
                'message_id' => $messageId,
            ]);

            // Marcar como leído
            $this->markMessageAsRead($messageId, $from);

            // Obtener o crear contacto
            $contact = WhatsappContact::firstOrCreate(
                ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
                [
                    'name' => 'Contacto sin nombre',
                    'status' => 'active',
                ]
            );

            // Guardar el mensaje
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'type' => 'audio',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'metadata' => [
                    'timestamp' => $message['timestamp'] ?? null,
                ],
            ]);

            // Obtener los menús desde la base de datos
            $productosMenu = $this->menuByActionId('menu_productos');
            $infoMenu = $this->menuByActionId('menu_info');

            // Enviar respuesta
            $response = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => "🎵 *Mensaje de audio recibido*\n\n".
                            'Gracias por tu mensaje de audio. ¿En qué más puedo ayudarte?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_info',
                                    'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->sendMessage($from, $response);

        } catch (\Exception $e) {
            Log::error('❌ Error processing audio message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }

    protected function handleVideoMessage($message)
    {
        try {
            $from = $message['from'];
            $messageId = $message['id'];

            Log::info('[handleVideoMessage] 🎥 Video message received', [
                'from' => substr($from, 0, 4).'****'.substr($from, -4),
                'message_id' => $messageId,
            ]);

            // Marcar como leído
            $this->markMessageAsRead($messageId, $from);

            // Obtener o crear contacto
            $contact = WhatsappContact::firstOrCreate(
                ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
                [
                    'name' => 'Contacto sin nombre',
                    'status' => 'active',
                ]
            );

            // Guardar el mensaje
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'type' => 'video',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'metadata' => [
                    'timestamp' => $message['timestamp'] ?? null,
                ],
            ]);

            // Obtener los menús desde la base de datos
            $productosMenu = $this->menuByActionId('menu_productos');
            $infoMenu = $this->menuByActionId('menu_info');

            // Enviar respuesta
            $response = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => "🎥 *Video recibido*\n\n".
                            'Gracias por compartir el video. ¿En qué más puedo ayudarte?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_info',
                                    'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->sendMessage($from, $response);

        } catch (\Exception $e) {
            Log::error('❌ Error processing video message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }

    protected function handleDocumentMessage($message)
    {
        try {
            $from = $message['from'];
            $messageId = $message['id'];

            Log::info('[handleDocumentMessage] 📄 Document message received', [
                'from' => substr($from, 0, 4).'****'.substr($from, -4),
                'message_id' => $messageId,
            ]);

            // Marcar como leído
            $this->markMessageAsRead($messageId, $from);

            // Obtener o crear contacto
            $contact = WhatsappContact::firstOrCreate(
                ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
                [
                    'name' => 'Contacto sin nombre',
                    'status' => 'active',
                ]
            );

            // Guardar el mensaje
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'type' => 'document',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'content' => json_encode($message['text'] ?? $message['document'] ?? 'Documento recibido'),
                'metadata' => [
                    'filename' => $message['document']['filename'] ?? null,
                    'mime_type' => $message['document']['mime_type'] ?? null,
                    'timestamp' => $message['timestamp'] ?? null,
                ],
            ]);

            $proofCart = $this->findCartPendingProofUpload($contact);
            if ($proofCart) {
                $response = $this->registrarComprobantePago($contact, $proofCart, $message, 'document');
                if ($response) {
                    $this->sendMessage($from, $response);
                }

                return;
            }

            // Obtener los menús desde la base de datos
            $productosMenu = $this->menuByActionId('menu_productos');
            $infoMenu = $this->menuByActionId('menu_info');

            // Enviar respuesta interactiva
            $response = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => "📄 *Documento recibido*\n\n".
                            'Gracias por compartir el documento. ¿En qué más puedo ayudarte?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_info',
                                    'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->sendMessage($from, $response);

        } catch (\Exception $e) {
            Log::error('❌ Error processing document message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }

    protected function handleLocationMessage($message)
    {
        try {
            $from = $message['from'];
            $messageId = $message['id'];

            Log::info('[handleLocationMessage] 📍 Location message received', [
                'from' => substr($from, 0, 4).'****'.substr($from, -4),
                'message_id' => $messageId,
            ]);

            // Marcar como leído
            $this->markMessageAsRead($messageId, $from);

            // Obtener o crear contacto
            $contact = WhatsappContact::firstOrCreate(
                ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
                [
                    'name' => 'Contacto sin nombre',
                    'status' => 'active',
                ]
            );

            // Guardar el mensaje
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'type' => 'location',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'content' => json_encode($message['location'] ?? 'Ubicación recibida'),
                'metadata' => [
                    'latitude' => $message['location']['latitude'] ?? null,
                    'longitude' => $message['location']['longitude'] ?? null,
                    'timestamp' => $message['timestamp'] ?? null,
                ],
            ]);

            // Si el cliente comparte su ubicación por su cuenta mientras
            // esperamos la dirección de entrega, la aceptamos como dirección
            // (con link a Google Maps) y seguimos pidiendo el nombre de quien
            // recibe, igual que si hubiera escrito la dirección en texto. Ya
            // no se calcula el envío por GPS: el vendedor lo confirma desde
            // el panel (ver buildFulfillmentSummaryText).
            $cart = WhatsappCart::where('contact_id', $contact->id)
                ->where('status', 'active')
                ->first();

            if ($cart && ! empty($cart->metadata['awaiting_delivery_address'] ?? false)) {
                $lat = $message['location']['latitude'] ?? null;
                $lon = $message['location']['longitude'] ?? null;

                // Si Meta no mandó coordenadas reales (payload incompleto),
                // no guardamos un link roto tipo "q=0,0": le pedimos que
                // escriba la dirección en su lugar.
                if ($lat === null || $lon === null || ((float) $lat === 0.0 && (float) $lon === 0.0)) {
                    Log::warning('[handleLocationMessage] Ubicación sin coordenadas válidas, se pide dirección por texto', [
                        'contact_id' => $contact->id,
                        'cart_id' => $cart->id,
                    ]);

                    $this->sendMessage($from, [
                        'type' => 'text',
                        'text' => ['body' => '📍 No pudimos leer tu ubicación. ¿Puedes escribirnos la *dirección completa* de entrega (calle, sector, referencia)?'],
                    ]);

                    return;
                }

                $lat = (float) $lat;
                $lon = (float) $lon;
                $mapsUrl = "https://maps.google.com/?q={$lat},{$lon}";

                $metadata = $cart->metadata ?? [];
                unset($metadata['awaiting_delivery_address']);
                $metadata['delivery_location'] = [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'manual_address' => "Ubicación compartida: {$mapsUrl}",
                ];
                $metadata['awaiting_delivery_recipient_name'] = true;
                $cart->metadata = $metadata;
                $cart->save();

                $this->sendMessage($from, $this->buildRecipientNamePrompt($contact));

                return;
            }

            // Obtener los menús desde la base de datos
            $productosMenu = $this->menuByActionId('menu_productos');
            $infoMenu = $this->menuByActionId('menu_info');

            // Enviar respuesta
            $response = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => "📍 *Ubicación recibida*\n\n".
                            'Gracias por compartir tu ubicación. ¿En qué más puedo ayudarte?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_info',
                                    'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->sendMessage($from, $response);

        } catch (\Exception $e) {
            Log::error('❌ Error processing location message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }

    protected function handleStickerMessage($message)
    {
        try {
            $from = $message['from'];
            $messageId = $message['id'];

            Log::info('[handleStickerMessage] 🎯 Sticker message received', [
                'from' => substr($from, 0, 4).'****'.substr($from, -4),
                'message_id' => $messageId,
            ]);

            // Marcar como leído
            $this->markMessageAsRead($messageId, $from);

            // Obtener o crear contacto
            $contact = WhatsappContact::firstOrCreate(
                ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
                [
                    'name' => 'Contacto sin nombre',
                    'status' => 'active',
                ]
            );

            // Guardar el mensaje
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'type' => 'sticker',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'content' => json_encode($message['text'] ?? $message['sticker'] ?? 'Sticker recibido'),
                'metadata' => [
                    'timestamp' => $message['timestamp'] ?? null,
                ],
            ]);

            // Obtener los menús desde la base de datos
            $productosMenu = $this->menuByActionId('menu_productos');
            $infoMenu = $this->menuByActionId('menu_info');

            // Enviar respuesta
            $response = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => "🎯 *Sticker recibido*\n\n".
                            'Gracias por compartir el sticker. ¿En qué más puedo ayudarte?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_info',
                                    'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $this->sendMessage($from, $response);

        } catch (\Exception $e) {
            Log::error('❌ Error processing sticker message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }

    protected function updateMessageStatus($status)
    {
        try {
            $messageId = $status['id'] ?? null;
            $newStatus = $status['status'] ?? null;
            $timestamp = $status['timestamp'] ?? null;

            if (! $messageId || ! $newStatus) {
                Log::error('❌ Estado inválido', ['status' => $status]);

                return;
            }

            $message = WhatsappMessage::where('message_id', $messageId)->first();
            if (! $message) {
                Log::warning('⚠️ Mensaje no encontrado', ['id' => $messageId]);

                return;
            }

            // Solo actualizar si el nuevo estado es más reciente
            if ($timestamp && $message->updated_at && strtotime($timestamp) <= strtotime($message->updated_at)) {
                Log::info('[updateMessageStatus] ⏭️ Estado obsoleto ignorado', [
                    'message_id' => $status['id'],
                    'estado_actual' => $message->status,
                    'nuevo_estado' => $status['status'],
                ]);

                return;
            }

            $message->status = $newStatus;
            $message->save();

            Log::info('[updateMessageStatus] ✅ Estado actualizado', [
                'message_id' => $status['id'],
                'estado' => $status['status'],
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error actualizando estado', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);
        }
    }

    /**
     * Verifica si hay actividad humana reciente en un chat
     * Retorna true si hay actividad humana en las últimas X horas (por defecto 2 horas)
     */
    protected function hasRecentHumanActivity(WhatsappContact $contact, int $hoursThreshold = 2): bool
    {
        $thresholdTime = now()->subHours($hoursThreshold);

        // Buscar el último mensaje enviado por un humano (desde el panel)
        $lastHumanMessage = WhatsappMessage::where('contact_id', $contact->id)
            ->where('sender_type', 'system')
            ->whereNotNull('metadata->human_sent')
            ->where('metadata->human_sent', true)
            ->where('created_at', '>=', $thresholdTime)
            ->latest('created_at')
            ->first();

        if ($lastHumanMessage) {
            Log::info('[hasRecentHumanActivity] ✅ Actividad humana detectada', [
                'contact_id' => $contact->id,
                'last_human_message_id' => $lastHumanMessage->id,
                'last_human_message_at' => $lastHumanMessage->created_at,
                'hours_ago' => now()->diffInHours($lastHumanMessage->created_at),
            ]);

            return true;
        }

        Log::debug('[hasRecentHumanActivity] ❌ No hay actividad humana reciente', [
            'contact_id' => $contact->id,
            'threshold_hours' => $hoursThreshold,
        ]);

        return false;
    }

    /**
     * Verifica si hay actividad humana reciente por número de teléfono
     */
    protected function hasRecentHumanActivityByPhone(string $phoneNumber, int $hoursThreshold = 2): bool
    {
        $contact = $this->findContactByPhone($phoneNumber);
        if (! $contact) {
            return false;
        }

        return $this->hasRecentHumanActivity($contact, $hoursThreshold);
    }

    protected function handleChatbotResponse($contact, $message)
    {
        try {
            // Refrescar el contacto desde la base de datos para obtener el valor actualizado de bot_enabled
            $contact->refresh();

            // Verificar si el bot está habilitado para este contacto
            if (! $this->botMayRespondToContact($contact)) {
                $this->logBotBlocked('handleChatbotResponse', $contact, [
                    'message_id' => $message->id,
                    'message_content' => substr($message->content, 0, 100),
                ]);

                return null; // No enviar respuesta automática
            }

            // Si el bot está activado manualmente, NO verificar actividad humana reciente
            // El bot funcionará inmediatamente cuando esté activado

            if (! empty($message->message_id)) {
                $this->sendTypingIndicator($message->message_id);
            }

            $response = $this->generateChatbotResponse($message->content, $message->contact->phone_number);

            if ($response && ! empty($message->message_id)) {
                $this->prepareBotReply($contact, $message->message_id);
            }

            // Enviar la respuesta
            $result = $response ? $this->sendMessageToWhatsApp($contact->phone_number, $response) : false;

            if ($result) {
                // Extraer el contenido del mensaje según el tipo
                $content = '';
                if ($response['type'] === 'text') {
                    $content = $response['text']['body'];
                } elseif ($response['type'] === 'interactive') {
                    $content = $response['interactive']['body']['text'];
                }

                // Crear el mensaje de respuesta
                $responseMessage = WhatsappMessage::create([
                    'contact_id' => $contact->id,
                    'business_profile_id' => $this->businessProfile->id,
                    'message_id' => $result['message_id'],
                    'content' => $content,
                    'type' => $response['type'],
                    'status' => 'sent',
                    'metadata' => [
                        'is_bot_response' => true,
                        'interactive_data' => $response['type'] === 'interactive' ? $response['interactive'] : null,
                    ],
                ]);

                Log::info('[handleChatbotResponse] Chatbot response handled successfully', [
                    'contact_id' => $contact->id,
                    'message' => $message,
                ]);

                return $responseMessage;
            }

            Log::error('Failed to send chatbot response', [
                'contact_id' => $contact->id,
                'response' => $response,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error handling chatbot response', [
                'error' => $e->getMessage(),
                'contact_id' => $contact->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function sendAudioMessage(WhatsappContact $contact, $audioPath, ?string $caption = null, bool $humanSent = false)
    {
        try {
            if (! $this->businessProfile) {
                Log::error('No business profile found');

                return false;
            }

            if ($humanSent) {
                $this->sendTypingIndicatorForContact($contact);
            }

            // Detectar el tipo MIME del archivo
            $mimeType = mime_content_type($audioPath);
            $extension = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));

            // WhatsApp no acepta video/webm para audio. Si se detecta como video/webm,
            // forzar el tipo según la extensión del archivo
            if ($mimeType === 'video/webm' || $mimeType === 'application/octet-stream') {
                // Determinar el tipo según la extensión
                switch ($extension) {
                    case 'ogg':
                        $mimeType = 'audio/ogg';
                        break;
                    case 'webm':
                        // Intentar como audio/webm, pero WhatsApp puede no aceptarlo
                        // Mejor convertir a OGG o rechazar
                        Log::warning('WhatsApp Audio: WebM detectado, puede no ser compatible', [
                            'path' => $audioPath,
                            'extension' => $extension,
                        ]);
                        $mimeType = 'audio/webm'; // Intentar de todas formas
                        break;
                    case 'mp3':
                        $mimeType = 'audio/mpeg';
                        break;
                    case 'wav':
                        $mimeType = 'audio/wav';
                        break;
                    case 'm4a':
                        $mimeType = 'audio/mp4';
                        break;
                    case 'aac':
                        $mimeType = 'audio/aac';
                        break;
                    default:
                        Log::error('WhatsApp Audio Error: No se pudo determinar el tipo MIME', [
                            'path' => $audioPath,
                            'detected_mime' => mime_content_type($audioPath),
                            'extension' => $extension,
                        ]);

                        return false;
                }
            }

            // Validar que el tipo MIME sea compatible con WhatsApp
            // WhatsApp acepta: audio/aac, audio/mp4, audio/mpeg, audio/amr, audio/ogg, audio/opus
            // NO acepta audio/webm directamente, pero lo intentaremos
            $allowedAudioTypes = ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg', 'audio/opus', 'audio/wav'];
            if (! in_array($mimeType, $allowedAudioTypes) && $mimeType !== 'audio/webm') {
                Log::error('WhatsApp Audio Error: Tipo MIME no compatible', [
                    'mime_type' => $mimeType,
                    'allowed_types' => $allowedAudioTypes,
                    'path' => $audioPath,
                    'extension' => $extension,
                ]);

                return false;
            }

            // Si es webm, advertir que puede fallar
            if ($mimeType === 'audio/webm') {
                Log::warning('WhatsApp Audio: Enviando WebM, puede no ser compatible con WhatsApp', [
                    'path' => $audioPath,
                ]);
            }

            // Primero subir el audio a WhatsApp Media API
            $uploadResponse = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->attach('file', file_get_contents($audioPath), basename($audioPath))
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            if (! $uploadResponse->successful()) {
                Log::error('WhatsApp Media Upload Error (Audio)', [
                    'response' => $uploadResponse->json(),
                    'contact' => $contact->phone_number,
                ]);
                $this->recordSendFailure($contact, 'audio', 'Error al subir el audio a WhatsApp: '.json_encode($uploadResponse->json()), [], 'sendAudioMessage');

                return false;
            }

            $mediaId = $uploadResponse->json()['id'];

            // Ahora enviar el mensaje con el audio
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone_number,
                'type' => 'audio',
                'audio' => [
                    'id' => $mediaId,
                ],
            ];

            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json()['messages'][0]['id'];

                $tracking = $this->humanTrackingPayload($humanSent);
                $metadata = array_merge([
                    'media_id' => $mediaId,
                    'filename' => basename($audioPath),
                ], $tracking['metadata_extra']);

                WhatsappMessage::create([
                    'business_profile_id' => $this->businessProfile ? $this->businessProfile->id : null,
                    'contact_id' => $contact->id,
                    'admin_user_id' => $tracking['admin_user_id'],
                    'message_id' => $messageId,
                    'content' => $caption ?? 'Audio enviado',
                    'type' => 'audio',
                    'status' => 'sent',
                    'sender_type' => $humanSent ? 'humano' : 'system',
                    'receiver_type' => 'client',
                    'metadata' => $metadata,
                ]);

                return true;
            }

            Log::error('WhatsApp API Error (Audio)', [
                'response' => $response->json(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'audio', 'Error al enviar audio: '.json_encode($response->json()), [], 'sendAudioMessage');

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error (Audio)', [
                'error' => $e->getMessage(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'audio', $e->getMessage(), [], 'sendAudioMessage');

            return false;
        }
    }

    public function sendDocumentMessage(WhatsappContact $contact, $documentPath, ?string $filename = null, ?string $caption = null, bool $humanSent = false)
    {
        try {
            if (! $this->businessProfile) {
                Log::error('No business profile found');

                return false;
            }

            if ($humanSent) {
                $this->sendTypingIndicatorForContact($contact);
            }

            // Usar el nombre del archivo proporcionado o el nombre del archivo subido
            $documentFilename = $filename ?? basename($documentPath);

            // Primero subir el documento a WhatsApp Media API
            $uploadResponse = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->attach('file', file_get_contents($documentPath), basename($documentPath))
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => mime_content_type($documentPath),
                ]);

            if (! $uploadResponse->successful()) {
                Log::error('WhatsApp Media Upload Error (Document)', [
                    'response' => $uploadResponse->json(),
                    'contact' => $contact->phone_number,
                ]);
                $this->recordSendFailure($contact, 'document', 'Error al subir el documento a WhatsApp: '.json_encode($uploadResponse->json()), ['filename' => $documentFilename], 'sendDocumentMessage');

                return false;
            }

            $mediaId = $uploadResponse->json()['id'];

            // Ahora enviar el mensaje con el documento
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone_number,
                'type' => 'document',
                'document' => [
                    'id' => $mediaId,
                    'filename' => $documentFilename,
                ],
            ];

            if ($caption) {
                $payload['document']['caption'] = $caption;
            }

            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->businessProfile->phone_number_id}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json()['messages'][0]['id'];

                $tracking = $this->humanTrackingPayload($humanSent);
                $metadata = array_merge([
                    'media_id' => $mediaId,
                    'filename' => $documentFilename,
                    'mime_type' => mime_content_type($documentPath),
                ], $tracking['metadata_extra']);

                WhatsappMessage::create([
                    'business_profile_id' => $this->businessProfile ? $this->businessProfile->id : null,
                    'contact_id' => $contact->id,
                    'admin_user_id' => $tracking['admin_user_id'],
                    'message_id' => $messageId,
                    'content' => $caption ?? $documentFilename,
                    'type' => 'document',
                    'status' => 'sent',
                    'sender_type' => $humanSent ? 'humano' : 'system',
                    'receiver_type' => 'client',
                    'metadata' => $metadata,
                ]);

                return true;
            }

            Log::error('WhatsApp API Error (Document)', [
                'response' => $response->json(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'document', 'Error al enviar documento: '.json_encode($response->json()), ['filename' => $documentFilename], 'sendDocumentMessage');

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error (Document)', [
                'error' => $e->getMessage(),
                'contact' => $contact->phone_number,
            ]);

            $this->recordSendFailure($contact, 'document', $e->getMessage(), ['filename' => $documentFilename ?? null], 'sendDocumentMessage');

            return false;
        }
    }

    protected function sendMessageToWhatsApp($to, $message)
    {
        try {
            $phoneNumberId = $this->resolvePhoneNumberId();
            if (! $phoneNumberId) {
                Log::error('No phone_number_id found for outbound message');

                return false;
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => $message['type'] ?? 'text',
            ];

            if ($payload['type'] === 'text') {
                $payload['text'] = [
                    'body' => $message['text']['body'],
                ];
            } elseif ($payload['type'] === 'image') {
                $payload['image'] = $message['image'] ?? [];
            } elseif ($payload['type'] === 'interactive') {
                $payload['interactive'] = $message['interactive'];
            } elseif ($payload['type'] === 'contacts') {
                if (isset($message['contacts'])) {
                    $payload['contacts'] = $this->formatContacts($message['contacts']);
                } else {
                    Log::error('Error sending message', [
                        'error' => 'Contacts data not found in message',
                        'to' => substr($to, 0, 4).'****'.substr($to, -4),
                        'type' => $payload['type'],
                    ]);

                    return false;
                }
            }

            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? null;

                Log::info('[sendMessageToWhatsApp] Message sent successfully', [
                    'to' => substr($to, 0, 4).'****'.substr($to, -4),
                    'message_id' => $messageId,
                    'type' => $payload['type'],
                    'phone_number_id' => $phoneNumberId,
                    'company_id' => $this->businessProfile?->company_id,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                ];
            }

            Log::error('Failed to send message', [
                'response' => $response->json(),
                'to' => substr($to, 0, 4).'****'.substr($to, -4),
                'type' => $payload['type'],
            ]);

            $this->recordSendFailure($to, $payload['type'], 'Meta respondió con error: '.json_encode($response->json()), [], 'sendMessageToWhatsApp');

            return false;
        } catch (\Exception $e) {
            Log::error('Error sending message', [
                'error' => $e->getMessage(),
                'to' => substr($to, 0, 4).'****'.substr($to, -4),
                'type' => $payload['type'] ?? 'text',
            ]);

            $this->recordSendFailure($to, $payload['type'] ?? 'text', $e->getMessage(), [], 'sendMessageToWhatsApp');

            return false;
        }
    }

    private function generateChatbotResponse(string $message, string $from): array
    {
        try {
            // Si el mensaje es un saludo, bienvenida (1 vez al día) + menú principal
            if ($this->isGreetingMessage($message)) {
                $contact = $this->findContactByPhone($from);

                return $this->handleGreetingMessage($from, $contact);
            }

            // Buscar respuesta específica en la base de datos
            $response = WhatsappChatbotResponse::where('keyword', strtolower($message))
                ->where('is_active', true)
                ->first();

            if ($response) {
                Log::info('[generateChatbotResponse] 🤖 Respuesta del chatbot encontrada', [
                    'keyword' => $response->keyword,
                    'tipo' => $response->type,
                    'show_menu' => $response->show_menu,
                ]);

                // Si la respuesta es de tipo contacts, enviar primero el contacto
                if ($response->type === 'contacts') {
                    // Intentar obtener el contacto desde la base de datos primero
                    $contactData = $this->getContactFromDatabase($response->keyword);

                    // Si no se encuentra en BD, usar el campo contacts del chatbot response como fallback
                    if (! $contactData && $response->contacts) {
                        $contactData = is_string($response->contacts)
                            ? $response->contacts
                            : (is_array($response->contacts) ? json_encode($response->contacts) : null);
                    }

                    if ($contactData) {
                        $this->sendMessageToWhatsApp($from, [
                            'type' => 'contacts',
                            'contacts' => $contactData,
                            'text' => [
                                'body' => $response->response,
                            ],
                        ]);
                    } else {
                        // Si no hay contacto disponible, enviar solo el mensaje de texto
                        $this->sendMessageToWhatsApp($from, [
                            'type' => 'text',
                            'text' => [
                                'body' => $response->response."\n\n⚠️ Contacto no disponible en este momento.",
                            ],
                        ]);
                    }

                    // Si debe mostrar menú, enviar el menú después
                    if ($response->show_menu) {
                        return $this->getMainMenu();
                    }

                    // Retornar null porque ya se envió el mensaje
                    return null;
                }

                // Si debe mostrar menú y es tipo text, enviar el menú con el texto como encabezado
                if ($response->show_menu && $response->type === 'text') {
                    return $this->getMainMenu($response->response);
                }

                return [
                    'type' => 'text',
                    'text' => ['body' => $response->response],
                ];
            }

            // Si no se encuentra respuesta específica, intentar con ChatGPT
            $config = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first();
            if ($config) {
                $chatGPT = new ChatGPTService($config);

                if ($chatGPT->isEnabled()) {
                    // El webhook no valida quién es realmente "from", así que un
                    // número (real o forjado) podría insistir con mensajes que no
                    // calzan ninguna respuesta fija y disparar una consulta a la
                    // API de OpenAI por cada uno. Se limita por contacto para
                    // evitar una factura de IA sin control.
                    $burstKey = 'chatgpt-burst:'.$from;
                    $dailyKey = 'chatgpt-daily:'.$from;

                    if (RateLimiter::tooManyAttempts($burstKey, 6) || RateLimiter::tooManyAttempts($dailyKey, 60)) {
                        Log::warning('[generateChatbotResponse] ⏳ Límite de consultas a ChatGPT alcanzado, se omite la llamada', [
                            'from' => substr($from, 0, 4).'****'.substr($from, -4),
                        ]);
                    } else {
                        RateLimiter::hit($burstKey, 300);
                        RateLimiter::hit($dailyKey, 86400);

                        try {
                            $aiResponse = $chatGPT->query($message);

                            if ($aiResponse) {
                                Log::info('[generateChatbotResponse] 🤖 Respuesta de ChatGPT', [
                                    'message' => $message,
                                    'response' => $aiResponse,
                                ]);

                                // Enviar el menú principal con la respuesta de ChatGPT como encabezado
                                return $this->getMainMenu($aiResponse);
                            }
                        } catch (\Exception $e) {
                            Log::error('[generateChatbotResponse] ❌ Error al consultar ChatGPT', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Si no hay respuesta de ChatGPT o falló, usar fallback configurado o menú principal
            $chatbotConfig = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first();
            $fallbackPayload = $this->buildMarketingStepPayload(MarketingStepKey::FALLBACK_MESSAGE);
            if ($fallbackPayload) {
                Log::info('[generateChatbotResponse] ℹ️ Enviando mensaje fallback del flujo');

                return $fallbackPayload;
            }

            $fallbackText = $chatbotConfig?->default_response;
            Log::info('[generateChatbotResponse] ℹ️ No se encontró respuesta específica, enviando menú principal');

            return $this->getMainMenu($fallbackText ?: null);

        } catch (\Exception $e) {
            Log::error('[generateChatbotResponse] ❌ Error', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return $this->getMainMenu();
        }
    }

    private function isGreetingMessage(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = preg_replace('/[!?.¡¿]+$/u', '', $normalized) ?? $normalized;

        $greetings = [
            'hola', 'hi', 'hello', 'buenas', 'buenos dias', 'buenas tardes',
            'buenas noches', 'inicio', 'start', 'menu', 'menú', 'hey', 'ola',
        ];

        $chatbotConfig = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first();
        if (is_array($chatbotConfig?->greetings)) {
            foreach ($chatbotConfig->greetings as $greeting) {
                $g = mb_strtolower(trim((string) $greeting));
                if ($g !== '') {
                    $greetings[] = $g;
                }
            }
        }

        $greetings = array_unique($greetings);

        return in_array($normalized, $greetings, true);
    }

    private function handleGreetingMessage(string $from, ?WhatsappContact $contact): array
    {
        if ($contact) {
            $this->maybeSendPrivacyNotice($contact);
        }

        $graphPayload = $this->resolveGraphStartPayload($contact);
        if ($graphPayload) {
            if ($contact) {
                $contact->markWelcomedToday();
            }

            return $graphPayload;
        }

        if ($contact?->wasWelcomedToday()) {
            Log::info('[handleGreetingMessage] Saludo repetido hoy, enviando solo menú principal', [
                'contact_id' => $contact->id,
            ]);

            return $this->getMainMenu(null, $contact);
        }

        if ($contact) {
            $contact->markWelcomedToday();
        }

        Log::info('[handleGreetingMessage] Primer saludo del día, bienvenida + menú en un solo mensaje');

        return $this->buildFirstGreetingMenu($contact);
    }

    /**
     * Le manda al cliente el aviso de protección de datos, una sola vez
     * (hasta que un admin reinicie su conversación, ver
     * AbandonedCartService::close). Se manda como mensaje aparte, antes del
     * saludo/menú, para que quede como el primer mensaje que ve.
     */
    private function maybeSendPrivacyNotice(WhatsappContact $contact): void
    {
        if ($contact->hasReceivedPrivacyNotice()) {
            return;
        }

        $config = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first()
            ?? WhatsappChatbotConfig::first();

        if (! ($config?->metadata['privacy_notice_enabled'] ?? false)) {
            return;
        }

        $text = trim((string) ($config->metadata['privacy_notice_text'] ?? ''));
        if ($text === '') {
            return;
        }

        $link = trim((string) ($config->metadata['privacy_notice_link'] ?? ''));
        $body = $text.($link !== '' ? "\n\n🔗 {$link}" : '');

        $this->sendBotPayload($contact, [
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        $contact->markPrivacyNoticeSent();
    }

    private function buildFirstGreetingMenu(?WhatsappContact $contact): array
    {
        $vars = $this->marketingFlowVariables($contact);
        $welcomeStep = $this->getMarketingStep(MarketingStepKey::WELCOME);

        $welcomeBody = '';
        if ($welcomeStep?->is_enabled) {
            $welcomeBody = trim($welcomeStep->renderMessage($vars));
        }

        if ($welcomeBody === '') {
            $chatbotConfig = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first();
            if ($chatbotConfig?->welcome_message) {
                $welcomeBody = trim(MarketingFlowStep::interpolate($chatbotConfig->welcome_message, $vars));
            }
        }

        $menu = $this->getMainMenu($welcomeBody !== '' ? $welcomeBody : null, $contact);

        if (($menu['type'] ?? '') !== 'interactive' || ! $welcomeStep?->is_enabled) {
            return $menu;
        }

        $welcomeHeader = $this->resolveWelcomeHeaderForMenu($welcomeStep, $vars);
        if ($welcomeHeader) {
            $menu['interactive']['header'] = $welcomeHeader;
        }

        return $menu;
    }

    private function resolveWelcomeHeaderForMenu(MarketingFlowStep $welcomeStep, array $vars): ?array
    {
        if ($welcomeStep->getHeaderMode() === 'image') {
            $header = $welcomeStep->getRenderedHeader($vars);

            return $header ? $this->resolveInteractiveHeader($header) : null;
        }

        if ($welcomeStep->getMessageImageUrl()) {
            return [
                'type' => 'image',
                'image' => ['link' => $welcomeStep->getMessageImageUrl()],
            ];
        }

        return null;
    }

    private function resolveInteractiveHeader(array $header): array
    {
        if (($header['type'] ?? '') === 'image' && ! empty($header['_image_path'])) {
            return [
                'type' => 'image',
                'image' => ['link' => asset('storage/'.ltrim($header['_image_path'], '/'))],
            ];
        }

        if (($header['type'] ?? '') === 'text' && ! empty($header['text'])) {
            return ['type' => 'text', 'text' => $header['text']];
        }

        return $header;
    }

    private function getMainMenu(?string $headerText = null, ?WhatsappContact $contact = null): array
    {
        $flowMenu = $this->buildMarketingStepPayload(MarketingStepKey::MAIN_MENU, $contact, $headerText);
        if ($flowMenu) {
            if (config('whatsapp.native_catalog_menu_enabled')
                && ($flowMenu['interactive']['type'] ?? null) === 'button') {
                $flowMenu['interactive']['action']['buttons'][] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'catalogo_whatsapp',
                        'title' => '📱 Catálogo WhatsApp',
                    ],
                ];
            }

            return $flowMenu;
        }
        $productosMenu = $this->menuByActionId('menu_productos');
        $pedidosMenu = $this->menuByActionId('menu_pedido');
        $infoMenu = $this->menuByActionId('menu_info');

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $headerText ?? '¿En qué más puedo ayudarte?',
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'menu_productos',
                                'title' => $productosMenu ? $productosMenu->button_text : '🛍️ Productos',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'menu_pedido',
                                'title' => $pedidosMenu ? $pedidosMenu->button_text : '📦 Ver Pedidos',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'menu_info',
                                'title' => $infoMenu ? $infoMenu->button_text : 'ℹ️ Información',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function prepareTemplateComponents(WhatsappTemplate $template, array $variables)
    {
        $components = [];
        $varIndex = 0;
        if (is_array($template->components)) {
            foreach ($template->components as $component) {
                $type = strtolower($component['type'] ?? '');
                if (in_array($type, ['header', 'body'])) {
                    $params = [];
                    $text = $component['text'] ?? '';
                    // Contar cuántos {{n}} hay en el texto
                    $varCount = substr_count($text, '{{');
                    for ($i = 0; $i < $varCount; $i++) {
                        $params[] = [
                            'type' => 'text',
                            'text' => $variables[$varIndex] ?? '',
                        ];
                        $varIndex++;
                    }
                    if ($varCount > 0) {
                        $components[] = [
                            'type' => $type,
                            'parameters' => $params,
                        ];
                    }
                }
            }
        }

        return $components;
    }

    protected function resolvePhoneNumberId(): ?string
    {
        return $this->webhookPhoneNumberId
            ?? $this->businessProfile?->phone_number_id
            ?? config('whatsapp.phone_number_id');
    }

    /**
     * Contacto del perfil activo (webhook). Evita mezclar el mismo teléfono entre bots del portafolio.
     */
    protected function findContactByPhone(string $phone): ?WhatsappContact
    {
        if ($this->businessProfile) {
            $scoped = WhatsappContact::where('phone_number', $phone)
                ->where('business_profile_id', $this->businessProfile->id)
                ->first();

            if ($scoped) {
                return $scoped;
            }

            if ($this->webhookPhoneNumberId) {
                return null;
            }
        }

        return WhatsappContact::where('phone_number', $phone)->first();
    }

    public function getBusinessProfile(): ?WhatsappBusinessProfile
    {
        return $this->businessProfile;
    }

    /**
     * Fija explícitamente el negocio activo (fuera del flujo de webhook,
     * donde ya se resuelve por setWebhookPhoneNumberId). Úsalo siempre que se
     * construya un WhatsappService para actuar sobre un contacto/carrito/
     * campaña ya conocido -- de lo contrario el servicio solo adopta un
     * perfil "por defecto" cuando es inequívoco (ver
     * resolveUnambiguousLegacyProfile(), llamado desde el constructor), que
     * en un sistema multiempresa con más de un perfil en la base es null.
     */

    /**
     * Rechaza (lanzando, no en silencio) un perfil ausente o no usable. Es
     * crítico que además LIMPIE $this->businessProfile antes de fallar: en
     * un job/comando que reutiliza una sola instancia para varios contactos
     * de distintas empresas (SendWhatsAppTemplate, PendingReplyRecoveryService,
     * etc.), un catch demasiado amplio en el llamador no debe terminar
     * enviando con las credenciales del contacto anterior.
     */
    public function useBusinessProfile(?WhatsappBusinessProfile $profile): void
    {
        $this->tenantResolutionAttempted = true;

        if (! $profile || ! $profile->isUsable()) {
            $this->businessProfile = null;
            $this->webhookProfileKnown = false;

            Log::warning('[useBusinessProfile] Perfil de WhatsApp ausente o no usable; se rechaza sin reutilizar el anterior.', [
                'business_profile_id' => $profile?->id,
                'status' => $profile?->status,
            ]);

            throw new WhatsappBusinessProfileUnavailableException(
                $profile
                    ? "El perfil de WhatsApp #{$profile->id} no está usable (status={$profile->status})."
                    : 'No se proporcionó ningún perfil de WhatsApp para este envío.'
            );
        }

        $this->businessProfile = $profile;
        $this->webhookProfileKnown = true;
    }

    public function setWebhookPhoneNumberId(?string $phoneNumberId): void
    {
        $this->webhookPhoneNumberId = $phoneNumberId ?: null;
        $this->inboundMarkedRead = false;

        if (! $phoneNumberId) {
            return;
        }

        // Igual que useBusinessProfile(): un número desconectado localmente
        // no debe volver a recibir/responder mensajes solo porque Meta siga
        // mandando el webhook a ese phone_number_id.
        $profile = WhatsappBusinessProfile::where('phone_number_id', $phoneNumberId)->usable()->first();

        if ($profile) {
            $this->businessProfile = $profile;
            $this->webhookProfileKnown = true;

            return;
        }

        $this->webhookProfileKnown = false;

        Log::warning('[setWebhookPhoneNumberId] Webhook de un número no registrado en whatsapp_business_profiles', [
            'phone_number_id' => $phoneNumberId,
            'perfil_por_defecto' => $this->businessProfile?->business_name,
            'hint' => 'Registre cada línea del portafolio Meta con su phone_number_id y flujo propio.',
        ]);
    }

    /**
     * Guarda el wamid entrante del webhook (para typing del panel humano y respuestas del bot).
     */
    protected function rememberInboundFromWebhook(array $message): void
    {
        $contact = $this->findContactByPhone($message['from']);

        if ($contact && ! empty($message['id'])) {
            $this->rememberInboundMessage($contact, $message['id']);
        }
    }

    protected function markMessageAsRead($messageId, $to)
    {
        try {
            if (! $messageId || ! $to) {
                Log::warning('⚠️ No se puede marcar como leído', [
                    'tiene_id' => ! empty($messageId),
                    'tiene_destino' => ! empty($to),
                ]);

                return false;
            }

            $phoneNumberId = $this->resolvePhoneNumberId();
            if (! $phoneNumberId) {
                return false;
            }

            $response = Http::withToken($this->apiToken())
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);

            if ($response->successful()) {
                Log::info('[markMessageAsRead] ✅ Mensaje marcado como leído', [
                    'id' => $messageId,
                    'para' => substr($to, 0, 4).'****'.substr($to, -4),
                ]);

                return true;
            }

            Log::error('❌ Error al marcar como leído', [
                'id' => $messageId,
                'error' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('❌ Error en markMessageAsRead', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return false;
        }
    }

    /**
     * Muestra "escribiendo..." en el WhatsApp del cliente (marca el mensaje como leído).
     * Requiere el message_id (wamid) de un mensaje entrante del cliente.
     */
    public function sendTypingIndicator(string $whatsappMessageId): bool
    {
        try {
            if (! config('whatsapp.typing_indicator_enabled', true)) {
                return false;
            }

            $phoneNumberId = $this->resolvePhoneNumberId();
            if (! $whatsappMessageId || ! $phoneNumberId) {
                return false;
            }

            $response = Http::withToken($this->apiToken())
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $whatsappMessageId,
                    'typing_indicator' => [
                        'type' => 'text',
                    ],
                ]);

            if ($response->successful()) {
                $this->inboundMarkedRead = true;
                Log::info('[sendTypingIndicator] Indicador de escritura enviado', [
                    'message_id' => substr($whatsappMessageId, 0, 24).'...',
                    'phone_number_id' => $phoneNumberId,
                ]);

                return true;
            }

            Log::warning('[sendTypingIndicator] No se pudo enviar', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('[sendTypingIndicator] Error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Guarda el wamid del último mensaje entrante (válido ~24 h para typing).
     */
    public function rememberInboundMessage(WhatsappContact $contact, string $whatsappMessageId): void
    {
        if (! $whatsappMessageId) {
            return;
        }

        $contact->forceFill([
            'last_inbound_message_id' => $whatsappMessageId,
            'last_inbound_at' => now(),
        ])->save();
    }

    /**
     * Resuelve un message_id entrante reciente y válido para el indicador de escritura.
     */
    public function resolveInboundMessageId(WhatsappContact $contact): ?string
    {
        if (
            $contact->last_inbound_message_id
            && $contact->last_inbound_at
            && $contact->last_inbound_at->gte(now()->subHours(24))
        ) {
            return $contact->last_inbound_message_id;
        }

        $recent = WhatsappMessage::where('contact_id', $contact->id)
            ->where('sender_type', 'client')
            ->whereNotNull('message_id')
            ->where('message_id', '!=', '')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('created_at')
            ->value('message_id');

        return $recent ?: null;
    }

    /**
     * Sincroniza last_inbound desde el último mensaje del cliente (<24 h).
     */
    public function syncContactLastInbound(WhatsappContact $contact): ?string
    {
        $recent = WhatsappMessage::where('contact_id', $contact->id)
            ->where('sender_type', 'client')
            ->whereNotNull('message_id')
            ->where('message_id', '!=', '')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('created_at')
            ->first();

        if ($recent) {
            $this->rememberInboundMessage($contact, $recent->message_id);

            return $recent->message_id;
        }

        return $this->resolveInboundMessageId($contact);
    }

    /**
     * Busca el último mensaje del cliente (últimas 24 h) y envía el indicador de escritura.
     */
    public function sendTypingIndicatorForContact(WhatsappContact $contact): bool
    {
        $messageId = $this->syncContactLastInbound($contact);

        if (! $messageId) {
            Log::debug('[sendTypingIndicatorForContact] Sin mensaje entrante reciente (<24h)', [
                'contact_id' => $contact->id,
            ]);

            return false;
        }

        return $this->sendTypingIndicator($messageId);
    }

    /**
     * Muestra "escribiendo..." justo antes de enviar la respuesta del bot (mismo flujo que el panel humano).
     */
    protected function prepareBotReply(WhatsappContact $contact, ?string $inboundMessageId = null): void
    {
        $contact->refresh();

        if (! $this->botMayRespondToContact($contact)) {
            return;
        }

        $messageId = $inboundMessageId ?: $this->resolveInboundMessageId($contact);
        if (! $messageId) {
            Log::warning('[prepareBotReply] Sin wamid entrante reciente para typing', [
                'contact_id' => $contact->id,
            ]);

            return;
        }

        // Siempre enviar typing aquí (no omitir si ya se marcó como leído)
        if (! $this->sendTypingIndicator($messageId)) {
            Log::warning('[prepareBotReply] No se pudo mostrar typing antes de responder', [
                'contact_id' => $contact->id,
            ]);

            return;
        }

        $delayMs = max((int) $this->getBotResponseDelayMs(), 500);
        usleep($delayMs * 1000);
    }

    protected function getBotResponseDelayMs(): int
    {
        $config = $this->businessProfile
            ? WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first()
            : WhatsappChatbotConfig::first();

        if ($config && $config->response_delay > 0) {
            return $config->response_delay;
        }

        return (int) config('whatsapp.bot_reply_delay_ms', 2500);
    }

    private function handleInteractiveMessage($data)
    {
        try {
            $from = $data['from'];
            $interactive = $data['interactive'];
            $messageId = $data['id'];

            Log::info('[handleInteractiveMessage] 📥 Mensaje interactivo recibido', [
                'de' => substr($from, 0, 4).'****'.substr($from, -4),
                'tipo' => 'interactive',
                'id' => $messageId,
                'contenido' => $interactive,
            ]);

            // Bug real: sin escopar por negocio, un número que ya chateó con
            // otra empresa (mismo teléfono, otro business_profile_id) traía
            // ese contacto viejo -- el pedido terminaba armado sobre el
            // negocio equivocado y hasta se mandaba con las credenciales de
            // otra empresa (ver useBusinessProfile($contact->businessProfile)
            // en OrderLifecycleService/BulkOrderService).
            $contact = $this->findContactByPhone($from);
            if (! $contact) {
                // Obtener datos del contacto del webhook
                $contactData = $message['contacts'][0] ?? [];
                $profile = $contactData['profile'] ?? [];
                $contactName = $profile['name'] ?? 'Contacto sin nombre';

                // Crear el contacto automáticamente
                $contact = WhatsappContact::create([
                    'business_profile_id' => $this->businessProfile->id,
                    'phone_number' => $from,
                    'name' => $contactName,
                    'status' => 'active',
                ]);

                Log::info('✅ Nuevo contacto creado', [
                    'phone' => $from,
                    'contact_id' => $contact->id,
                    'name' => $contactName,
                ]);
            } elseif ($contact->name === 'Contacto sin nombre') {
                // Si el contacto existe pero tiene nombre genérico, intentar actualizarlo
                $contactData = $message['contacts'][0] ?? [];
                $profile = $contactData['profile'] ?? [];
                $contactName = $profile['name'] ?? null;

                if ($contactName && $contactName !== 'Contacto sin nombre') {
                    $contact->name = $contactName;
                    $contact->save();

                    Log::info('✅ Nombre de contacto actualizado', [
                        'phone' => $from,
                        'contact_id' => $contact->id,
                        'old_name' => 'Contacto sin nombre',
                        'new_name' => $contactName,
                    ]);
                }
            }

            // Guardar el mensaje
            $type = $interactive['type'] ?? 'button_reply';
            $content = $type === 'button_reply'
                ? ($interactive['button_reply'] ?? [])
                : ($interactive['list_reply'] ?? []);
            $buttonId = $content['id'] ?? null;
            $buttonTitle = $content['title'] ?? 'Respuesta interactiva';

            $whatsappMessage = WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'content' => $buttonTitle,
                'type' => 'interactive',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'metadata' => [
                    'interactive' => $interactive,
                    'reply_id' => $buttonId,
                    'reply_type' => $type,
                ],
            ]);

            $this->lastMessage = $whatsappMessage;
            $this->rememberInboundMessage($contact, $messageId);

            // Refrescar el contacto desde la base de datos para obtener el valor actualizado de bot_enabled
            $contact->refresh();

            // Verificar si el bot está habilitado para este contacto ANTES de procesar cualquier respuesta
            if (! $this->botMayRespondToContact($contact)) {
                $this->markMessageAsRead($messageId, $from);
                $this->logBotBlocked('handleInteractiveMessage', $contact, [
                    'phone' => substr($from, 0, 4).'****'.substr($from, -4),
                ]);

                return; // No procesar ninguna respuesta automática
            }

            // Un WhatsApp Flow terminado llega como nfm_reply. A diferencia de
            // botones/listas, su respuesta es un JSON con los datos del pedido.
            if ($type === 'nfm_reply') {
                $this->handleNativeFlowReply($contact, $from, $interactive, $messageId);

                return;
            }

            // Si el bot está activado manualmente, NO verificar actividad humana reciente
            // El bot funcionará inmediatamente cuando esté activado

            // Determinar el tipo de respuesta interactiva
            $type = $interactive['type'] ?? 'button_reply';
            $content = $type === 'button_reply'
                ? ($interactive['button_reply'] ?? [])
                : ($interactive['list_reply'] ?? []);
            $buttonId = $content['id'] ?? null;
            $buttonTitle = $content['title'] ?? '';

            Log::info('[handleInteractiveMessage] Botón presionado', [
                'id' => $buttonId,
                'titulo' => $buttonTitle,
            ]);

            if ($buttonId && $this->tryHandleGraphButton($buttonId, $contact, $from, $messageId)) {
                return;
            }

            $inlineFlowResponse = $this->resolveFlowInlineResponse($buttonId, $contact);
            if ($inlineFlowResponse) {
                $this->sendMessage($from, $inlineFlowResponse);
                usleep(500000);
                $this->sendMessage($from, $this->getMainMenu(null, $contact));

                return;
            }

            $flowAction = $this->resolveFlowButtonAction($buttonId);
            if ($flowAction === MarketingButtonAction::AGENT || $this->isAgentRequestButton($buttonId, $buttonTitle)) {
                $this->triggerAgentHandoff($contact, $from, 'button:'.$buttonId);

                return;
            }

            $buttonId = match ($flowAction) {
                MarketingButtonAction::PRODUCTS => 'menu_productos',
                MarketingButtonAction::ORDERS => 'menu_pedido',
                MarketingButtonAction::INFO => 'menu_info',
                MarketingButtonAction::MAIN_MENU => 'menu_principal',
                MarketingButtonAction::VIEW_CART => 'ver_carrito',
                MarketingButtonAction::CHECKOUT => 'checkout',
                MarketingButtonAction::CATALOG => 'menu_productos',
                default => $buttonId,
            };

            $response = null;

            switch ($buttonId) {
                // Menús principales
                case 'menu_productos':
                case 'productos':
                    $response = $this->getProductsMenu($contact);
                    break;
                case 'menu_pedido':
                    $response = $this->getOrderMenu();
                    break;
                case 'menu_info':
                    $response = $this->getInfoMenu();
                    break;
                case 'menu_principal':
                case 'return_to_menu':  // Agregado el caso para el botón de retorno
                    $response = $this->getMainMenu(null, $contact);
                    break;

                    // Información
                case 'horarios':
                case 'contacto':
                case 'envios':
                case 'pagos':
                case 'asesoria':
                case 'redes':  // Agregado el caso para redes sociales
                    // Buscar la respuesta en la base de datos
                    $chatbotResponse = WhatsappChatbotResponse::where('keyword', $buttonId)
                        ->where('is_active', true)
                        ->first();

                    if ($chatbotResponse) {
                        if ($chatbotResponse->type === 'contacts') {
                            // Intentar obtener el contacto desde la base de datos primero
                            $contactData = $this->getContactFromDatabase($buttonId);

                            // Si no se encuentra en BD, usar el campo contacts del chatbot response como fallback
                            if (! $contactData && $chatbotResponse->contacts) {
                                $contactData = is_string($chatbotResponse->contacts)
                                    ? $chatbotResponse->contacts
                                    : (is_array($chatbotResponse->contacts) ? json_encode($chatbotResponse->contacts) : null);
                            }

                            if ($contactData) {
                                // Enviar el contacto con formato de tarjeta
                                $this->sendMessage($from, [
                                    'type' => 'contacts',
                                    'contacts' => $contactData,
                                    'text' => ['body' => $chatbotResponse->response],
                                ]);
                            } else {
                                // Si no hay contacto disponible, enviar solo el mensaje de texto
                                $this->sendMessage($from, [
                                    'type' => 'text',
                                    'text' => ['body' => $chatbotResponse->response."\n\n⚠️ Contacto no disponible en este momento."],
                                ]);
                            }
                        } else {
                            $this->sendMessage($from, [
                                'type' => 'text',
                                'text' => ['body' => $chatbotResponse->response],
                            ]);
                        }

                        usleep(500000);
                        $response = $this->getMainMenu(null, $contact);
                    } else {
                        $response = [
                            'type' => 'text',
                            'text' => ['body' => 'Lo siento, esta información no está disponible en este momento.'],
                        ];
                    }
                    break;

                case 'soporte':
                    // Buscar la respuesta en la base de datos
                    $chatbotResponse = WhatsappChatbotResponse::where('keyword', $buttonId)
                        ->where('is_active', true)
                        ->first();

                    if ($chatbotResponse) {
                        if ($chatbotResponse->type === 'contacts') {
                            // Intentar obtener el contacto desde la base de datos primero
                            $contactData = $this->getContactFromDatabase($buttonId);

                            // Si no se encuentra en BD, usar el campo contacts del chatbot response como fallback
                            if (! $contactData && $chatbotResponse->contacts) {
                                $contactData = is_string($chatbotResponse->contacts)
                                    ? $chatbotResponse->contacts
                                    : (is_array($chatbotResponse->contacts) ? json_encode($chatbotResponse->contacts) : null);
                            }

                            if ($contactData) {
                                // Enviar el contacto con formato de tarjeta
                                $this->sendMessage($from, [
                                    'type' => 'contacts',
                                    'contacts' => $contactData,
                                    'text' => ['body' => $chatbotResponse->response],
                                ]);
                            } else {
                                // Si no hay contacto disponible, enviar solo el mensaje de texto
                                $this->sendMessage($from, [
                                    'type' => 'text',
                                    'text' => ['body' => $chatbotResponse->response."\n\n⚠️ Contacto no disponible en este momento."],
                                ]);
                            }

                            // Esperar un momento para asegurar que el contacto se envíe primero
                            usleep(500000);

                            $response = $this->getMainMenu(null, $contact);
                        } else {
                            $this->sendMessage($from, [
                                'type' => 'text',
                                'text' => ['body' => $chatbotResponse->response],
                            ]);
                        }
                    }
                    break;
                case 'ventas':
                    $response = WhatsappChatbotResponse::where('keyword', 'ventas')->first();
                    if ($response && $response->type === 'contacts') {
                        $this->sendMessageToWhatsApp($from, [
                            'type' => 'contacts',
                            'contacts' => $response->contacts, // <-- Solo el string plano
                        ]);
                    }
                    // Obtener opciones del menú
                    $menuOptions = WhatsappChatbotResponse::where('is_active', true)
                        ->where('show_menu', true)
                        ->orderBy('order')
                        ->get();
                    break;

                case 'faq':
                    // Buscar la respuesta en la base de datos
                    $chatbotResponse = WhatsappChatbotResponse::where('keyword', $buttonId)
                        ->where('is_active', true)
                        ->first();

                    if ($chatbotResponse) {
                        // Enviar la respuesta de texto
                        $this->sendMessage($from, [
                            'type' => 'text',
                            'text' => ['body' => $chatbotResponse->response],
                        ]);

                        usleep(500000);
                        $response = $this->getMainMenu(null, $contact);
                    } else {
                        $response = [
                            'type' => 'text',
                            'text' => ['body' => 'Lo siento, esta información no está disponible en este momento.'],
                        ];
                    }
                    break;

                    // Navegación de productos
                case 'ver_mas_precios':
                    // Nunca enviamos al cliente un listado técnico con SKU.
                    // Las categorías hacen el menú más corto, visual y fácil de recorrer.
                    $response = $this->buildCategoryBrowserResponse($contact);
                    break;
                case 'volver_productos':
                    $response = $this->getProductsMenu($contact);
                    break;
                case 'volver_categorias':
                    $response = $this->getProductsMenu($contact);
                    break;
                case 'seguir_comprando':
                    $response = $this->getProductsMenu($contact);
                    break;

                    // Carrito y compras
                case 'ver_carrito':
                    $response = $this->getCartContents($contact);
                    break;
                case 'finalizar_compra':
                case 'checkout':
                    $response = $this->finalizarCompra($contact);
                    break;
                case 'bulk_order_web':
                    $response = $this->sendBulkWebOrderLink($contact);
                    break;
                case 'catalogo_whatsapp':
                    $response = $this->sendNativeCatalogMenu($contact);
                    break;

                    // Procesamiento de imagen
                case 'cancelar_proceso_imagen':
                    $response = $this->processImageMessage($contact);
                    break;
                case 'continuar_proceso':
                    $response = $this->resumeActiveProcessPrompt($contact);
                    break;

                    // Acciones de productos
                default:
                    if (preg_match('/^ver_mas_cat_(\d+)_(\d+)$/', (string) $buttonId, $verMasMatch)) {
                        // La lista nativa de WhatsApp permite un máximo de 10 filas.
                        // Si una categoría crece, mostramos su navegación limpia en lugar
                        // de forzar al cliente a copiar o escribir códigos SKU.
                        $response = $this->buildCategoryBrowserResponse($contact);
                        break;
                    }

                    if (str_starts_with((string) $buttonId, 'cat_')) {
                        $categoryId = (int) substr((string) $buttonId, 4);
                        Log::info('📂 Categoría seleccionada del catálogo', ['category_id' => $categoryId]);
                        $response = $this->getProductsMenu($contact, $categoryId > 0 ? $categoryId : null);
                        break;
                    }

                    // Si es un número simple, es una selección del menú interactivo
                    if (is_numeric($buttonId)) {
                        Log::info('🔍 Producto seleccionado del menú', ['id' => $buttonId]);
                        $response = $this->getProductDetails(intval($buttonId), $contact);
                    } elseif (strpos($buttonId, 'comprar_') === 0) {
                        $parts = explode('_', $buttonId);
                        $productId = intval($parts[1]);
                        Log::info('🛒 Compra directa de producto', ['id' => $productId, 'button_id' => $buttonId]);
                        $response = $this->addToCart($contact, $productId, 1);
                    } elseif (strpos($buttonId, 'quick_add_') === 0) {
                        // Compra de un toque: el cliente ya escogió el producto
                        // y, si aplica, su variación. Agregamos 1 unidad sin
                        // abrir otro formulario ni pedir la cantidad otra vez.
                        [, , $productId, $variationKey] = array_pad(explode('_', $buttonId, 4), 4, null);
                        $variationIndex = $variationKey !== null && $variationKey !== 'base'
                            ? (int) $variationKey
                            : null;
                        $response = $this->addToCart($contact, (int) $productId, 1, $variationIndex);
                    } elseif (strpos($buttonId, 'pedir_cantidad_') === 0) {
                        // Producto configurado con "permitir elegir cantidad":
                        // en vez de agregar 1 unidad de una vez, pregunta
                        // cuántas quiere, igual que en el formulario web.
                        [, , $productId, $variationKey] = array_pad(explode('_', $buttonId, 4), 4, null);
                        $variationIndex = $variationKey !== null && $variationKey !== 'base'
                            ? (int) $variationKey
                            : null;
                        $response = $this->showQuantitySelection($contact, (int) $productId, $variationIndex);
                    } elseif (strpos($buttonId, 'pedido_express_') === 0) {
                        $productId = (int) str_replace('pedido_express_', '', $buttonId);
                        $response = $this->buildQuickOrderFlowPayload($productId, $contact);
                    } elseif (strpos($buttonId, 'personalizar_') === 0) {
                        $productId = (int) str_replace('personalizar_', '', $buttonId);
                        $response = $this->showVariationSelection($productId, $contact);
                    } elseif (strpos($buttonId, 'variacion_') === 0) {
                        [, $productId, $variationIndex] = explode('_', $buttonId, 3);
                        $productId = (int) $productId;
                        $variationIndex = (int) $variationIndex;
                        $variantProduct = $this->findCatalogProduct($productId);
                        if ($variantProduct && $variantProduct->allow_quantity_selection) {
                            // El producto permite elegir cantidad: preguntamos
                            // cuántas unidades antes de agregar al carrito.
                            $response = $this->showQuantitySelection($contact, $productId, $variationIndex);
                        } else {
                            // Elegir una variante ya es una decisión suficiente:
                            // agregamos una unidad y evitamos el paso redundante.
                            $response = $this->addToCart($contact, $productId, 1, $variationIndex);
                        }
                    } elseif (strpos($buttonId, 'ver_producto_') === 0) {
                        $parts = explode('_', $buttonId);
                        $productId = intval($parts[2]);
                        Log::info('🔍 Buscando producto por ID', ['id' => $productId, 'button_id' => $buttonId]);
                        $response = $this->getProductDetails($productId, $contact);
                    } elseif (strpos($buttonId, 'producto_') === 0) {
                        $parts = explode('_', $buttonId);
                        $productId = intval($parts[1]);
                        Log::info('🔍 Buscando producto por ID', ['id' => $productId, 'button_id' => $buttonId]);
                        $response = $this->getProductDetails($productId, $contact);
                    } elseif (strpos($buttonId, 'otra_cantidad_') === 0) {
                        // El cliente quiere escribir una cantidad que no está
                        // en la lista: guardamos qué producto/variación está
                        // eligiendo y esperamos su próximo mensaje de texto.
                        [, , $productId, $variationKey] = array_pad(explode('_', $buttonId, 4), 4, null);
                        $metadata = $contact->metadata ?? [];
                        $metadata['pending_custom_quantity'] = [
                            'product_id' => (int) $productId,
                            'variation_index' => $variationKey !== null ? (int) $variationKey : null,
                            'requested_at' => now()->toIso8601String(),
                        ];
                        $contact->metadata = $metadata;
                        $contact->save();
                        $response = [
                            'type' => 'text',
                            'text' => ['body' => '✍️ Escribe la cantidad que deseas agregar (entre 1 y 49).'],
                        ];
                    } elseif (strpos($buttonId, 'cantidad_') === 0) {
                        $parts = explode('_', $buttonId);
                        $quantity = intval($parts[1]);
                        $productId = intval($parts[2]);
                        Log::info('📦 Agregando al carrito', ['product_id' => $productId, 'quantity' => $quantity]);
                        $response = $this->addToCart($contact, $productId, $quantity, isset($parts[3]) ? (int) $parts[3] : null);
                    } elseif (strpos($buttonId, 'agregar_') === 0) {
                        $parts = explode('_', $buttonId);
                        $productId = intval($parts[2]);
                        $quantity = intval($parts[3]);
                        $response = $this->addToCart($contact, $productId, $quantity);
                    } elseif (preg_match('/^sucursal_mantener_(\d+)$/', (string) $buttonId, $sucursalMatch)) {
                        $response = $this->confirmarSucursalMantenida($contact, (int) $sucursalMatch[1]);
                    } elseif (preg_match('/^sucursal_cambiar_(\d+)$/', (string) $buttonId, $sucursalMatch)) {
                        $cartParaCambiar = WhatsappCart::where('id', (int) $sucursalMatch[1])->where('contact_id', $contact->id)->first();
                        $response = $cartParaCambiar
                            ? $this->buildSucursalList($cartParaCambiar)
                            : ['type' => 'text', 'text' => ['body' => 'Lo siento, no se encontró el pedido.']];
                    } elseif (preg_match('/^sucursal_set_(\d+)_(\d+)$/', (string) $buttonId, $sucursalMatch)) {
                        $response = $this->setSucursalPedido($contact, (int) $sucursalMatch[1], (int) $sucursalMatch[2]);
                    } elseif (preg_match('/^tipo_llevar_(\d+)$/', (string) $buttonId, $tipoMatch)) {
                        $response = $this->setTipoServicio($contact, (int) $tipoMatch[1], 'llevar');
                    } elseif (preg_match('/^tipo_servir_(\d+)$/', (string) $buttonId, $tipoMatch)) {
                        $response = $this->setTipoServicio($contact, (int) $tipoMatch[1], 'servir');
                    } elseif (preg_match('/^retiro_local_(\d+)$/', (string) $buttonId, $retiroMatch)) {
                        $response = $this->setModoRetiro($contact, (int) $retiroMatch[1], 'retiro');
                    } elseif (preg_match('/^retiro_delivery_(\d+)$/', (string) $buttonId, $retiroMatch)) {
                        $response = $this->setModoRetiro($contact, (int) $retiroMatch[1], 'delivery');
                    } elseif (preg_match('/^nota_omitir_(\d+)$/', (string) $buttonId, $notaMatch)) {
                        $response = $this->skipOrderNote($contact, (int) $notaMatch[1]);
                    } elseif (strpos($buttonId, 'confirmar_pedido_') === 0) {
                        $parts = explode('_', $buttonId);
                        $cartId = intval($parts[2]);
                        $response = $this->confirmarPedido($contact, $cartId);
                    } elseif (strpos($buttonId, 'modificar_pedido_') === 0) {
                        $parts = explode('_', $buttonId);
                        $cartId = intval($parts[2]);
                        $response = $this->modificarPedido($contact, $cartId);
                    } elseif (strpos($buttonId, 'cancelar_pedido_') === 0) {
                        $parts = explode('_', $buttonId);
                        $cartId = intval($parts[2]);
                        $response = $this->cancelarPedido($contact, $cartId);
                    } elseif (strpos($buttonId, 'pago_transferencia_') === 0) {
                        $parts = explode('_', $buttonId);
                        $cartId = intval($parts[2]);
                        $response = $this->procesarPagoTransferencia($contact, $cartId);
                    } elseif (strpos($buttonId, 'pago_efectivo_') === 0) {
                        $parts = explode('_', $buttonId);
                        $cartId = intval($parts[2]);
                        $response = $this->procesarPagoEfectivo($contact, $cartId);
                    } elseif (preg_match('/^pago_tarjeta_(\d+)$/', $buttonId, $matches)) {
                        $cartId = $matches[1];
                        $response = $this->procesarPagoTarjeta($contact, $cartId);
                    } elseif ($buttonId === 'enviar_comprobante_menu') {
                        $response = $this->buildPaymentProofOrderList($contact);
                    } elseif (preg_match('/^enviar_comprobante_(\d+)$/', $buttonId, $matches)) {
                        $response = $this->iniciarEnvioComprobante($contact, (int) $matches[1]);
                    } elseif ($buttonId === 'ver_instrucciones_pago') {
                        $response = $this->buildMarketingStepPayload(MarketingStepKey::CHECKOUT, $contact)
                            ?? $this->getMainMenu(null, $contact);
                    } elseif ($buttonId === 'recipient_name_self' || $buttonId === 'recipient_name_other') {
                        $cartForRecipient = WhatsappCart::where('contact_id', $contact->id)
                            ->where('status', 'active')
                            ->first();

                        if (! $cartForRecipient || empty($cartForRecipient->metadata['awaiting_delivery_recipient_name'] ?? false)) {
                            $response = $this->getMainMenu(null, $contact);
                        } elseif ($buttonId === 'recipient_name_self') {
                            $selfName = trim((string) $contact->name) !== '' ? $contact->name : 'Cliente';
                            $response = $this->applyDeliveryRecipientName($contact, $cartForRecipient, $selfName);
                        } else {
                            $response = [
                                'type' => 'text',
                                'text' => ['body' => '✍️ Escribe el nombre de quien recibe el pedido.'],
                            ];
                        }
                    } else {
                        // Ningún patrón conoce este button_id (versión vieja de un
                        // flujo republicado, id corrupto, etc.): antes esto dejaba
                        // al bot en silencio total. Se responde con el menú para
                        // que el cliente nunca quede sin salida.
                        Log::warning('⚠️ button_id no reconocido por ningún patrón', ['button_id' => $buttonId]);
                        $response = $this->getMainMenu('🙏 No reconocí esa opción. Aquí tienes el menú:', $contact);
                    }
                    break;
            }

            if ($response) {
                $this->prepareBotReply($contact, $messageId);
                $this->sendMessage($from, $response);
            } else {
                $this->markMessageAsRead($messageId, $from);
                Log::warning('⚠️ No se encontró respuesta para el botón', [
                    'button_id' => $buttonId,
                    'button_title' => $buttonTitle,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Error al procesar mensaje interactivo', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'mensaje' => $data,
            ]);
        }
    }

    private function processImageMessage(WhatsappContact $contact)
    {
        try {
            // Obtener el último mensaje de imagen del contacto
            $lastImageMessage = WhatsappMessage::where('contact_id', $contact->id)
                ->where('type', 'image')
                ->latest()
                ->first();

            if (! $lastImageMessage) {
                return [
                    'type' => 'text',
                    'text' => [
                        'body' => '❌ No se encontró ninguna imagen para procesar. Por favor, envía una imagen primero.',
                    ],
                ];
            }

            // Aquí puedes agregar la lógica para procesar la imagen
            // Por ejemplo, análisis de imagen, OCR, etc.

            // Limpiar el proceso activo
            $this->clearActiveProcess($contact);

            return [
                'type' => 'text',
                'text' => [
                    'body' => '✅ Imagen procesada correctamente. ¿Qué más puedo hacer por ti?',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error al procesar imagen: '.$e->getMessage());

            return [
                'type' => 'text',
                'text' => [
                    'body' => '❌ Lo siento, hubo un error al procesar la imagen. Por favor, intenta de nuevo.',
                ],
            ];
        }
    }

    /**
     * Responde al botón "⏳ Continuar proceso": en vez de dejar al bot en
     * silencio (bug previo), reenvía el recordatorio de lo que se está
     * esperando para que el cliente sepa exactamente cómo seguir.
     */
    private function resumeActiveProcessPrompt(WhatsappContact $contact): array
    {
        $cart = WhatsappCart::where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        if ($cart && ($cart->metadata['pending_note'] ?? false)) {
            return [
                'type' => 'text',
                'text' => ['body' => "📝 Seguimos esperando la nota de tu pedido. Escríbela ahora, o escribe 'sin nota' si prefieres continuar sin agregar una."],
            ];
        }

        $proofCart = $this->findCartPendingProofUpload($contact);
        if ($proofCart) {
            $orderNumber = $proofCart->getOrderNumber();

            return [
                'type' => 'text',
                'text' => ['body' => "🕐 Seguimos esperando el comprobante de pago de tu pedido *{$orderNumber}*. Envía la imagen o PDF cuando lo tengas."],
            ];
        }

        return [
            'type' => 'text',
            'text' => ['body' => '👍 Perfecto, continúa cuando quieras.'],
        ];
    }

    private function clearActiveProcess(WhatsappContact $contact)
    {
        // Limpiar carrito activo con nota pendiente
        $cart = WhatsappCart::where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            $metadata = $cart->metadata ?? [];
            unset($metadata['pending_note']);
            $cart->metadata = $metadata;
            $cart->save();
        }

        $proofCart = $this->findCartPendingProofUpload($contact);
        if ($proofCart) {
            $metadata = $proofCart->metadata ?? [];
            unset($metadata['pending_payment_proof']);
            $proofCart->metadata = $metadata;
            $proofCart->save();
        }
    }

    private function getCartContents($contact)
    {
        $cart = WhatsappCart::where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => 'Tu carrito está vacío. ¿Qué te gustaría comprar?'],
                    'action' => ['buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'productos',
                                'title' => '🛍️ Ver productos',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'menu_principal',
                                'title' => '🏠 Menú principal',
                            ],
                        ],
                    ]],
                ],
            ];
        }

        $message = "🛒 *Tu Carrito*\n\n";

        foreach ($cart->items as $item) {
            $price = $item->price;
            $subtotal = $price * $item->quantity;
            $message .= "• {$item->name}\n";
            $message .= "  Cantidad: {$item->quantity}\n";
            $message .= "  Precio: \${$price}\n";
            $message .= "  Subtotal: \${$subtotal}\n\n";
        }

        $message .= '¿Qué deseas hacer?';

        $buttons = [];
        $bulkAvailable = app(BulkOrderService::class)->isAvailable();

        if ($bulkAvailable) {
            $buttons = [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'seguir_comprando',
                        'title' => '🛍️ Seguir comprando',
                    ],
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'bulk_order_web',
                        'title' => '📋 Armar lista',
                    ],
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'finalizar_compra',
                        'title' => '✅ Finalizar compra',
                    ],
                ],
            ];
        } else {
            $buttons = [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'seguir_comprando',
                        'title' => '🛍️ Seguir comprando',
                    ],
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'finalizar_compra',
                        'title' => '✅ Finalizar compra',
                    ],
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'menu_principal',
                        'title' => '🏠 Menú principal',
                    ],
                ],
            ];
        }

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $message,
                ],
                'action' => [
                    'buttons' => $buttons,
                ],
            ],
        ];
    }

    private function sendBulkWebOrderLink(WhatsappContact $contact): array
    {
        $bulkService = app(BulkOrderService::class);

        if (! $bulkService->isAvailable()) {
            return [
                'type' => 'text',
                'text' => ['body' => 'Esta opción no está disponible en tu plan actual.'],
            ];
        }

        $token = $bulkService->issueToken($contact);
        if (! $token) {
            return [
                'type' => 'text',
                'text' => ['body' => 'No pudimos generar el enlace. Intenta de nuevo en unos minutos.'],
            ];
        }

        $url = $bulkService->formUrl($token);

        $businessLabel = $this->scopedChatbotConfig()?->bot_name ?: ($this->businessProfile?->business_name ?: 'nosotros');

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'cta_url',
                'body' => [
                    'text' => "🛒 *Pide con {$businessLabel} a tu ritmo*\n\n"
                        ."Mira el menú, personaliza tus favoritos y revisa tu carrito en una sola pantalla.\n"
                        .'Al confirmar, tu pedido llega al equipo y te respondemos por este chat.',
                ],
                'action' => [
                    'name' => 'cta_url',
                    'parameters' => [
                        'display_text' => 'Ver menú',
                        'url' => $url,
                    ],
                ],
            ],
        ];
    }

    public function finalizeBulkWebOrder(WhatsappCart $cart): string
    {
        $cart->status = WhatsappCart::STATUS_PENDING;
        $cart->payment_status = 'pending';
        $cart->save();

        $details = $this->syncOrderDetails($cart);

        return $details['order_number'];
    }

    public function notifyBulkWebOrderSubmitted(WhatsappContact $contact, WhatsappCart $cart, string $orderNumber): void
    {
        $fallback = "✅ *Pedido registrado*\n\n"
            ."📦 *Número de pedido:* {$orderNumber}\n"
            ."💰 *Total:* \${$cart->total}\n\n"
            .'Guarda este número para consultar el estado. Te contactaremos si hace falta algo más.';

        $body = MessageTemplate::render('bulk_order_submitted', [
            'order_number' => $orderNumber,
            'total' => number_format((float) $cart->total, 2),
        ], $fallback);

        $this->sendMessageToWhatsApp($contact->phone_number, [
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    public function sendBotPayload(WhatsappContact $contact, array $payload, bool $humanSent = false): bool
    {
        if (! $contact->phone_number) {
            return false;
        }

        if ($humanSent) {
            $this->sendTypingIndicatorForContact($contact);
        }

        return (bool) $this->sendMessage($contact->phone_number, $payload);
    }

    public function buildOrderConfirmationPayload(WhatsappCart $cart, string $pdfUrl, ?string $agentNote = null): array
    {
        $cart->loadMissing('items');
        $orderNumber = $cart->getOrderNumber();

        $itemsList = '';
        if ($cart->items->isNotEmpty()) {
            $itemsList .= "*Resumen:*\n";
            foreach ($cart->items->take(6) as $item) {
                $itemsList .= "• {$item->name} x{$item->quantity}\n";
            }
            if ($cart->items->count() > 6) {
                $itemsList .= '• … y '.($cart->items->count() - 6)." producto(s) más\n";
            }
            $itemsList .= "\n";
        }

        $noteLine = ($cart->note && strtolower(trim($cart->note)) !== 'sin nota')
            ? "📝 *Nota:* {$cart->note}\n\n"
            : '';
        $agentNoteLine = $agentNote ? "💬 *Mensaje del asesor:*\n{$agentNote}\n\n" : '';

        $fallback = "📋 *Confirma tu pedido*\n\n"
            ."📦 *Número:* {$orderNumber}\n"
            ."💰 *Total:* \${$cart->total}\n"
            ."📄 *PDF:* {$pdfUrl}\n\n"
            .$itemsList.$noteLine.$agentNoteLine
            .'Revisa el PDF y elige una opción:';

        $body = MessageTemplate::render('order_confirmation_ticket', [
            'order_number' => $orderNumber,
            'total' => number_format((float) $cart->total, 2),
            'pdf_url' => $pdfUrl,
            'items_list' => $itemsList,
            'note_line' => $noteLine,
            'agent_note_line' => $agentNoteLine,
        ], $fallback);

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'confirmar_pedido_'.$cart->id,
                                'title' => '✅ Confirmar',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'modificar_pedido_'.$cart->id,
                                'title' => '✏️ Modificar',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'cancelar_pedido_'.$cart->id,
                                'title' => '❌ Cancelar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Título del botón "Ver carrito" con un badge de cantidad de productos
     * cuando hay algo agregado, para que el cliente note de un vistazo que
     * ya tiene artículos esperando en el carrito.
     */
    private function cartButtonTitle(int $itemCount): string
    {
        if ($itemCount <= 0) {
            return '🛒 Ver carrito';
        }

        // WhatsApp corta (o rechaza) títulos de botón de más de 20
        // caracteres: se limita a 2 dígitos para que el título completo
        // nunca pase de 19, sin importar cuántas unidades tenga el carrito.
        $badge = min($itemCount, 99);

        return "🛒 Ver carrito ({$badge})";
    }

    private function cartItemCount(?WhatsappContact $contact): int
    {
        if (! $contact) {
            return 0;
        }

        $cart = WhatsappCart::where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }

    /**
     * Método de pago preguntado UNA vez por pedido, antes de pedir cantidad o
     * agregar el primer producto (no después de "para llevar" como antes).
     * Si el carrito activo todavía no tiene payment_method, devuelve la
     * pregunta y guarda qué acción había que hacer (mostrar cantidad o
     * agregar directo) para retomarla apenas conteste. Si ya eligió tarjeta,
     * corta cualquier acción nueva del catálogo -- el pedido ya se resuelve
     * en la página externa. Devuelve null si no hay que interceptar nada
     * (ya respondió efectivo/transferencia), y el llamador sigue normal.
     *
     * @param  array{action: string, product_id: int, quantity?: int, variation_index?: ?int}  $pendingAction
     */
    private function interceptForPaymentMethod(WhatsappContact $contact, array $pendingAction): ?array
    {
        $cart = WhatsappCart::firstOrCreate(
            ['contact_id' => $contact->id, 'status' => 'active'],
            ['total' => 0]
        );

        if (! empty($cart->payment_method)) {
            if ($cart->payment_method === 'tarjeta' && ! empty($cart->metadata['card_payment_link_sent'] ?? false)) {
                $cardPaymentUrl = trim((string) ($this->scopedChatbotConfig()?->metadata['card_payment_url'] ?? ''));

                return [
                    'type' => 'text',
                    'text' => ['body' => $cardPaymentUrl !== ''
                        ? "Ya te enviamos el link para pagar tu pedido con tarjeta:\n{$cardPaymentUrl}"
                        : 'Tu pedido con pago por tarjeta ya fue procesado. Si necesitas ayuda, escríbenos.'],
                ];
            }

            return null;
        }

        $metadata = $cart->metadata ?? [];
        $metadata['pending_first_action'] = $pendingAction;
        $cart->metadata = $metadata;
        $cart->save();

        Log::info('[interceptForPaymentMethod] 💳 Solicitando método de pago antes de agregar el primer producto', [
            'cart_id' => $cart->id,
            'contact_id' => $contact->id,
            'pending_action' => $pendingAction['action'],
        ]);

        $cardPaymentUrl = trim((string) ($this->scopedChatbotConfig()?->metadata['card_payment_url'] ?? ''));

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => $this->getCheckoutStepMessage(
                        'payment_method',
                        "💳 *Selecciona el método de pago*\n\nAntes de continuar, elige cómo deseas realizar el pago:"
                    ),
                ],
                'action' => [
                    'button' => 'Seleccionar método de pago',
                    'sections' => [
                        [
                            'title' => 'Métodos de pago disponibles',
                            'rows' => array_values(array_filter([
                                $this->isPaymentMethodEnabled('transferencia') ? [
                                    'id' => 'pago_transferencia_'.$cart->id,
                                    'title' => '🏦 Transferencia',
                                    'description' => 'Transferencia o depósito · envías el comprobante',
                                ] : null,
                                $this->isPaymentMethodEnabled('efectivo') ? [
                                    'id' => 'pago_efectivo_'.$cart->id,
                                    'title' => '💵 Pago en efectivo',
                                    'description' => 'Pago en efectivo al recibir el pedido',
                                ] : null,
                                ($this->isPaymentMethodEnabled('tarjeta') && $cardPaymentUrl !== '') ? [
                                    'id' => 'pago_tarjeta_'.$cart->id,
                                    'title' => '💳 Pago con tarjeta',
                                    'description' => 'Te mandamos un link para pagar en línea',
                                ] : null,
                            ])),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function addToCart(WhatsappContact $contact, $priceId, $quantity = 1, ?int $variationIndex = null)
    {
        try {
            if ($gate = $this->interceptForPaymentMethod($contact, [
                'action' => 'add',
                'product_id' => (int) $priceId,
                'quantity' => $quantity,
                'variation_index' => $variationIndex,
            ])) {
                return $gate;
            }

            $price = WhatsappPrice::query()->whereKey($priceId)
                ->where('business_profile_id', $this->businessProfile?->id)
                ->where('is_active', true)->where('stock', '>', 0)->firstOrFail();
            $variation = $this->productVariation($price, $variationIndex);
            $unitPrice = $variation['price'] ?? ($price->is_promo ? $price->promo_price : $price->price);
            $lineNote = $variation ? 'Variación: '.$variation['title'] : null;

            // Buscar carrito activo o crear uno nuevo
            $cart = WhatsappCart::firstOrCreate(
                ['contact_id' => $contact->id, 'status' => 'active'],
                ['total' => 0]
            );

            // Verificar si el producto ya está en el carrito
            $existingItem = $cart->items()
                ->where('whatsapp_price_id', $priceId)
                ->where('line_note', $lineNote)
                ->first();

            if ($existingItem) {
                // Incrementar cantidad si ya existe
                $existingItem->quantity += $quantity;
                $existingItem->save();
            } else {
                // Crear nuevo item si no existe
                $cart->items()->create([
                    'whatsapp_price_id' => $price->id,
                    'name' => $price->name,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_note' => $lineNote,
                ]);
            }

            // Recalcular total
            $cart->total = $cart->items()->sum(DB::raw('price * quantity'));
            $cart->save();

            $cartItemCount = (int) $cart->items()->sum('quantity');
            $cartButtonTitle = $this->cartButtonTitle($cartItemCount);

            $buttons = [];
            $bulkAvailable = app(BulkOrderService::class)->isAvailable();

            if ($bulkAvailable) {
                $buttons = [
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'seguir_comprando',
                            'title' => '🛍️ Seguir comprando',
                        ],
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'bulk_order_web',
                            'title' => '📋 Armar lista',
                        ],
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'ver_carrito',
                            'title' => $cartButtonTitle,
                        ],
                    ],
                ];
            } else {
                $buttons = [
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'ver_carrito',
                            'title' => $cartButtonTitle,
                        ],
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'seguir_comprando',
                            'title' => '🛍️ Seguir comprando',
                        ],
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'menu_principal',
                            'title' => '🏠 Menú principal',
                        ],
                    ],
                ];
            }

            $bodyText = "✅ *Producto agregado al carrito*\n\n".
                "• {$price->name}\n".
                ($variation ? "• {$variation['title']}\n" : '').
                "• Cantidad: {$quantity}\n".
                '• Precio unitario: $'.$unitPrice."\n".
                '• Subtotal: $'.($unitPrice * $quantity)."\n".
                ($price->is_promo ? "• ¡Aprovecha esta oferta! 🎉\n" : '')."\n".
                '¿Qué deseas hacer?';

            if ($bulkAvailable) {
                $bodyText .= "\n\n_📋 Armar lista:_ ideal si vas a pedir varios productos de una vez.";
            }

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => $bodyText],
                    'action' => ['buttons' => $buttons],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al agregar al carrito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'contact_id' => $contact->id,
                'price_id' => $priceId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al agregar el producto al carrito. Por favor, intenta nuevamente.'],
            ];
        }
    }

    private function finalizarCompra($contact)
    {
        try {
            $cart = WhatsappCart::where('contact_id', $contact->id)
                ->where('status', 'active')
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => "¡Gracias por tu interés! 😊\n\n".
                                "Tu carrito está vacío en este momento.\n\n".
                                '¿En qué más puedo ayudarte?',
                        ],
                        'action' => [
                            'buttons' => [
                                [
                                    'type' => 'reply',
                                    'reply' => [
                                        'id' => 'productos',
                                        'title' => '🛍️ Ver productos',
                                    ],
                                ],
                                [
                                    'type' => 'reply',
                                    'reply' => [
                                        'id' => 'menu_principal',
                                        'title' => '🏠 Menú principal',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // Si ya se le mandó el link de pago con tarjeta, no seguir
            // preguntando nada más — el resto del pedido se resuelve en la
            // página externa, no en este chat.
            if (! empty($cart->metadata['card_payment_link_sent'] ?? false)) {
                $cardPaymentUrl = trim((string) ($this->scopedChatbotConfig()?->metadata['card_payment_url'] ?? ''));

                return [
                    'type' => 'text',
                    'text' => ['body' => $cardPaymentUrl !== ''
                        ? "Ya te enviamos el link para pagar tu pedido con tarjeta:\n{$cardPaymentUrl}"
                        : 'Tu pedido con pago por tarjeta ya fue procesado. Si necesitas ayuda, escríbenos.'],
                ];
            }

            // Paso 1: confirmar la sucursal del pedido (una sola vez por carrito)
            if (empty($cart->metadata['branch_confirmed'] ?? false)) {
                return $this->buildSucursalStep($contact, $cart);
            }

            // Paso 2: para llevar o para servir. Se salta y se asume un valor
            // si: la sucursal tiene desactivado el servicio en mesa (siempre
            // "llevar"), o el admin desactivó el paso completo desde el
            // editor visual (usa el valor por defecto que haya elegido).
            if (empty($cart->metadata['service_type'] ?? null)) {
                $branch = $cart->branch_id ? BusinessBranch::find($cart->branch_id) : null;
                $branchAllowsDineIn = ! $branch || $branch->dine_in_enabled;

                if (! $branchAllowsDineIn || ! $this->isCheckoutStepEnabled('service_type')) {
                    $metadata = $cart->metadata ?? [];
                    $metadata['service_type'] = $branchAllowsDineIn
                        ? $this->getCheckoutStepDefault('service_type', 'llevar')
                        : 'llevar';
                    $cart->metadata = $metadata;
                    $cart->save();
                } else {
                    return $this->buildServiceTypeStep($cart);
                }
            }

            // Paso 2.5 (RESPALDO): en el flujo normal, el método de pago ya
            // se preguntó antes, al pedir cantidad o agregar el primer
            // producto (ver interceptForPaymentMethod). Este bloque solo
            // actúa si por algún motivo un carrito llega hasta acá sin
            // payment_method guardado todavía.
            if (($cart->metadata['service_type'] ?? null) === 'llevar' && empty($cart->payment_method)) {
                $metadata = $cart->metadata ?? [];
                $metadata['pending_payment_method'] = true;
                $cart->metadata = $metadata;
                $cart->save();

                Log::info('[finalizarCompra] 💳 Solicitando método de pago', [
                    'cart_id' => $cart->id,
                    'contact_id' => $contact->id,
                ]);

                $cardPaymentUrl = trim((string) ($this->scopedChatbotConfig()?->metadata['card_payment_url'] ?? ''));

                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'body' => [
                            'text' => $this->getCheckoutStepMessage(
                                'payment_method',
                                "💳 *Selecciona el método de pago*\n\nPor favor, elige cómo deseas realizar el pago:"
                            ),
                        ],
                        'action' => [
                            'button' => 'Seleccionar método de pago',
                            'sections' => [
                                [
                                    'title' => 'Métodos de pago disponibles',
                                    'rows' => array_values(array_filter([
                                        $this->isPaymentMethodEnabled('transferencia') ? [
                                            'id' => 'pago_transferencia_'.$cart->id,
                                            'title' => '🏦 Transferencia',
                                            'description' => 'Transferencia o depósito · envías el comprobante',
                                        ] : null,
                                        $this->isPaymentMethodEnabled('efectivo') ? [
                                            'id' => 'pago_efectivo_'.$cart->id,
                                            'title' => '💵 Pago en efectivo',
                                            'description' => 'Pago en efectivo al recibir el pedido',
                                        ] : null,
                                        ($this->isPaymentMethodEnabled('tarjeta') && $cardPaymentUrl !== '') ? [
                                            'id' => 'pago_tarjeta_'.$cart->id,
                                            'title' => '💳 Pago con tarjeta',
                                            'description' => 'Te mandamos un link para pagar en línea',
                                        ] : null,
                                    ])),
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // Paso 3: retiro en local o delivery (solo aplica si es para
            // llevar). También se puede desactivar desde el editor visual.
            if (($cart->metadata['service_type'] ?? null) === 'llevar'
                && empty($cart->metadata['pickup_mode'] ?? null)) {
                if (! $this->isCheckoutStepEnabled('pickup_mode')) {
                    $metadata = $cart->metadata ?? [];
                    $metadata['pickup_mode'] = $this->getCheckoutStepDefault('pickup_mode', 'retiro');
                    $cart->metadata = $metadata;
                    $cart->save();
                } else {
                    return $this->buildPickupModeStep($cart);
                }
            }

            // Paso 4: dirección de entrega + nombre de quien recibe (el costo
            // de envío lo confirma un vendedor desde el panel, ver módulo de
            // Pedidos).
            if (($cart->metadata['pickup_mode'] ?? null) === 'delivery'
                && empty($cart->metadata['delivery_location'] ?? null)) {
                return $this->buildDeliveryLocationRequest($cart);
            }

            // Si el carrito no tiene nota, solicitar la nota
            if (empty($cart->note)) {
                $metadata = $cart->metadata ?? [];
                $metadata['pending_note'] = true;
                $cart->metadata = $metadata;
                $cart->save();

                Log::info('[finalizarCompra] 📝 Solicitando nota para el pedido', [
                    'cart_id' => $cart->id,
                    'contact_id' => $contact->id,
                ]);

                $faltaMensaje = ($cart->metadata['service_type'] ?? null) === 'servir'
                    ? 'Solo falta la nota (opcional) y confirmamos tu pedido.'
                    : 'Solo falta la nota (opcional) y el pago.';

                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => ['text' => "🎉 ¡Ya casi terminamos con tu pedido!\n{$faltaMensaje}\n\n".
                            'Si quieres, escribe una nota (instrucciones especiales, preferencias o cualquier detalle importante), o toca el botón para continuar sin nota.'],
                        'action' => [
                            'buttons' => [
                                [
                                    'type' => 'reply',
                                    'reply' => ['id' => 'nota_omitir_'.$cart->id, 'title' => '✅ Sin nota'],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // El método de pago ahora se pregunta siempre, antes de armar el
            // carrito (ver interceptForPaymentMethod) -- incluye "para
            // servir". "Pagar en caja" queda solo como respaldo para el caso
            // (ya no debería darse en el flujo normal) de que el carrito
            // llegue hasta acá sin ningún payment_method guardado.
            if (($cart->metadata['service_type'] ?? null) === 'servir' && empty($cart->payment_method)) {
                return $this->finalizePayAtRegisterOrder($cart);
            }

            // Preparar los detalles del pedido para guardar en metadata
            $orderDetails = [
                'order_number' => 'ORD-'.str_pad($cart->id, 6, '0', STR_PAD_LEFT),
                'items' => [],
                'total' => $cart->total,
                'note' => $cart->note,
                'created_at' => $cart->created_at->format('Y-m-d H:i:s'),
                'status' => $cart->status,
                'payment_method' => $cart->payment_method,
                'payment_status' => $cart->payment_status,
                'branch' => $cart->branch?->name,
                'service_type' => $cart->metadata['service_type'] ?? null,
                'pickup_mode' => $cart->metadata['pickup_mode'] ?? null,
                'delivery_location' => $cart->metadata['delivery_location'] ?? null,
                'delivery_distance_km' => $cart->metadata['delivery_distance_km'] ?? null,
                'delivery_fee' => $cart->metadata['delivery_fee'] ?? null,
                'delivery_recipient_name' => $cart->metadata['delivery_recipient_name'] ?? null,
                'delivery_fee_pending_review' => $cart->metadata['delivery_fee_pending_review'] ?? false,
            ];

            foreach ($cart->items as $item) {
                $orderDetails['items'][] = [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->price * $item->quantity,
                ];
            }

            // Guardar los detalles del pedido en metadata
            $metadata = $cart->metadata ?? [];
            $metadata['order_details'] = $orderDetails;
            $cart->metadata = $metadata;
            $cart->save();

            // Preparar resumen del pedido
            $message = $this->buildOrderSummaryHeader('📋 *Resumen de tu pedido*', $orderDetails['order_number']);
            $message .= $this->buildOrderItemsText($cart);
            $message .= $this->buildFulfillmentSummaryText($cart);
            $message .= $this->buildPaymentMethodBlock($this->getPaymentMethodText($cart->payment_method));
            $message .= $this->buildCostBreakdownText($cart, false);

            if ($cart->note && $cart->note !== 'sin nota') {
                $message .= "📝 *Nota:* {$cart->note}\n\n";
            }

            $message .= '¿Confirmas tu pedido?';

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'confirmar_pedido_'.$cart->id,
                                    'title' => '✅ Confirmar pedido',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'cancelar_pedido_'.$cart->id,
                                    'title' => '❌ Cancelar pedido',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error al finalizar compra', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al procesar tu compra.'],
            ];
        }
    }

    /**
     * Paso 1 del checkout: confirmar la sucursal (si el cliente ya pidió antes)
     * o elegirla de la lista de sucursales activas.
     */
    private function buildSucursalStep(WhatsappContact $contact, WhatsappCart $cart)
    {
        $lastBranchId = $contact->getLastBranchId();
        $lastBranch = $lastBranchId
            ? BusinessBranch::where('id', $lastBranchId)->where('is_active', true)->first()
            : null;

        if ($lastBranch) {
            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $this->getCheckoutStepMessage(
                            'sucursal_repeat',
                            "📍 *Sucursal de tu pedido*\n\n¿Pedirás nuevamente desde *{{sucursal}}*?",
                            ['sucursal' => $lastBranch->name]
                        ),
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => ['id' => 'sucursal_mantener_'.$cart->id, 'title' => '✅ Sí, la misma'],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => ['id' => 'sucursal_cambiar_'.$cart->id, 'title' => '🔄 Elegir otra'],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $this->buildSucursalList($cart);
    }

    private function buildSucursalList(WhatsappCart $cart)
    {
        $branches = BusinessBranch::where('business_profile_id', $this->businessProfile->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            // No hay sucursales configuradas todavía: no bloquear el pedido.
            $metadata = $cart->metadata ?? [];
            $metadata['branch_confirmed'] = true;
            $cart->metadata = $metadata;
            $cart->save();

            return $this->finalizarCompra($cart->contact);
        }

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $this->getCheckoutStepMessage('sucursal_list', '📍 *¿Desde qué sucursal pedirás?*')],
                'action' => [
                    'button' => 'Elegir sucursal',
                    'sections' => [[
                        'title' => 'Sucursales disponibles',
                        'rows' => $branches->map(fn (BusinessBranch $branch) => [
                            'id' => 'sucursal_set_'.$branch->id.'_'.$cart->id,
                            'title' => mb_substr($branch->name, 0, 24),
                        ])->values()->all(),
                    ]],
                ],
            ],
        ];
    }

    private function confirmarSucursalMantenida(WhatsappContact $contact, int $cartId)
    {
        $cart = WhatsappCart::where('id', $cartId)->where('contact_id', $contact->id)->first();
        if (! $cart) {
            return ['type' => 'text', 'text' => ['body' => 'Lo siento, no se encontró el pedido.']];
        }

        $lastBranchId = $contact->getLastBranchId();
        $branch = $lastBranchId ? BusinessBranch::where('id', $lastBranchId)->where('is_active', true)->first() : null;

        if (! $branch) {
            return $this->buildSucursalList($cart);
        }

        return $this->setSucursalPedido($contact, $branch->id, $cartId);
    }

    private function setSucursalPedido(WhatsappContact $contact, int $branchId, int $cartId)
    {
        $cart = WhatsappCart::where('id', $cartId)->where('contact_id', $contact->id)->first();
        if (! $cart) {
            return ['type' => 'text', 'text' => ['body' => 'Lo siento, no se encontró el pedido.']];
        }

        $branch = BusinessBranch::where('id', $branchId)
            ->where('business_profile_id', $this->businessProfile->id)
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            return $this->buildSucursalList($cart);
        }

        $cart->branch_id = $branch->id;
        $metadata = $cart->metadata ?? [];
        $metadata['branch_confirmed'] = true;
        $cart->metadata = $metadata;
        $cart->save();

        $contact->rememberBranch($branch->id);

        return $this->finalizarCompra($contact);
    }

    /**
     * Paso 2 del checkout: para llevar o para servir.
     */
    private function buildServiceTypeStep(WhatsappCart $cart)
    {
        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $this->getCheckoutStepMessage('service_type', '🍽️ *¿Tu pedido es para llevar o para servir?*')],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => ['id' => 'tipo_llevar_'.$cart->id, 'title' => '🥡 Para llevar'],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => ['id' => 'tipo_servir_'.$cart->id, 'title' => '🍽️ Para servir'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function setTipoServicio(WhatsappContact $contact, int $cartId, string $tipo)
    {
        $cart = WhatsappCart::where('id', $cartId)->where('contact_id', $contact->id)->first();
        if (! $cart) {
            return ['type' => 'text', 'text' => ['body' => 'Lo siento, no se encontró el pedido.']];
        }

        $metadata = $cart->metadata ?? [];
        $metadata['service_type'] = $tipo;
        $cart->metadata = $metadata;
        $cart->save();

        return $this->finalizarCompra($contact);
    }

    /**
     * Paso 3 del checkout (solo si es para llevar): retiro en local o delivery.
     */
    private function buildPickupModeStep(WhatsappCart $cart)
    {
        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $this->getCheckoutStepMessage('pickup_mode', "🚗 *¿Retiras en el local o prefieres delivery?*\n\n🛵 El delivery tiene un costo adicional que te confirmaremos por este chat.")],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => ['id' => 'retiro_local_'.$cart->id, 'title' => '🏬 Retiro en local'],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => ['id' => 'retiro_delivery_'.$cart->id, 'title' => '🛵 Delivery'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function setModoRetiro(WhatsappContact $contact, int $cartId, string $modo)
    {
        $cart = WhatsappCart::where('id', $cartId)->where('contact_id', $contact->id)->first();
        if (! $cart) {
            return ['type' => 'text', 'text' => ['body' => 'Lo siento, no se encontró el pedido.']];
        }

        $metadata = $cart->metadata ?? [];
        $metadata['pickup_mode'] = $modo;
        $cart->metadata = $metadata;
        $cart->save();

        return $this->finalizarCompra($contact);
    }

    /**
     * Paso 4 del checkout (solo si eligió delivery): pedir la dirección de
     * entrega por texto. No se pide ubicación GPS: el costo de envío exacto
     * lo confirma el vendedor manualmente desde el panel según la dirección
     * (ver buildFulfillmentSummaryText y el módulo de Pedidos).
     */
    private function buildDeliveryLocationRequest(WhatsappCart $cart)
    {
        $metadata = $cart->metadata ?? [];
        $metadata['awaiting_delivery_address'] = true;
        $cart->metadata = $metadata;
        $cart->save();

        return [
            'type' => 'text',
            'text' => ['body' => $this->getCheckoutStepMessage('delivery_location', '📍 Escríbenos la *dirección completa* de entrega (calle, sector, referencia).')],
        ];
    }

    /**
     * Encabezado compacto del resumen de pedido: "{título}\nPedido *#XXX*".
     */
    private function buildOrderSummaryHeader(string $title, string $orderNumber): string
    {
        return "{$title}\nPedido *#{$orderNumber}*\n\n";
    }

    /**
     * Lista de productos en texto plano, sin ícono por línea (solo el
     * nombre en negrita) -- el desglose de precios va aparte, en
     * buildCostBreakdownText().
     */
    private function buildOrderItemsText(WhatsappCart $cart): string
    {
        $cart->loadMissing('items');
        $lines = '';

        foreach ($cart->items as $item) {
            $lines .= "*{$item->name}*\n";
            $lines .= "Cantidad: {$item->quantity}\n";
            $lines .= 'Precio unitario: $'.number_format((float) $item->price, 2)."\n\n";
        }

        return $lines;
    }

    private function buildPaymentMethodBlock(string $label): string
    {
        return "💳 *Pago*\n{$label}\n\n";
    }

    /**
     * Desglose de costos del pedido: subtotal, IVA (si está configurado —
     * ya viene incluido en el precio, esto solo lo muestra por separado sin
     * cambiar el total) y costo de envío/para llevar (confirmado, o "por
     * confirmar" si todavía falta que un vendedor lo revise). Mientras haya
     * algún costo pendiente, el total se llama "Total productos" (aclara que
     * todavía puede subir) y se agrega una sola línea final con lo que falta
     * confirmar -- en vez de una línea "Por confirmar" repetida por cada
     * costo, que saturaba el mensaje.
     *
     * $includeTotal en false omite el total (y el aviso de "por confirmar")
     * -- se usa en los mensajes del flujo antes de que un vendedor confirme
     * el costo de envío/para llevar, para no mostrarle al cliente un total
     * que todavía puede cambiar. Ese total final se le manda recién en el
     * mensaje de costo confirmado (ver OrderLifecycleService::buildFulfillmentCostsMessageBody).
     */
    private function buildCostBreakdownText(WhatsappCart $cart, bool $includeTotal = true): string
    {
        $cart->loadMissing('items');
        $subtotal = (float) $cart->items->sum(fn ($item) => $item->price * $item->quantity);

        $config = WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first()
            ?? WhatsappChatbotConfig::first();

        $lines = '';

        if ($config?->iva_enabled && $config->iva_percentage > 0) {
            $rate = $config->iva_percentage / 100;
            $ivaAmount = $subtotal - ($subtotal / (1 + $rate));
            $pct = rtrim(rtrim(number_format($config->iva_percentage, 2), '0'), '.');
            $lines .= "IVA incluido ({$pct}%): $".number_format($ivaAmount, 2)."\n";
        }

        $metadata = $cart->metadata ?? [];
        $pending = [];

        if (($metadata['pickup_mode'] ?? null) === 'delivery') {
            if (! empty($metadata['delivery_fee_pending_review'] ?? false) || ! array_key_exists('delivery_fee', $metadata)) {
                $pending[] = 'envío';
            } else {
                $deliveryFee = (float) ($metadata['delivery_fee'] ?? 0);
                if ($deliveryFee > 0) {
                    $lines .= 'Costo de envío: $'.number_format($deliveryFee, 2)."\n";
                }
            }
        }

        if (($metadata['service_type'] ?? null) === 'llevar') {
            if (! array_key_exists('pickup_fee', $metadata)) {
                $pending[] = 'costo para llevar';
            } else {
                $pickupFee = (float) ($metadata['pickup_fee'] ?? 0);
                if ($pickupFee > 0) {
                    $lines .= 'Costo para llevar: $'.number_format($pickupFee, 2)."\n";
                }
            }
        }

        if (! $includeTotal) {
            return $lines."\n";
        }

        $totalLabel = $pending ? 'Total productos' : 'Total';
        $lines .= "💰 *{$totalLabel}:* $".number_format((float) $cart->total, 2)."\n";

        if ($pending) {
            $lines .= ucfirst(implode(' y ', $pending)).": por confirmar\n";
        }

        return $lines."\n";
    }

    /**
     * Bloque de entrega (tipo de pedido, sucursal, dirección) que se agrega
     * al resumen del pedido, tanto para el cliente como para lo que ve caja
     * en el panel.
     */
    private function buildFulfillmentSummaryText(WhatsappCart $cart): string
    {
        $branchName = $cart->branch?->name;
        $serviceType = $cart->metadata['service_type'] ?? null;
        $pickupMode = $cart->metadata['pickup_mode'] ?? null;

        $typeLabel = match ($serviceType) {
            'llevar' => 'Para llevar',
            'servir' => 'Para servir',
            default => null,
        };

        if (! $typeLabel && ! $branchName) {
            return '';
        }

        $lines = "🚚 *Entrega*\n";
        if ($branchName) {
            $lines .= "Sucursal: {$branchName}\n";
        }
        if ($typeLabel) {
            $lines .= "Tipo: {$typeLabel}\n";
        }

        if ($serviceType === 'llevar' && $pickupMode === 'delivery') {
            $address = $cart->metadata['delivery_location']['manual_address'] ?? null;
            if ($address) {
                $lines .= "Dirección: {$address}\n";
            }

            $recipientName = $cart->metadata['delivery_recipient_name'] ?? null;
            if ($recipientName) {
                $lines .= "Recibe: {$recipientName}\n";
            }
        } elseif ($serviceType === 'llevar' && $pickupMode === 'retiro') {
            $lines .= "Entrega: Retiro en el local\n";
        }

        return $lines."\n";
    }

    /**
     * Botón "Sin nota" del paso de nota: evita que el cliente tenga que
     * escribir texto para saltarse un campo opcional.
     */
    private function skipOrderNote(WhatsappContact $contact, int $cartId)
    {
        $cart = WhatsappCart::where('id', $cartId)->where('contact_id', $contact->id)->first();
        if (! $cart) {
            return ['type' => 'text', 'text' => ['body' => 'Lo siento, no se encontró el pedido.']];
        }

        $cart->note = 'sin nota';
        $metadata = $cart->metadata ?? [];
        unset($metadata['pending_note']);
        $cart->metadata = $metadata;
        $cart->save();

        return $this->finalizarCompra($contact);
    }

    /**
     * Pedidos "para servir" no piden método de pago: se genera el número de
     * pedido y se le dice al cliente que pague en caja, igual que en el
     * punto de venta. El carrito pasa a "pending" para que caja lo confirme.
     */
    private function finalizePayAtRegisterOrder(WhatsappCart $cart): array
    {
        $cart->status = WhatsappCart::STATUS_PENDING;
        $cart->payment_status = 'pending';
        $cart->save();

        $details = $this->syncOrderDetails($cart);

        $metadata = $cart->metadata ?? [];
        unset($metadata['awaiting_client_confirmation'], $metadata['pending_payment_method']);
        $metadata['confirmed_at'] = now()->toIso8601String();
        $metadata['confirmed_via'] = 'whatsapp_pay_at_register';
        $cart->metadata = $metadata;
        $cart->save();

        $body = $this->buildOrderSummaryHeader('✅ *¡Pedido registrado!*', $details['order_number'])
            .$this->buildFulfillmentSummaryText($cart)
            .$this->buildCostBreakdownText($cart)
            .'🧾 Pasa a caja con tu número de pedido para cancelar. ¡Gracias por tu pedido!';

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'menu_pedido', 'title' => '📦 Mis pedidos']],
                        ['type' => 'reply', 'reply' => ['id' => 'menu_principal', 'title' => '🏠 Menú principal']],
                    ],
                ],
            ],
        ];
    }

    /**
     * Pregunta "¿a nombre de quién recibimos el pedido?" con dos botones:
     * el nombre del propio contacto (compra en un toque) u "Otro nombre"
     * para escribirlo a mano. Antes esto solo aceptaba texto libre.
     */
    private function buildRecipientNamePrompt(WhatsappContact $contact): array
    {
        $selfName = trim((string) $contact->name) !== '' ? $contact->name : 'Cliente';

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $this->getCheckoutStepMessage('delivery_recipient_name', '🧑 ¿A nombre de quién recibimos el pedido?'),
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'recipient_name_self',
                                'title' => Str::limit($selfName, 20, ''),
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'recipient_name_other',
                                'title' => '✍️ Otro nombre',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Guarda el nombre de quien recibe, calcula el estimado referencial de
     * envío y continúa el checkout. Compartido entre el botón "usar mi
     * nombre" y la respuesta de texto libre con un nombre distinto.
     */
    private function applyDeliveryRecipientName(WhatsappContact $contact, WhatsappCart $cart, string $name): array
    {
        $minimumFee = (float) ($cart->branch?->delivery_fee_minimum ?? config('delivery.minimum_fee'));

        $metadata = $cart->metadata ?? [];
        unset($metadata['awaiting_delivery_recipient_name']);
        $metadata['delivery_recipient_name'] = trim($name);
        $metadata['delivery_fee'] = $minimumFee;
        $metadata['delivery_fee_pending_review'] = true;
        $cart->metadata = $metadata;
        $cart->save();

        return $this->finalizarCompra($contact);
    }

    /**
     * Indica si el carrito activo tiene algún paso del checkout pendiente
     * (sucursal, tipo de pedido, retiro/delivery, nota o método de pago),
     * usado por el escape valve de "cancelar" en handleTextMessage.
     */
    private function cartHasPendingCheckoutStep(WhatsappCart $cart): bool
    {
        // Bug real: un carrito activo recién creado (todavía sin productos,
        // el cliente apenas está navegando categorías) también tiene
        // 'branch_confirmed' vacío -- sin este chequeo, cualquier saludo o
        // palabra suelta mientras se navega el catálogo (sin haber tocado
        // "Finalizar compra" todavía) se interpretaba como "checkout
        // pendiente" y reenviaba finalizarCompra(), que a su vez respondía
        // "tu carrito está vacío" pegado atrás del saludo -- un mensaje
        // contradictorio. Sin ítems no puede haber ningún paso de checkout
        // pendiente, sin importar qué falte en el metadata.
        if ($cart->items->isEmpty()) {
            return false;
        }

        if (empty($cart->metadata['branch_confirmed'] ?? false)) {
            return true;
        }
        if (empty($cart->metadata['service_type'] ?? null)) {
            return true;
        }
        if (($cart->metadata['service_type'] ?? null) === 'llevar'
            && empty($cart->metadata['pickup_mode'] ?? null)) {
            return true;
        }
        if (($cart->metadata['pickup_mode'] ?? null) === 'delivery'
            && empty($cart->metadata['delivery_location'] ?? null)) {
            return true;
        }
        if (! empty($cart->metadata['awaiting_delivery_recipient_name'] ?? false)) {
            return true;
        }
        if (! empty($cart->metadata['pending_note'] ?? false)) {
            return true;
        }
        if (! empty($cart->metadata['pending_payment_method'] ?? false)) {
            return true;
        }

        return false;
    }

    private function getPaymentMethodText($method)
    {
        return match ($method) {
            'transferencia' => 'Transferencia o depósito bancario',
            'efectivo' => 'Pago en efectivo',
            'tarjeta' => 'Pago con tarjeta',
            default => 'No especificado'
        };
    }

    /**
     * Limpia formato WhatsApp (*negrita*, _cursiva_, etc.) al buscar SKU copiado del chat.
     */
    private function normalizeProductSearchTerm(string $text): string
    {
        $text = trim($text);

        if (preg_match('/\[?SKU:\s*([^\]\*\s]+)\]?/i', $text, $matches)) {
            return trim($matches[1], '*_~`');
        }

        $previous = null;
        while ($previous !== $text) {
            $previous = $text;
            $text = trim($text);
            $text = preg_replace('/^[\*_~`]+|[\*_~`]+$/u', '', $text) ?? $text;
        }

        return trim($text);
    }

    /**
     * true si este número (staff, no cliente) tiene permiso para usar la
     * palabra clave de consulta de delivery. La lista se configura en
     * Configuración del bot; vacía por defecto, así que la función queda
     * desactivada hasta que se configure explícitamente.
     */
    private function isAuthorizedDispatchNumber(string $phone): bool
    {
        $authorized = $this->scopedChatbotConfig()?->delivery_dispatch_numbers ?? [];
        if (empty($authorized)) {
            return false;
        }

        return in_array(preg_replace('/\D+/', '', $phone), $authorized, true);
    }

    /**
     * Comando de despacho: el equipo escribe la palabra clave configurada
     * (por defecto "2501"), el bot pide el número de pedido, y responde con
     * los datos listos para reenviar al repartidor por WhatsApp. Devuelve
     * true si el mensaje era parte de este comando (el caller no debe seguir
     * procesándolo como un mensaje normal), false si no aplica.
     */
    private function handleDeliveryDispatchLookup(WhatsappContact $contact, string $from, string $rawText): bool
    {
        $config = $this->scopedChatbotConfig();
        $keyword = $config?->delivery_dispatch_keyword ?? '2501';

        if ($rawText !== '' && strcasecmp($rawText, $keyword) === 0) {
            $metadata = $contact->metadata ?? [];
            $metadata['awaiting_delivery_dispatch_lookup'] = true;
            $contact->metadata = $metadata;
            $contact->save();

            $this->sendMessage($from, [
                'type' => 'text',
                'text' => ['body' => "🛵 *Datos para delivery*\n\n¿Cuál es el número de pedido?"],
            ]);

            return true;
        }

        if (! empty($contact->metadata['awaiting_delivery_dispatch_lookup'] ?? false)) {
            $metadata = $contact->metadata ?? [];
            unset($metadata['awaiting_delivery_dispatch_lookup']);
            $contact->metadata = $metadata;
            $contact->save();

            $order = $this->findOrderForDispatchLookup($rawText);

            $this->sendMessage($from, [
                'type' => 'text',
                'text' => ['body' => $order
                    ? $this->buildDispatchInfoText($order)
                    : "❌ No encontré ningún pedido con el número *{$rawText}*. Escribe *{$keyword}* para intentar de nuevo.",
                ],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Busca un pedido por su número visible (p.ej. "ORD-013", "013" o "13")
     * o, como último recurso, por el ID interno del carrito. No usa una
     * consulta SQL sobre el JSON de metadata porque el número visible puede
     * venir de dos fuentes distintas (turno diario o ID) según el pedido;
     * es más simple y correcto reutilizar WhatsappCart::getOrderNumber().
     */
    private function findOrderForDispatchLookup(string $input): ?WhatsappCart
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $needle = strtoupper(preg_replace('/\s+/', '', $input));
        $needleDigits = ltrim(preg_replace('/\D+/', '', $needle), '0');

        return WhatsappCart::reportable()
            ->with(['contact', 'branch'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->first(function (WhatsappCart $order) use ($needle, $needleDigits) {
                $orderNumber = strtoupper($order->getOrderNumber());
                if ($orderNumber === $needle || $orderNumber === 'ORD-'.$needle) {
                    return true;
                }

                if ($needleDigits === '') {
                    return false;
                }

                $orderDigits = ltrim(preg_replace('/\D+/', '', $orderNumber), '0');

                return $orderDigits !== '' && $orderDigits === $needleDigits;
            });
    }

    /** Texto listo para reenviarle al repartidor por WhatsApp. */
    private function buildDispatchInfoText(WhatsappCart $order): string
    {
        $metadata = $order->metadata ?? [];

        if (($metadata['pickup_mode'] ?? null) !== 'delivery') {
            $tipo = ($metadata['service_type'] ?? null) === 'servir' ? 'para servir' : 'retiro en el local';

            return "⚠️ El pedido *{$order->getOrderNumber()}* no es de delivery (es *{$tipo}*), no tiene dirección de entrega.";
        }

        $recipient = $metadata['delivery_recipient_name'] ?? $order->contact?->name ?? 'Cliente';
        $address = $metadata['delivery_location']['manual_address'] ?? 'Sin dirección registrada';

        return "🛵 *Datos para el delivery*\n\n"
            ."Pedido: *{$order->getOrderNumber()}*\n"
            ."Entregar a: {$recipient}\n"
            ."Dirección: {$address}\n"
            .'Pago: '.$this->getDispatchPaymentLabel($order);
    }

    /**
     * Nota de pago pensada para el repartidor: si es efectivo, le dice
     * cuánto cobrar al entregar; si ya se pagó por transferencia/tarjeta, le
     * aclara que no debe cobrar nada -- sin esto, "Transferencia o depósito
     * bancario" a secas no le dice al repartidor qué hacer con el dinero.
     */
    private function getDispatchPaymentLabel(WhatsappCart $order): string
    {
        return match ($order->payment_method) {
            'efectivo' => 'Efectivo — cobrar $'.number_format((float) $order->total, 2).' al entregar',
            'transferencia' => 'Transferencia o depósito (ya pagado, no cobrar)',
            'tarjeta' => 'Tarjeta (ya pagado, no cobrar)',
            default => 'No especificado',
        };
    }

    /**
     * Avisa al cliente por WhatsApp que su pedido salió en camino y le
     * comparte el contacto del repartidor (tarjeta de contacto real, no
     * solo el nombre en texto, para que pueda escribirle directo si hace
     * falta). Igual que las demás notificaciones automáticas, no manda nada
     * si el número es sintético (POS) o si ya se cerró la ventana de 24h.
     *
     * @return array{sent: bool, reason: ?string}
     */
    public function notifyCustomerOrderOnTheWay(WhatsappCart $order, DeliveryDriver $driver): array
    {
        $contact = $order->contact;

        if (! $contact || ! $contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
            return ['sent' => false, 'reason' => 'no_phone'];
        }

        if (! $contact->last_inbound_at || $contact->last_inbound_at->lt(now()->subHours(24))) {
            return ['sent' => false, 'reason' => 'window_closed'];
        }

        $fallback = "🛵 *¡Tu pedido va en camino!*\n\nPedido *{$order->getOrderNumber()}*\n\n"
            ."Te compartimos el contacto de tu repartidor, *{$driver->full_name}*, por si necesitas comunicarte con él.";

        $body = MessageTemplate::render('order_on_the_way', [
            'order_number' => $order->getOrderNumber(),
            'driver_name' => $driver->full_name,
        ], $fallback);

        $this->sendBotPayload($contact, [
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        $cardSent = $this->sendDriverContactCard($contact, $driver);

        return ['sent' => true, 'reason' => $cardSent ? null : 'contact_card_failed'];
    }

    /**
     * Manda la tarjeta de contacto del repartidor con su propio número como
     * wa_id (para que el cliente pueda tocar "Enviar mensaje" y le escriba
     * directo a él, no al negocio). No reutiliza formatContacts()/sendMessage()
     * porque esas siempre fuerzan el wa_id al número del negocio -- correcto
     * para "compartir el contacto del negocio", pero no para este caso.
     */
    private function sendDriverContactCard(WhatsappContact $contact, DeliveryDriver $driver): bool
    {
        $phoneNumberId = $this->resolvePhoneNumberId();
        if (! $phoneNumberId || ! $contact->phone_number) {
            return false;
        }

        $waId = preg_replace('/\D+/', '', $driver->phone_number);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $contact->phone_number,
            'type' => 'contacts',
            'contacts' => [[
                'name' => [
                    'formatted_name' => $driver->full_name,
                    'first_name' => $driver->first_name,
                    'last_name' => $driver->last_name ?? '',
                ],
                'phones' => [[
                    'phone' => $driver->phone_number,
                    'type' => 'CELL',
                    'wa_id' => $waId,
                ]],
            ]],
        ];

        try {
            $response = Http::withToken($this->apiToken())->timeout(10)->retry(2, 1500)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages", $payload);
        } catch (\Throwable $e) {
            $this->recordSendFailure(
                $contact,
                'contacts',
                'Error al compartir el contacto del repartidor: '.$e->getMessage(),
                ['driver_id' => $driver->id],
                'sendDriverContactCard'
            );

            return false;
        }

        if ($response->successful()) {
            WhatsappMessage::create([
                'business_profile_id' => $this->businessProfile?->id,
                'contact_id' => $contact->id,
                'message_id' => $response->json()['messages'][0]['id'] ?? null,
                'content' => 'Contacto compartido: '.$driver->full_name,
                'type' => 'contacts',
                'status' => 'sent',
                'sender_type' => 'system',
                'receiver_type' => 'client',
                'metadata' => ['driver_id' => $driver->id, 'driver_phone' => $driver->phone_number],
            ]);

            return true;
        }

        $this->recordSendFailure(
            $contact,
            'contacts',
            'Error al compartir el contacto del repartidor: '.json_encode($response->json()),
            ['driver_id' => $driver->id],
            'sendDriverContactCard'
        );

        return false;
    }

    /**
     * Procesa los mensajes de texto recibidos de WhatsApp
     * Esta función maneja:
     * 1. Creación/actualización de contactos
     * 2. Guardado de mensajes
     * 3. Procesamiento de notas para pedidos
     * 4. Búsqueda de productos por SKU
     * 5. Generación de respuestas del chatbot
     */
    private function handleTextMessage($message)
    {
        try {
            // Validar que el mensaje tenga la estructura correcta
            if (! isset($message['text'])) {
                Log::error('[handleTextMessage] ❌ Estructura de mensaje inválida', [
                    'message' => $message,
                ]);

                return;
            }

            // Obtener el texto del mensaje, manejando tanto strings como arrays
            $text = is_array($message['text']) ? strtolower($message['text']['body']) : strtolower($message['text']);
            $from = $message['from'];
            $messageId = $message['id'] ?? null;
            $contact = $this->findContactByPhone($from);

            // Comando interno para el equipo de despacho (no para clientes):
            // consulta los datos de entrega de un pedido para reenviarlos al
            // repartidor. Solo responde a números autorizados desde el panel
            // (ver Configuración del bot); para cualquier otro contacto, ni
            // siquiera se evalúa -- sigue el flujo normal sin dejar rastro.
            if ($this->isAuthorizedDispatchNumber($from)) {
                $rawText = is_array($message['text']) ? ($message['text']['body'] ?? '') : (string) $message['text'];
                $dispatchContact = $contact ?? WhatsappContact::firstOrCreate(
                    ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
                    ['name' => 'Equipo de despacho', 'status' => 'active']
                );

                if ($this->handleDeliveryDispatchLookup($dispatchContact, $from, trim($rawText))) {
                    return;
                }

                $contact = $dispatchContact;
            }

            // Detectar si el cliente pide el catálogo (verificar bot_enabled antes)
            if (preg_match('/(catalogo|catálogo|productos|precios|lista de precios|ver productos)/i', $text)) {
                if ($contact) {
                    $contact->refresh();
                    if (! $this->botMayRespondToContact($contact)) {
                        $this->logBotBlocked('handleTextMessage.catalog', $contact, [
                            'phone' => substr($from, 0, 4).'****'.substr($from, -4),
                        ]);

                        return;
                    }
                    if ($messageId) {
                        $this->rememberInboundMessage($contact, $messageId);
                    }
                }
                if ($contact && $this->botMayRespondToContact($contact) && $messageId) {
                    $this->prepareBotReply($contact, $messageId);
                }
                $this->sendCatalog($from);

                return;
            }

            Log::info('[inicio Texto] handleTextMessage');
            // Extraer información básica del mensaje
            $from = $message['from']; // Número de teléfono del remitente
            $text = is_array($message['text']) ? $message['text']['body'] : $message['text']; // Obtener el texto del mensaje
            $messageId = $message['id']; // ID único del mensaje

            // Lista de respuestas comunes que no deben ser tratadas como SKUs
            $commonResponses = ['no', 'si', 'ok', 'okay', 'gracias', 'thanks', 'bye', 'adios', 'chao', 'hola', 'hi', 'hello'];

            // Buscar o crear el contacto en la base de datos
            $contact = $this->findContactByPhone($from);
            if (! $contact) {
                // Si el contacto no existe, obtener sus datos del webhook
                $contactData = $message['contacts'][0] ?? [];
                $profile = $contactData['profile'] ?? [];
                $contactName = $profile['name'] ?? 'Contacto sin nombre';

                // Crear nuevo contacto en la base de datos
                $contact = WhatsappContact::create([
                    'business_profile_id' => $this->businessProfile->id,
                    'phone_number' => $from,
                    'name' => $contactName,
                    'status' => 'active',
                ]);

                Log::info('[handleTextMessage] ✅ Nuevo contacto creado', [
                    'phone' => $from,
                    'contact_id' => $contact->id,
                    'name' => $contactName,
                ]);
            } elseif ($contact->name === 'Contacto sin nombre') {
                // Si el contacto existe pero tiene nombre genérico, intentar actualizarlo con el nombre real
                $contactData = $message['contacts'][0] ?? [];
                $profile = $contactData['profile'] ?? [];
                $contactName = $profile['name'] ?? null;

                if ($contactName && $contactName !== 'Contacto sin nombre') {
                    $contact->name = $contactName;
                    $contact->save();

                    Log::info('[handleTextMessage] ✅ Nombre de contacto actualizado', [
                        'phone' => $from,
                        'contact_id' => $contact->id,
                        'old_name' => 'Contacto sin nombre',
                        'new_name' => $contactName,
                    ]);
                }
            } else {
                Log::info('[handleTextMessage] 📝 Contacto encontrado en la base de dato');
            }

            // Guardar el mensaje en la base de datos
            $whatsappMessage = WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $messageId,
                'content' => $text,
                'type' => 'text',
                'status' => 'received',
                'sender_type' => 'client',
                'receiver_type' => 'system',
            ]);

            $this->lastMessage = $whatsappMessage;
            $this->rememberInboundMessage($contact, $messageId);

            // Variables para controlar el flujo de procesamiento
            $response = null;
            $processHandled = false;
            $willAutoReply = false;

            // Verificar si hay un carrito activo esperando una nota
            $cart = WhatsappCart::where('contact_id', $contact->id)
                ->where('status', 'active')
                ->first();

            $normalizedText = strtolower(trim($text));

            // Si el cliente eligió "Otra cantidad", el próximo texto debe ser
            // el número que quiere agregar (entre 1 y 49). Si pasaron más de
            // 10 minutos desde que se pidió, se descarta en silencio para no
            // interpretar un mensaje sin relación como una cantidad.
            $pendingQuantity = $contact->metadata['pending_custom_quantity'] ?? null;
            if ($pendingQuantity) {
                $requestedAt = isset($pendingQuantity['requested_at']) ? Carbon::parse($pendingQuantity['requested_at']) : null;
                if (! $requestedAt || $requestedAt->lt(now()->subMinutes(10))) {
                    $metadata = $contact->metadata ?? [];
                    unset($metadata['pending_custom_quantity']);
                    $contact->metadata = $metadata;
                    $contact->save();
                    $pendingQuantity = null;
                }
            }

            if (! $processHandled && $pendingQuantity) {
                if (in_array($normalizedText, ['cancelar', 'salir'], true)) {
                    $metadata = $contact->metadata ?? [];
                    unset($metadata['pending_custom_quantity']);
                    $contact->metadata = $metadata;
                    $contact->save();
                    $response = $this->getMainMenu(null, $contact);
                } elseif (ctype_digit($normalizedText) && (int) $normalizedText > 0 && (int) $normalizedText < 50) {
                    $metadata = $contact->metadata ?? [];
                    unset($metadata['pending_custom_quantity']);
                    $contact->metadata = $metadata;
                    $contact->save();
                    $response = $this->addToCart(
                        $contact,
                        (int) $pendingQuantity['product_id'],
                        (int) $normalizedText,
                        $pendingQuantity['variation_index'] ?? null
                    );
                } else {
                    $response = [
                        'type' => 'text',
                        'text' => ['body' => 'Por favor, escribe solo un número entre 1 y 49 (o escribe *cancelar*).'],
                    ];
                }
                $processHandled = true;
            }

            // Escape valve: si el carrito tiene un paso de checkout pendiente
            // (sucursal, tipo de pedido, delivery, nota o pago) y el cliente
            // escribe "cancelar", cancelamos en vez de dejarlo colgado.
            if ($cart && in_array($normalizedText, ['cancelar', 'salir'], true) && $this->cartHasPendingCheckoutStep($cart)) {
                Log::info('[handleTextMessage] ❌ Cancelación por escape valve', ['cart_id' => $cart->id]);
                $response = $this->cancelarPedido($contact, $cart->id);
                $processHandled = true;
            }

            // Paso 1 de delivery: dirección de entrega (texto libre, ya no se
            // pide ubicación GPS).
            if (! $processHandled && $cart && ! empty($cart->metadata['awaiting_delivery_address'] ?? false)) {
                Log::info('[handleTextMessage] 🗺️ Dirección de entrega recibida', ['cart_id' => $cart->id]);

                $metadata = $cart->metadata ?? [];
                unset($metadata['awaiting_delivery_address']);
                $metadata['delivery_location'] = ['manual_address' => trim($text)];
                $metadata['awaiting_delivery_recipient_name'] = true;
                $cart->metadata = $metadata;
                $cart->save();

                $response = $this->buildRecipientNamePrompt($contact);
                $processHandled = true;
            }

            // Paso 2 de delivery: nombre de quien recibe (respuesta de texto
            // libre — el botón "Otro nombre" cae aquí; el botón con el
            // nombre del propio contacto se resuelve en handleInteractiveMessage).
            // El costo de envío NO se suma al total todavía (solo se guarda
            // un estimado referencial para prellenar el panel): el vendedor
            // lo confirma desde el módulo de Pedidos y recién ahí se suma al
            // total y se le avisa al cliente (ver OrderLifecycleService::sendFulfillmentCostsMessage).
            if (! $processHandled && $cart && ! empty($cart->metadata['awaiting_delivery_recipient_name'] ?? false)) {
                Log::info('[handleTextMessage] 🧑 Nombre de receptor de delivery recibido', ['cart_id' => $cart->id]);

                $response = $this->applyDeliveryRecipientName($contact, $cart, $text);
                $processHandled = true;
            }

            // Si el pedido está esperando el comprobante de pago, no debe caer
            // en respuestas genéricas (menú, IA, etc.): solo puede enviar el
            // comprobante (imagen/PDF) o cancelar. Se consulta el estado en
            // vivo, así que si el equipo ya cambió el estado desde el panel
            // (lo confirmó manualmente, por ejemplo), este bloqueo deja de
            // aplicar automáticamente.
            if (! $processHandled) {
                $awaitingProofCart = $this->findCartPendingProofUpload($contact);
                if ($awaitingProofCart) {
                    if (in_array($normalizedText, ['cancelar', 'salir'], true)) {
                        Log::info('[handleTextMessage] ❌ Cancelación de pedido pendiente de comprobante', ['cart_id' => $awaitingProofCart->id]);
                        $response = $this->cancelarPedido($contact, $awaitingProofCart->id);
                    } else {
                        $orderNumber = $awaitingProofCart->getOrderNumber();
                        $response = [
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'button',
                                'body' => ['text' => "🕐 Seguimos esperando el comprobante de pago de tu pedido *{$orderNumber}*.\n\n".
                                    'Envía la imagen o PDF del comprobante, o toca el botón si prefieres cancelar el pedido.'],
                                'action' => [
                                    'buttons' => [
                                        ['type' => 'reply', 'reply' => ['id' => 'cancelar_pedido_'.$awaitingProofCart->id, 'title' => '❌ Cancelar pedido']],
                                    ],
                                ],
                            ],
                        ];
                    }
                    $processHandled = true;
                }
            }

            if (! $processHandled && $cart && isset($cart->metadata['pending_note']) && $cart->metadata['pending_note']) {
                // Si hay un carrito esperando nota, procesar el mensaje como nota del pedido
                Log::info('[handleTextMessage] 📝 Procesando nota para pedido', [
                    'cart_id' => $cart->id,
                    'note' => $text,
                ]);

                // Actualizar la nota del carrito y continuar con el proceso de compra
                $cart->note = $text;
                $metadata = $cart->metadata ?? [];
                unset($metadata['pending_note']);
                $cart->metadata = $metadata;
                $cart->save();

                // Continuar con el proceso de finalización de compra
                $response = $this->finalizarCompra($contact);
                $processHandled = true;
            }

            // Si el carrito tiene un paso de checkout pendiente que solo se
            // resuelve con un botón/lista (sucursal, llevar/servir,
            // retiro/delivery, método de pago) y el cliente escribe texto
            // libre en vez de tocar una opción, no lo dejamos "perderse" en
            // el menú genérico: se le reenvía el mismo paso pendiente. Si el
            // texto es un saludo u otra palabra común reconocible, se
            // reconoce con un mensaje acorde (en vez del genérico "no
            // entendí") pero sin abandonar el paso pendiente.
            if (! $processHandled && $cart && $this->cartHasPendingCheckoutStep($cart) && ! $this->isAgentRequestText($text)) {
                Log::info('[handleTextMessage] 🔁 Reenviando paso de checkout pendiente (texto no reconocido)', [
                    'cart_id' => $cart->id,
                ]);

                $pendingStep = $this->finalizarCompra($contact);
                if (is_array($pendingStep) && ($pendingStep['type'] ?? null) === 'interactive') {
                    $intro = $this->matchCommonIntentReply($text)
                        ?? '🙏 No entendí ese mensaje. Elige una opción de arriba para continuar:';
                    $pendingStep['interactive']['body']['text'] =
                        $intro."\n\n".($pendingStep['interactive']['body']['text'] ?? '');
                }
                $response = $pendingStep;
                $processHandled = true;
            }

            // Si no se procesó como nota, verificar si es un SKU de producto
            if (! $processHandled) {
                // Solo buscar productos si el mensaje no es una respuesta común y tiene más de 2 caracteres
                $searchTerm = $this->normalizeProductSearchTerm($text);
                if (! in_array(strtolower($searchTerm), $commonResponses) && strlen($searchTerm) > 2) {
                    // Buscar producto por SKU o nombre en la base de datos
                    $demoCliente = app(DemoClienteService::class);
                    $product = $demoCliente->applyProductScope(
                        WhatsappPrice::where('business_profile_id', $this->businessProfile?->id)
                            ->where(function ($query) use ($searchTerm) {
                                $query->where('sku', 'like', '%'.$searchTerm.'%')
                                    ->orWhere('name', 'like', '%'.$searchTerm.'%');
                            })
                    )
                        ->where('is_active', true)
                        ->first();

                    if ($product) {
                        // Si se encuentra el producto, mostrar sus detalles
                        Log::info('[handleTextMessage] 🔍 Producto encontrado', [
                            'search_term' => $searchTerm,
                            'original_text' => $text,
                            'product_id' => $product->id,
                        ]);
                        $response = $this->getProductDetails($product->id, $contact);
                        $processHandled = true;
                    }
                }
            }

            // Si no se procesó como SKU, generar respuesta del chatbot
            if (! $processHandled) {
                if ($this->isAgentRequestText($text)) {
                    if ($messageId) {
                        $this->sendTypingIndicator($messageId);
                    }
                    $this->triggerAgentHandoff($contact, $from, 'text');

                    Log::info('[handleTextMessage] ✅ Solicitud de asesor por texto', [
                        'contact_id' => $contact->id,
                        'phone' => substr($from, 0, 4).'****'.substr($from, -4),
                    ]);

                    return;
                }

                // Refrescar el contacto desde la base de datos para obtener el valor actualizado de bot_enabled
                $contact->refresh();

                // Verificar si el bot está habilitado para este contacto ANTES de generar respuesta
                if (! $this->botMayRespondToContact($contact)) {
                    $this->logBotBlocked('handleTextMessage', $contact, [
                        'phone' => substr($from, 0, 4).'****'.substr($from, -4),
                        'message_content' => substr($text, 0, 100),
                    ]);
                    // No generar ni enviar respuesta automática
                    $response = null;
                } else {
                    $willAutoReply = true;
                    // Typing mientras se genera la respuesta (IA, menús, etc.)
                    if ($messageId) {
                        $this->sendTypingIndicator($messageId);
                    }
                    $response = $this->generateChatbotResponse($text, $from);
                }
            } elseif ($response) {
                $willAutoReply = true;
            }

            if ($willAutoReply && $response) {
                // Typing justo antes de enviar (tras generar la respuesta), igual que el panel humano
                $this->prepareBotReply($contact, $messageId);
                $this->sendMessage($from, $response);
            } else {
                $this->markMessageAsRead($messageId, $from);
            }

            // Registrar el procesamiento exitoso del mensaje
            Log::info('[handleTextMessage] ✅ Mensaje de texto procesado', [
                'id' => $messageId,
                'contacto' => substr($from, 0, 4).'****'.substr($from, -4),
                'nombre' => $contact->name,
            ]);

        } catch (\Exception $e) {
            // Registrar cualquier error que ocurra durante el procesamiento
            Log::error('[handleTextMessage] ❌ Error al procesar mensaje de texto', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'mensaje' => $message,
            ]);
        }
    }

    private function handleImageMessage($message)
    {
        try {
            // Validar datos requeridos
            if (empty($message['from']) || empty($message['id'])) {
                Log::warning('⚠️ Datos básicos de mensaje incompletos', [
                    'tiene_from' => ! empty($message['from']),
                    'tiene_id' => ! empty($message['id']),
                ]);

                return;
            }

            // Verificar si el mensaje ya fue procesado
            $existingMessage = WhatsappMessage::where('message_id', $message['id'])->first();
            if ($existingMessage) {
                Log::info('⏭️ Mensaje de imagen ya procesado anteriormente', [
                    'message_id' => $message['id'],
                ]);

                return;
            }

            // Obtener datos del contacto
            $contactData = $message['contacts'][0] ?? [];
            $profile = $contactData['profile'] ?? [];
            $contactName = $profile['name'] ?? 'Contacto sin nombre';
            $waId = $contactData['wa_id'] ?? null;

            // Crear o actualizar contacto
            $contact = $this->findContactByPhone($message['from']);
            if (! $contact) {
                // Crear nuevo contacto con el nombre del webhook
                $contact = WhatsappContact::create([
                    'business_profile_id' => $this->businessProfile->id,
                    'phone_number' => $message['from'],
                    'name' => $contactName,
                    'status' => 'active',
                ]);

                Log::info('✅ Nuevo contacto creado', [
                    'phone' => $message['from'],
                    'contact_id' => $contact->id,
                    'name' => $contactName,
                ]);
            } elseif ($contact->name === 'Contacto sin nombre') {
                // Si el contacto existe pero tiene nombre genérico, intentar actualizarlo
                if ($contactName && $contactName !== 'Contacto sin nombre') {
                    $contact->name = $contactName;
                    $contact->save();

                    Log::info('✅ Nombre de contacto actualizado', [
                        'phone' => $message['from'],
                        'contact_id' => $contact->id,
                        'old_name' => 'Contacto sin nombre',
                        'new_name' => $contactName,
                    ]);
                }
            }

            $proofCart = $this->findCartPendingProofUpload($contact);
            if ($proofCart) {
                $response = $this->registrarComprobantePago($contact, $proofCart, $message, 'image');
                if ($response) {
                    $this->sendMessage($message['from'], $response);
                }

                return;
            }

            // Verificar si hay un proceso activo
            if ($this->isProcessActive($contact)) {
                Log::warning('⚠️ Imagen recibida durante proceso activo', [
                    'contact_id' => $contact->id,
                    'message_id' => $message['id'],
                ]);

                // Guardar la imagen temporalmente en metadata del último mensaje
                $incomingImage = is_array($message['image'] ?? null)
                    ? $message['image']
                    : (is_array($message['text'] ?? null) ? $message['text'] : []);
                $imageData = [
                    'image_id' => $incomingImage['id'] ?? $incomingImage['image_id'] ?? null,
                    'mime_type' => $incomingImage['mime_type'] ?? null,
                    'sha256' => $incomingImage['sha256'] ?? null,
                    'caption' => $incomingImage['caption'] ?? null,
                ];

                // Enviar mensaje de confirmación con opciones
                $response = [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => "⚠️ *Proceso en curso*\n\n".
                                "Tienes un proceso activo que necesita ser completado.\n\n".
                                "¿Qué deseas hacer?\n\n".
                                "1️⃣ Cancelar el proceso actual y procesar la imagen\n".
                                '2️⃣ Continuar con el proceso actual',
                        ],
                        'action' => [
                            'buttons' => [
                                [
                                    'type' => 'reply',
                                    'reply' => [
                                        'id' => 'cancelar_proceso_imagen',
                                        'title' => '📸 Procesar imagen',
                                    ],
                                ],
                                [
                                    'type' => 'reply',
                                    'reply' => [
                                        'id' => 'continuar_proceso',
                                        'title' => '⏳ Continuar proceso',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                // Guardar el mensaje de imagen temporalmente
                $whatsappMessage = WhatsappMessage::create([
                    'contact_id' => $contact->id,
                    'business_profile_id' => $this->businessProfile->id,
                    'message_id' => $message['id'],
                    'sender_type' => 'client',
                    'receiver_type' => 'system',
                    'content' => $imageData['caption'] ?? '',
                    'type' => 'image',
                    'status' => 'pending',
                    'metadata' => [
                        'timestamp' => $message['timestamp'],
                        'wa_id' => $waId,
                        'is_pending_confirmation' => true,
                        'media_id' => $imageData['image_id'],
                        'mime_type' => $imageData['mime_type'],
                        'sha256' => $imageData['sha256'],
                        'caption' => $imageData['caption'],
                    ],
                ]);

                $this->sendMessage($message['from'], $response);

                return;
            }

            // Si no hay proceso activo, continuar con el procesamiento normal de la imagen
            // Extraer datos de la imagen del mensaje
            $incomingImage = is_array($message['image'] ?? null)
                ? $message['image']
                : (is_array($message['text'] ?? null) ? $message['text'] : []);
            $imageData = [
                'image_id' => $incomingImage['id'] ?? $incomingImage['image_id'] ?? null,
                'mime_type' => $incomingImage['mime_type'] ?? null,
                'sha256' => $incomingImage['sha256'] ?? null,
                'caption' => $incomingImage['caption'] ?? null,
            ];

            // Log para depuración
            Log::info('📸 Datos de imagen recibidos', [
                'image_id' => $imageData['image_id'],
                'mime_type' => $imageData['mime_type'],
                'sha256' => $imageData['sha256'],
            ]);

            // Verificar datos de la imagen
            if (empty($imageData['image_id']) || empty($imageData['mime_type'])) {
                Log::error('❌ Datos de imagen incompletos', [
                    'tiene_id' => ! empty($imageData['image_id']),
                    'tiene_mime_type' => ! empty($imageData['mime_type']),
                    'datos' => $imageData,
                    'mensaje_original' => $message,
                ]);

                return;
            }

            // Crear mensaje de imagen
            $whatsappMessage = WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $message['id'],
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'content' => $imageData['caption'] ?? '',
                'type' => 'image',
                'status' => 'received',
                'metadata' => [
                    'timestamp' => $message['timestamp'],
                    'wa_id' => $waId,
                    'media_id' => $imageData['image_id'],
                    'mime_type' => $imageData['mime_type'],
                    'sha256' => $imageData['sha256'],
                    'caption' => $imageData['caption'],
                ],
            ]);

            // Establecer como último mensaje
            $this->lastMessage = $whatsappMessage;

            // Nota: este flujo antes marcaba el mensaje como "pending_note" y
            // pedía una descripción de texto, pero ningún código llegaba a leer
            // esa respuesta (handleTextMessage solo procesa pending_note de
            // carritos en checkout). Eso dejaba a cualquiera que enviara una
            // imagen fuera de un pedido con isProcessActive() en true para
            // siempre, atrapado en el aviso de "proceso en curso" en su
            // siguiente foto. Se simplifica a un simple acuse de recibo.
            $response = [
                'type' => 'text',
                'text' => [
                    'body' => '📸 Imagen recibida, ¡gracias!',
                ],
            ];

            $this->sendMessage($message['from'], $response);

            Log::info('✅ Mensaje de imagen procesado', [
                'id' => $message['id'],
                'contacto' => substr($message['from'], 0, 4).'****'.substr($message['from'], -4),
                'nombre' => $contactName,
                'image_id' => $imageData['image_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error procesando mensaje de imagen', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'mensaje' => $message,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function saveMessage(array $messageData)
    {
        try {
            // Verificar si el mensaje ya existe
            $messageId = is_array($messageData['message_id'])
                ? $messageData['message_id']['message_id']
                : $messageData['message_id'];

            $existingMessage = WhatsappMessage::where('message_id', $messageId)->first();
            if ($existingMessage) {
                Log::info('⏭️ Mensaje ya guardado', ['id' => $messageId]);

                return $existingMessage;
            }

            // Obtener datos del contacto
            $contact = WhatsappContact::find($messageData['contact_id']);
            if (! $contact) {
                Log::error('❌ Contacto no encontrado', ['contact_id' => $messageData['contact_id']]);

                return null;
            }

            // Determinar el contenido del mensaje según el tipo
            $content = '';
            if ($messageData['type'] === 'text') {
                $content = $messageData['content'] ?? '';
            } elseif ($messageData['type'] === 'interactive') {
                $interactiveContent = json_decode($messageData['content'], true);
                if (isset($interactiveContent['button_reply'])) {
                    $content = $interactiveContent['button_reply']['title'] ?? '';
                } elseif (isset($interactiveContent['list_reply'])) {
                    $content = $interactiveContent['list_reply']['title'] ?? '';
                } elseif (isset($interactiveContent['interactive'])) {
                    $content = $interactiveContent['interactive']['body']['text'] ?? '';
                }
            }

            // Crear mensaje
            $whatsappMessage = WhatsappMessage::create([
                'contact_id' => $messageData['contact_id'],
                'business_profile_id' => $contact->business_profile_id,
                'message_id' => $messageId,
                'sender_type' => $messageData['from'] === $contact->phone_number ? 'client' : 'system',
                'receiver_type' => $messageData['from'] === $contact->phone_number ? 'system' : 'client',
                'content' => $content,
                'type' => $messageData['type'],
                'status' => 'received',
                'metadata' => [
                    'timestamp' => $messageData['timestamp'] ?? null,
                    'raw_message' => $messageData,
                ],
            ]);

            Log::info('✅ Mensaje guardado correctamente', [
                'id' => $messageId,
                'contacto' => substr($contact->phone_number, 0, 4).'****'.substr($contact->phone_number, -4),
                'nombre' => $contact->name,
            ]);

            return $whatsappMessage;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando mensaje', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'mensaje' => $messageData,
            ]);

            return null;
        }
    }

    private function getRemainingProducts(?int $categoryId = null, int $skip = 0)
    {
        try {
            $demoCliente = app(DemoClienteService::class);

            if ($categoryId) {
                $item = $this->findCatalogCategory($categoryId);
                if (! $item) {
                    return [
                        'type' => 'text',
                        'text' => ['body' => 'Lo siento, no encontramos esa categoría.'],
                    ];
                }

                $prices = $demoCliente->applyProductScope(
                    $item->prices()->where('is_active', true)->where('stock', '>', 0)
                )->orderBy('name')->get();

                if ($skip > 0) {
                    $prices = $prices->slice($skip)->values();
                }

                if ($prices->isEmpty()) {
                    return [
                        'type' => 'text',
                        'text' => ['body' => 'No hay más productos en esta categoría.'],
                    ];
                }

                $message = "📋 *Más productos — {$item->title}*\n\n";
                $products = [];
                $number = 1;

                foreach ($prices as $price) {
                    $priceText = $price->is_promo
                        ? '💰 $'.number_format($price->promo_price, 2).' (Oferta)'
                        : '💰 $'.number_format($price->price, 2);

                    $message .= "*[SKU: {$price->sku}] {$price->name}*\n";
                    if ($price->description) {
                        $message .= '   '.Str::limit($price->description, 72, '...')."\n";
                    }
                    $message .= "   {$priceText}\n\n";

                    $products[$number] = [
                        'price' => $price,
                        'sku' => $price->sku,
                    ];
                    $number++;
                }

                if ($this->lastMessage) {
                    $metadata = $this->lastMessage->metadata ?? [];
                    $metadata['product_list'] = $products;
                    $metadata['catalog_category_id'] = $categoryId;
                    $this->lastMessage->metadata = $metadata;
                    $this->lastMessage->save();
                }

                $exampleSku = $products[1]['sku'] ?? 'CQ001';
                $message .= "Para ver el detalle, *escriba el SKU* del producto (ej.: *{$exampleSku}*).";

                return [
                    'type' => 'text',
                    'text' => ['body' => $message],
                ];
            }

            $menu = $this->menuByActionId('prices_menu');

            if (! $menu) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no hay más productos disponibles.'],
                ];
            }

            $demoCliente = app(DemoClienteService::class);
            $menuItems = $demoCliente->scopeCategoriesWithVisibleProducts(
                $demoCliente->applyCategoryScope(
                    $menu->items()->where('is_active', true)
                )
            )->orderBy('order')->get();

            if ($menuItems->isEmpty()) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no hay más productos disponibles.'],
                ];
            }

            $message = "📋 *Lista Completa de Productos*\n\n";
            $products = [];
            $number = 1;

            foreach ($menuItems as $item) {
                $prices = $demoCliente->applyProductScope(
                    $item->prices()->where('is_active', true)->where('stock', '>', 0)
                )->orderBy('name')->get();

                if ($prices->isEmpty()) {
                    continue;
                }
                // COLOQUEMOS EN MAYUSCULA EL TITULO
                $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $message .= '          *'.strtoupper($item->title)."*\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
                foreach ($prices as $price) {
                    $priceText = $price->is_promo
                        ? '💰 $'.number_format($price->promo_price, 2).' (Oferta)'
                        : '💰 $'.number_format($price->price, 2);

                    // Incluir SKU en la lista
                    $message .= "*[SKU: {$price->sku}] {$price->name}*\n";
                    /*  if ($price->description) {
                         $message .= "   " . Str::limit($price->description, 50, '...') . "\n";
                     } */
                    $message .= "   {$priceText}\n\n";

                    // Guardar el producto en el array con su número y SKU
                    $products[$number] = [
                        'price' => $price,
                        'sku' => $price->sku,
                    ];
                    $number++;
                }
            }

            if (empty($products)) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no hay productos disponibles en este momento.'],
                ];
            }

            // Guardar la lista de productos en el último mensaje para referencia
            if ($this->lastMessage) {
                $metadata = $this->lastMessage->metadata ?? [];
                $metadata['product_list'] = $products;
                $this->lastMessage->metadata = $metadata;
                $this->lastMessage->save();
            }

            $message .= "Para seleccionar un producto, puedes:\n";

            $message .= "Escribir el SKU del producto (ej: {$products[1]['sku']})";

            return [
                'type' => 'text',
                'text' => ['body' => $message],
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error al obtener precios restantes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al cargar los productos.'],
            ];
        }
    }

    /** @return array<int, array{title: string, price: float}> */
    private function productVariations(WhatsappPrice $product): array
    {
        $variations = is_array($product->metadata) ? ($product->metadata['variations'] ?? []) : [];

        return collect($variations)
            ->filter(fn ($variation) => is_array($variation) && ! empty($variation['title']) && isset($variation['price']) && is_numeric($variation['price']))
            ->map(fn ($variation) => [
                'title' => trim((string) $variation['title']),
                'price' => (float) $variation['price'],
            ])
            ->values()
            ->all();
    }

    private function productVariation(WhatsappPrice $product, ?int $index): ?array
    {
        if ($index === null) {
            return null;
        }

        return $this->productVariations($product)[$index] ?? null;
    }

    /** Configuración del Flow nativo de pedido rápido, editable en el panel. */
    private function quickOrderFlowConfig(): array
    {
        return $this->getMarketingStep(MarketingStepKey::PRODUCTS_MENU)?->config['quick_order_flow'] ?? [];
    }

    private function buildQuickOrderFlowPayload(int $productId, WhatsappContact $contact): array
    {
        $product = $this->findCatalogProduct($productId);
        $flow = $this->quickOrderFlowConfig();

        if (! $product || empty($flow['flow_id'])) {
            // No retroceder al selector de cantidad legado si falta un Flow.
            // La compra rápida siempre agrega una unidad al carrito.
            return $product ? $this->addToCart($contact, $product->id, 1) : [
                'type' => 'text',
                'text' => ['body' => 'No encontramos este producto. Vuelve a abrir el menú para continuar.'],
            ];
        }

        return WhatsappMessagePayload::flow(
            "*{$product->name}* 🍗\n\nPersonaliza tu pedido, elige entrega o retiro y confírmalo en un solo formulario.",
            (string) $flow['flow_id'],
            (string) ($flow['flow_token'] ?? 'dpikeos_quick_order'),
            (string) ($flow['cta'] ?? 'Pedir ahora'),
            '3',
            'navigate',
            [
                'screen' => 'ORDER',
                'data' => [
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'base_price' => (string) $product->price,
                ],
            ]
        );
    }

    /**
     * Convierte el carrito nativo enviado desde el catálogo de Meta en un
     * pedido del panel. Los importes se vuelven a calcular localmente.
     */
    private function handleNativeCatalogOrder(array $message): void
    {
        $from = (string) $message['from'];
        $messageId = (string) $message['id'];
        $order = $message['order'] ?? [];
        $items = $order['product_items'] ?? [];

        if (! is_array($items) || $items === []) {
            Log::warning('[Catálogo Meta] carrito recibido sin productos', ['message_id' => $messageId]);

            return;
        }

        $contactName = $message['contacts'][0]['profile']['name'] ?? 'Contacto sin nombre';
        $contact = WhatsappContact::firstOrCreate(
            ['phone_number' => $from, 'business_profile_id' => $this->businessProfile->id],
            [
                'name' => $contactName,
                'status' => 'active',
            ]
        );

        WhatsappMessage::create([
            'contact_id' => $contact->id,
            'business_profile_id' => $this->businessProfile->id,
            'message_id' => $messageId,
            'content' => 'Carrito enviado desde el catálogo de WhatsApp',
            'type' => 'order',
            'status' => 'received',
            'sender_type' => 'client',
            'receiver_type' => 'system',
            'metadata' => ['meta_order' => $order],
        ]);

        $cart = null;
        DB::transaction(function () use ($contact, $items, $order, $messageId, &$cart) {
            $cart = WhatsappCart::create([
                'contact_id' => $contact->id,
                'status' => WhatsappCart::STATUS_PENDING,
                'total' => 0,
                'note' => $order['text'] ?? null,
                'metadata' => [
                    'source' => 'meta_catalog',
                    'meta_catalog_id' => $order['catalog_id'] ?? null,
                    'meta_message_id' => $messageId,
                ],
            ]);

            $turnNumber = app(DailyOrderNumberService::class)->assign($cart);
            $metadata = $cart->metadata ?? [];
            $metadata['order_details'] = ['order_number' => 'ORD-'.$turnNumber, 'turn_number' => $turnNumber];
            $cart->metadata = $metadata;
            $cart->save();

            foreach ($items as $item) {
                $retailerId = strtoupper(trim((string) ($item['product_retailer_id'] ?? '')));
                $product = WhatsappPrice::where('sku', $retailerId)->where('business_profile_id', $this->businessProfile?->id)->where('is_active', true)->first();
                if (! $product) {
                    Log::warning('[Catálogo Meta] producto no vinculado al panel', ['retailer_id' => $retailerId]);

                    continue;
                }

                $quantity = max(1, min((int) ($item['quantity'] ?? 1), max(1, (int) $product->max_quantity)));
                $unitPrice = (float) ($product->is_promo ? $product->promo_price : $product->price);

                $cart->items()->create([
                    'whatsapp_price_id' => $product->id,
                    'name' => $product->name,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_note' => 'Carrito de WhatsApp',
                ]);
            }

            $cart->total = $cart->items()->sum(DB::raw('price * quantity'));
            $cart->save();
        });

        if (! $cart || $cart->items()->count() === 0) {
            $cart?->delete();
            $this->sendMessage($from, ['type' => 'text', 'text' => ['body' => 'No pudimos relacionar los productos de tu carrito. Escríbenos y te ayudamos de inmediato.']]);

            return;
        }

        $summary = $cart->items()->get()->map(fn ($line) => "• {$line->quantity} × {$line->name}")->implode("\n");
        $greeting = ($community = $this->scopedChatbotConfig()?->community_name)
            ? "✅ *¡Recibimos tu pedido!* Gracias por ser parte de {$community}."
            : '✅ *¡Recibimos tu pedido!*';
        $this->sendMessage($from, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "{$greeting}\n\n{$summary}\n\n*Total:* $".number_format((float) $cart->total, 2)."\n\nAhora confirma cómo deseas recibirlo."],
                'action' => ['buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'checkout', 'title' => '✅ Continuar pedido']],
                    ['type' => 'reply', 'reply' => ['id' => 'menu_productos', 'title' => '➕ Agregar más']],
                    ['type' => 'reply', 'reply' => ['id' => 'agent', 'title' => '💬 Hablar con asesor']],
                ]],
            ],
        ]);

        Log::info('[Catálogo Meta] pedido creado desde carrito nativo', ['cart_id' => $cart->id, 'total' => $cart->total]);
    }

    /**
     * Convierte la salida nativa de un WhatsApp Flow en un pedido real.
     * Contrato esperado: product_sku, quantity, variation, extras[], fulfilment,
     * address, payment_method y customer_note. Los precios siempre se recalculan
     * con el catálogo local; nunca se confía en importes enviados por el cliente.
     */
    private function handleNativeFlowReply(WhatsappContact $contact, string $from, array $interactive, string $messageId): void
    {
        $raw = $interactive['nfm_reply']['response_json'] ?? '{}';
        $data = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($data)) {
            Log::warning('[WhatsApp Flow] respuesta JSON inválida', ['message_id' => $messageId]);
            $this->sendMessage($from, ['type' => 'text', 'text' => ['body' => 'No pudimos leer tu pedido. Por favor, vuelve a abrir el menú e inténtalo otra vez.']]);

            return;
        }

        $lines = $data['items'] ?? [$data];
        if (is_string($lines)) {
            $lines = json_decode($lines, true) ?: [];
        }
        if (! is_array($lines) || $lines === []) {
            $this->sendMessage($from, ['type' => 'text', 'text' => ['body' => 'Tu pedido no incluye productos. Abre el menú y elige tu combo favorito.']]);

            return;
        }

        $cart = null;
        DB::transaction(function () use ($contact, $lines, $data, $messageId, &$cart) {
            $cart = WhatsappCart::firstOrCreate(
                ['contact_id' => $contact->id, 'status' => 'active'],
                ['total' => 0]
            );

            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $sku = strtoupper(trim((string) ($line['product_sku'] ?? $line['sku'] ?? '')));
                $product = WhatsappPrice::where('sku', $sku)->where('business_profile_id', $this->businessProfile?->id)->where('is_active', true)->first();
                if (! $product) {
                    continue;
                }

                $quantity = max(1, min((int) ($line['quantity'] ?? 1), max(1, (int) $product->max_quantity)));
                $variationName = trim((string) ($line['variation'] ?? ''));
                $variation = collect($this->productVariations($product))->firstWhere('title', $variationName);
                $unitPrice = (float) ($variation['price'] ?? ($product->is_promo ? $product->promo_price : $product->price));

                $extras = $line['extras'] ?? [];
                if (is_string($extras)) {
                    $extras = array_filter(array_map('trim', explode(',', $extras)));
                }
                $extras = is_array($extras) ? $extras : [];
                $availableExtras = collect($product->metadata['extras'] ?? [])->keyBy('title');
                $extraNames = [];
                foreach ($extras as $extraName) {
                    $extra = $availableExtras->get((string) $extraName);
                    if ($extra) {
                        $extraNames[] = $extra['title'];
                        $unitPrice += (float) ($extra['price'] ?? 0);
                    }
                }

                $noteParts = array_filter([
                    $variation ? 'Variación: '.$variation['title'] : null,
                    $extraNames ? 'Extras: '.implode(', ', $extraNames) : null,
                ]);
                $lineNote = implode(' · ', $noteParts) ?: null;
                $cart->items()->create([
                    'whatsapp_price_id' => $product->id,
                    'name' => $product->name,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_note' => $lineNote,
                ]);
            }

            $cart->total = $cart->items()->sum(DB::raw('price * quantity'));
            $metadata = $cart->metadata ?? [];
            $metadata['flow_order'] = [
                'submitted_at' => now()->toIso8601String(),
                'message_id' => $messageId,
                'fulfilment' => $data['fulfilment'] ?? $data['delivery_method'] ?? null,
                'address' => $data['address'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
            ];
            $cart->metadata = $metadata;
            $cart->note = trim((string) ($data['customer_note'] ?? $cart->note));
            $cart->payment_method = $data['payment_method'] ?? null;
            $cart->save();
        });

        if (! $cart->items()->exists()) {
            $this->sendMessage($from, ['type' => 'text', 'text' => ['body' => 'Algunos productos ya no están disponibles. Abre el menú para elegir una opción vigente.']]);

            return;
        }

        $orderNumber = $this->finalizeBulkWebOrder($cart);
        $cart->refresh();
        $community = $this->scopedChatbotConfig()?->community_name;
        $greeting = $community ? "¡Pedido recibido! Gracias por ser parte de {$community}. ✨" : '¡Pedido recibido! ✨';
        $this->sendMessage($from, [
            'type' => 'text',
            'text' => ['body' => "{$greeting}\n\n*N.º {$orderNumber}*\nTotal: *$".number_format((float) $cart->total, 2)."*\n\nEl equipo confirmará disponibilidad, preparación y entrega por este chat."],
        ]);
    }

    private function getProductDetails($productId, ?WhatsappContact $contact = null)
    {
        try {
            $price = $this->findCatalogProduct($productId);
            if (! $price || ! $price->is_active) {
                // Sin el filtro is_active, un id numérico adivinado (ej. tocando
                // "3" a mano) permitía ver la ficha de productos deshabilitados
                // o descontinuados que ya no deberían mostrarse al cliente.
                Log::warning('❌ Producto no encontrado o inactivo', ['id' => $productId]);

                return [
                    'type' => 'text',
                    'text' => ['body' => 'Ese producto ya no está disponible. Escribe *menú* para ver las opciones vigentes.'],
                ];
            }

            // La imagen es el protagonista; el texto funciona como una ficha
            // corta y escaneable, no como una lista técnica de especificaciones.
            $description = trim((string) $price->description);
            $message = "*{$price->name}*\n";
            if ($description !== '') {
                $message .= "🍗 {$description}\n";
            }

            // Agregar características del producto
            if ($price->characteristics) {
                $characteristics = is_string($price->characteristics)
                    ? json_decode($price->characteristics, true)
                    : $price->characteristics;

                if (is_array($characteristics) && ! empty($characteristics)) {
                    $message .= "\n*Incluye*\n";
                    foreach ($characteristics as $characteristic) {
                        $message .= "• {$characteristic}\n";
                    }
                    $message .= "\n";
                }
            }

            $variations = $this->productVariations($price);
            if ($variations !== []) {
                $fromPrice = min(array_column($variations, 'price'));
                $message .= "\n*Desde $".number_format((float) $fromPrice, 2).'*';
            } elseif ($price->is_promo && $price->promo_price) {
                $message .= "\n*Precio de oferta: \${$price->promo_price}*\n";
                if ($price->promo_end_date) {
                    $message .= 'Válido hasta '.date('d/m/Y', strtotime($price->promo_end_date))."\n";
                }
                $message .= "Precio regular: ~\${$price->price}~";
            } else {
                $message .= "\n*Precio: \${$price->price}*";
            }

            // Ruta principal: compra en un toque. El Flow queda reservado para
            // personalizaciones opcionales; no obliga al cliente a repetir una
            // elección que ya hizo en el menú. Si el producto permite elegir
            // cantidad (allow_quantity_selection), el botón no agrega de una
            // vez: primero pregunta cuántas unidades, igual que en la web.
            $askQuantity = (bool) $price->allow_quantity_selection;
            $buttons = [];
            if (count($variations) <= 2) {
                foreach ($variations as $index => $variation) {
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => ($askQuantity ? 'pedir_cantidad_' : 'quick_add_').$productId.'_'.$index,
                            'title' => Str::limit('🛒 '.$variation['title'].' $'.number_format((float) $variation['price'], 2), 20, ''),
                        ],
                    ];
                }
            }

            if ($variations === []) {
                $quickPrice = (float) ($price->is_promo && $price->promo_price ? $price->promo_price : $price->price);
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => ($askQuantity ? 'pedir_cantidad_' : 'quick_add_').$productId.'_base',
                        'title' => Str::limit('🛒 Agregar $'.number_format($quickPrice, 2), 20, ''),
                    ],
                ];
            }

            // Una lista solo se abre cuando hay demasiadas variantes para los
            // botones. No se usa para repetir dos opciones que ya están visibles.
            if (count($variations) > 2) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'personalizar_'.$productId,
                        'title' => 'Ver opciones',
                    ],
                ];
            }

            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => 'ver_carrito',
                    'title' => $this->cartButtonTitle($this->cartItemCount($contact)),
                ],
            ];
            // validar que los titulos no sean mas de 20 caracteres -- por
            // caracteres reales (mb_strlen), no bytes: strlen() con emojis u
            // otros caracteres multibyte contaba de más y cortaba el título a
            // la mitad (perdiendo hasta el emoji), aunque ya entrara bien en
            // el límite real de WhatsApp.
            foreach ($buttons as &$button) {
                if (mb_strlen($button['reply']['title']) > 20) {
                    $button['reply']['title'] = mb_substr($button['reply']['title'], 0, 17).'...';
                }
            }
            unset($button);

            // validar que los botones no sean mas de 3
            if (count($buttons) > 3) {
                $buttons = array_slice($buttons, 0, 3);
            }

            $interactive = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'buttons' => $buttons,
                    ],
                ],
            ];

            // Si hay una imagen, agregarla como header. Sin imagen propia del
            // producto, el mensaje simplemente no lleva header (nunca cae al
            // logo de otra empresa como reemplazo).
            $imageUrl = app(ProductImageService::class)->resolveUrl($price->image);
            if ($imageUrl) {
                $interactive['interactive']['header'] = [
                    'type' => 'image',
                    'image' => [
                        'link' => $imageUrl,
                    ],
                ];
            }

            return $interactive;
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del producto', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
            ]);

            return null;
        }
    }

    // Agregar nuevo método para mostrar la selección de cantidad
    private function showVariationSelection($productId, ?WhatsappContact $contact = null)
    {
        $price = $this->findCatalogProduct($productId);
        if (! $price) {
            return null;
        }

        $variations = $this->productVariations($price);
        if ($variations === []) {
            // Ruta defensiva para mensajes heredados: jamás abrir el selector
            // de cantidad. Volvemos a la ficha de compra directa.
            return $this->getProductDetails($productId, $contact);
        }

        $rows = [];
        foreach (array_slice($variations, 0, 8, true) as $index => $variation) {
            $rows[] = [
                'id' => "variacion_{$productId}_{$index}",
                'title' => Str::limit($variation['title'], 24, ''),
                'description' => '$'.number_format((float) $variation['price'], 2),
            ];
        }

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => "*{$price->name}*\n\nElige cómo lo quieres:"],
                'action' => ['button' => 'Elegir opción', 'sections' => [['title' => 'Variaciones', 'rows' => $rows]]],
            ],
        ];
    }

    private function showQuantitySelection(WhatsappContact $contact, $productId, ?int $variationIndex = null)
    {
        try {
            if ($gate = $this->interceptForPaymentMethod($contact, [
                'action' => 'quantity',
                'product_id' => (int) $productId,
                'variation_index' => $variationIndex,
            ])) {
                return $gate;
            }

            $price = $this->findCatalogProduct($productId);

            if (! $price) {
                return null;
            }

            if (! $price->allow_quantity_selection) {
                // El producto ya no permite elegir cantidad (por ejemplo, se
                // desactivó la opción entre que se envió el botón y el clic):
                // agregamos 1 unidad en vez de dejar al cliente sin respuesta.
                return $this->addToCart($contact, (int) $productId, 1, $variationIndex);
            }

            $min = max(1, (int) ($price->min_quantity ?: 1));
            $max = max($min, (int) ($price->max_quantity ?: 99));
            // Una lista de WhatsApp admite máximo 10 filas en total; dejamos
            // hasta 8 opciones de cantidad + "Otra cantidad" + "Volver".
            $maxOption = min($max, $min + 7);

            $rows = [];
            for ($qty = $min; $qty <= $maxOption; $qty++) {
                $rows[] = [
                    'id' => 'cantidad_'.$qty.'_'.$productId.($variationIndex === null ? '' : '_'.$variationIndex),
                    'title' => $qty === 1 ? '1 unidad' : $qty.' unidades',
                ];
            }
            $rows[] = [
                'id' => 'otra_cantidad_'.$productId.($variationIndex === null ? '' : '_'.$variationIndex),
                'title' => '✏️ Otra cantidad',
            ];
            $rows[] = [
                'id' => 'volver_productos',
                'title' => 'Volver a productos',
            ];

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'body' => [
                        'text' => "*{$price->name}*\n\n¿Cuántas unidades quieres agregar?",
                    ],
                    'action' => [
                        'button' => 'Seleccionar cantidad',
                        'sections' => [
                            [
                                'title' => 'Cantidad',
                                'rows' => $rows,
                            ],
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error al mostrar selección de cantidad', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
            ]);

            return null;
        }
    }

    /** "Ver categorías" / "Volver a categorías" -- misma imagen de encabezado pendiente que getProductsMenu(). */
    private function buildCategoryBrowserResponse(?WhatsappContact $contact = null): array
    {
        $builder = app(MarketingCatalogBuilder::class, ['businessProfile' => $this->businessProfile]);

        if ($contact?->phone_number && ($imageUrl = $builder->pendingListHeaderImage(null))) {
            $this->sendMessage($contact->phone_number, WhatsappMessagePayload::image($imageUrl));
            // Meta tarda en descargar/procesar la imagen antes de entregarla;
            // sin esta pausa, la lista (JSON puro, sin nada que descargar)
            // suele llegarle al cliente antes que la imagen, aunque la hayamos
            // mandado primero (mismo ajuste que ya se usa más arriba para
            // preservar el orden entre dos mensajes seguidos del bot).
            usleep(500000);
        }

        return $builder->buildCategoryBrowser($contact);
    }

    private function getProductsMenu(?WhatsappContact $contact = null, ?int $categoryId = null)
    {
        try {
            $builder = app(MarketingCatalogBuilder::class, ['businessProfile' => $this->businessProfile]);

            if ($contact?->phone_number && ($imageUrl = $builder->pendingListHeaderImage($categoryId))) {
                $this->sendMessage($contact->phone_number, WhatsappMessagePayload::image($imageUrl));
                usleep(500000);
            }

            return $builder->buildCatalog($contact, $categoryId);
        } catch (\Exception $e) {
            Log::error('❌ Error al generar el menú de precios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'type' => 'text',
                'text' => [
                    'body' => 'Lo siento, ha ocurrido un error al cargar los productos. Por favor, intenta nuevamente más tarde.',
                ],
            ];
        }
    }

    private function sendNativeCatalogMenu(?WhatsappContact $contact = null): array
    {
        if (! config('whatsapp.catalog_enabled') || ! config('whatsapp.catalog_id')) {
            return $this->getProductsMenu($contact);
        }

        return WhatsappMessagePayload::catalog(
            '🍗 *Catálogo DPIKEOS*\n\nMira las fotos, agrega varios productos a tu carrito y envíalo cuando estés listo.',
            (string) config('whatsapp.catalog_id'),
            null,
            'Tu pedido se confirma por este chat.'
        );
    }

    private function sendMessage($to, $message)
    {
        try {
            // Validar y ajustar la longitud del texto del mensaje interactivo
            if (isset($message['interactive'])) {
                // Validar texto del cuerpo (máximo 1024 caracteres)
                if (isset($message['interactive']['body']['text'])) {
                    $text = $message['interactive']['body']['text'];
                    if (mb_strlen($text) > 1024) {
                        $message['interactive']['body']['text'] = mb_substr($text, 0, 1021).'...';
                        Log::warning('⚠️ Texto del cuerpo truncado', [
                            'longitud_original' => mb_strlen($text),
                            'longitud_final' => mb_strlen($message['interactive']['body']['text']),
                        ]);
                    }
                }

                // Validar títulos de botones (máximo 20 caracteres)
                if (isset($message['interactive']['action']['buttons'])) {
                    foreach ($message['interactive']['action']['buttons'] as &$button) {
                        if (isset($button['reply']['title'])) {
                            $title = $button['reply']['title'];
                            if (mb_strlen($title) > 20) {
                                $button['reply']['title'] = mb_substr($title, 0, 17).'...';
                                Log::warning('⚠️ Título de botón truncado', [
                                    'título_original' => $title,
                                    'título_final' => $button['reply']['title'],
                                ]);
                            }
                        }
                    }
                }

                // Validar elementos de lista
                if (isset($message['interactive']['action']['sections'])) {
                    foreach ($message['interactive']['action']['sections'] as &$section) {
                        // Validar título de sección (máximo 24 caracteres)
                        if (isset($section['title']) && mb_strlen($section['title']) > 24) {
                            $section['title'] = mb_substr($section['title'], 0, 21).'...';
                            Log::warning('⚠️ Título de sección truncado', [
                                'título_original' => $section['title'],
                                'título_final' => $section['title'],
                            ]);
                        }

                        // Validar filas de la sección
                        if (isset($section['rows'])) {
                            foreach ($section['rows'] as &$row) {
                                // Validar título de fila (máximo 24 caracteres)
                                if (isset($row['title']) && mb_strlen($row['title']) > 24) {
                                    $row['title'] = mb_substr($row['title'], 0, 21).'...';
                                    Log::warning('⚠️ Título de fila truncado', [
                                        'título_original' => $row['title'],
                                        'título_final' => $row['title'],
                                    ]);
                                }

                                // Validar descripción de fila (máximo 72 caracteres)
                                if (isset($row['description']) && mb_strlen($row['description']) > 72) {
                                    $row['description'] = mb_substr($row['description'], 0, 69).'...';
                                    Log::warning('⚠️ Descripción de fila truncada', [
                                        'descripción_original' => $row['description'],
                                        'descripción_final' => $row['description'],
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Validar texto del botón de acción (máximo 20 caracteres)
                if (isset($message['interactive']['action']['button'])) {
                    $buttonText = $message['interactive']['action']['button'];
                    if (mb_strlen($buttonText) > 20) {
                        $message['interactive']['action']['button'] = mb_substr($buttonText, 0, 17).'...';
                        Log::warning('⚠️ Texto del botón de acción truncado', [
                            'texto_original' => $buttonText,
                            'texto_final' => $message['interactive']['action']['button'],
                        ]);
                    }
                }
            }

            $result = $this->sendMessageToWhatsApp($to, $message);
            if (! $result && ($message['interactive']['type'] ?? null) === 'catalog_message') {
                // Commerce Manager puede tardar en habilitar productos recién
                // vinculados. Durante esa ventana no dejamos al cliente sin
                // respuesta: enviamos el catálogo local navegable.
                $contact = $this->findContactByPhone($to);
                $fallback = $contact && app(BulkOrderService::class)->isAvailable()
                    ? $this->sendBulkWebOrderLink($contact)
                    : $this->getProductsMenu($contact);
                Log::warning('[Catálogo Meta] No disponible; usando micrositio temporalmente', [
                    'to' => substr($to, 0, 4).'****'.substr($to, -4),
                ]);

                $result = $this->sendMessageToWhatsApp($to, $fallback);
                if ($result) {
                    $message = $fallback;
                }
            }

            if (! $result) {
                Log::error('❌ Error al enviar mensaje', [
                    'to' => $to,
                    'message' => $message,
                ]);

                return false;
            }

            // Obtener el contacto
            $contact = $this->findContactByPhone($to);
            if (! $contact) {
                Log::error('❌ Contacto no encontrado al guardar mensaje del sistema', ['phone' => $to]);

                return false;
            }

            // Determinar el contenido del mensaje según el tipo
            $content = '';
            if ($message['type'] === 'text') {
                $content = $message['text']['body'] ?? '';
            } elseif ($message['type'] === 'image') {
                $content = $message['image']['caption'] ?? '[Imagen]';
            } elseif ($message['type'] === 'interactive') {
                if (isset($message['interactive']['body']['text'])) {
                    $content = $message['interactive']['body']['text'];
                } elseif (isset($message['interactive']['header']['text'])) {
                    $content = $message['interactive']['header']['text'];
                }
            }

            // Guardar el mensaje del sistema
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'message_id' => $result['message_id'],
                'sender_type' => 'system',
                'receiver_type' => 'client',
                'content' => $content,
                'type' => $message['type'],
                'status' => 'sent',
                'metadata' => [
                    'raw_message' => $message,
                    'timestamp' => now(),
                ],
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('❌ Error al enviar mensaje', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function getOrderMenu()
    {
        try {
            $contact = $this->lastMessage?->contact ?? null;

            if (! $contact) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se pudo identificar tu contacto. Por favor, envía un mensaje primero.'],
                ];
            }

            $intro = '';
            $ordersStep = $this->getMarketingStep(MarketingStepKey::ORDERS_MENU);
            if ($ordersStep && $ordersStep->is_enabled && $ordersStep->message_template) {
                $intro = $ordersStep->renderMessage($this->marketingFlowVariables($contact))."\n\n";
            }

            // Solo pedidos en curso: ya entregados/pagados no se muestran aquí
            // (el cliente los pidió afuera de esta lista, no tiene sentido
            // seguir recordándoselos como si necesitaran su atención).
            $orders = WhatsappCart::where('contact_id', $contact->id)
                ->whereIn('status', [
                    WhatsappCart::STATUS_PENDING,
                    WhatsappCart::STATUS_CONFIRMED,
                    WhatsappCart::STATUS_PREPARING,
                    WhatsappCart::STATUS_READY,
                    WhatsappCart::STATUS_PAYMENT_PENDING,
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($orders->isEmpty()) {
                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => $intro."📦 *Historial de Pedidos*\n\n".
                                "No tienes pedidos realizados aún.\n\n".
                                '¿Te gustaría ver nuestros productos?',
                        ],
                        'action' => [
                            'buttons' => [
                                [
                                    'type' => 'reply',
                                    'reply' => [
                                        'id' => 'productos',
                                        'title' => '🛍️ Ver productos',
                                    ],
                                ],
                                [
                                    'type' => 'reply',
                                    'reply' => [
                                        'id' => 'menu_principal',
                                        'title' => '🏠 Menú principal',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // Agrupar pedidos por estado
            $pendingOrders = $orders->where('status', WhatsappCart::STATUS_PENDING);
            $confirmedOrders = $orders->whereIn('status', [
                WhatsappCart::STATUS_CONFIRMED,
                WhatsappCart::STATUS_PREPARING,
                WhatsappCart::STATUS_READY,
            ]);
            $paymentPendingOrders = $orders->where('status', WhatsappCart::STATUS_PAYMENT_PENDING);

            $message = $intro."📦 *Tus Pedidos*\n\n";

            // Mostrar pedidos pendientes de confirmación
            if ($pendingOrders->isNotEmpty()) {
                $message .= "⏳ *Pedidos Pendientes de Confirmación*\n";
                foreach ($pendingOrders as $order) {
                    $orderDetails = $order->metadata['order_details'] ?? null;
                    if ($orderDetails) {
                        $message .= "🛒 *{$orderDetails['order_number']}*\n";
                        $message .= '📅 Fecha: '.date('d/m/Y H:i', strtotime($orderDetails['created_at']))."\n";
                        $message .= "💰 Total: \${$orderDetails['total']}\n";
                        $message .= '💳 Método de pago: '.$this->getPaymentMethodText($orderDetails['payment_method'])."\n";
                        $message .= "📋 Items:\n";
                        foreach ($orderDetails['items'] as $item) {
                            $message .= "  • {$item['name']} x{$item['quantity']}\n";
                        }
                        if (! empty($orderDetails['note']) && $orderDetails['note'] !== 'sin nota') {
                            $message .= "📝 Nota: {$orderDetails['note']}\n";
                        }
                        $message .= "\n";
                    }
                }
            }

            // Mostrar pedidos confirmados
            if ($confirmedOrders->isNotEmpty()) {
                $message .= "✅ *Pedidos en Proceso*\n";
                foreach ($confirmedOrders as $order) {
                    $orderDetails = $order->metadata['order_details'] ?? null;
                    if ($orderDetails) {
                        $message .= "🛒 *{$orderDetails['order_number']}*\n";
                        $message .= '📅 Fecha: '.date('d/m/Y H:i', strtotime($orderDetails['created_at']))."\n";
                        $message .= "💰 Total: \${$orderDetails['total']}\n";
                        $message .= '💳 Método de pago: '.$this->getPaymentMethodText($orderDetails['payment_method'])."\n";
                        $message .= "📋 Items:\n";
                        foreach ($orderDetails['items'] as $item) {
                            $message .= "  • {$item['name']} x{$item['quantity']}\n";
                        }
                        if (! empty($orderDetails['note']) && $orderDetails['note'] !== 'sin nota') {
                            $message .= "📝 Nota: {$orderDetails['note']}\n";
                        }
                        $message .= "\n";
                    }
                }
            }

            // Mostrar pedidos pendientes de pago
            if ($paymentPendingOrders->isNotEmpty()) {
                $message .= "💳 *Pedidos Pendientes de Pago*\n";
                foreach ($paymentPendingOrders as $order) {
                    $orderDetails = $order->metadata['order_details'] ?? null;
                    if ($orderDetails) {
                        $message .= "🛒 *{$orderDetails['order_number']}*\n";
                        $message .= '📅 Fecha: '.date('d/m/Y H:i', strtotime($orderDetails['created_at']))."\n";
                        $message .= "💰 Total: \${$orderDetails['total']}\n";
                        $message .= '💳 Método de pago: '.$this->getPaymentMethodText($orderDetails['payment_method'])."\n";
                        if ($order->isAwaitingPaymentProof() && ! $order->hasPaymentProof()) {
                            $message .= "📎 *Estado:* Pendiente de comprobante\n";
                        } elseif ($order->payment_status === 'proof_submitted') {
                            $message .= "✅ *Estado:* Comprobante en revisión\n";
                        }
                        $message .= "📋 Items:\n";
                        foreach ($orderDetails['items'] as $item) {
                            $message .= "  • {$item['name']} x{$item['quantity']}\n";
                        }
                        if (! empty($orderDetails['note']) && $orderDetails['note'] !== 'sin nota') {
                            $message .= "📝 Nota: {$orderDetails['note']}\n";
                        }
                        $message .= "\n";
                    }
                }
            }

            $message .= '¿Qué deseas hacer?';

            $awaitingProofOrders = $paymentPendingOrders->filter(
                fn ($order) => $order->isAwaitingPaymentProof() && ! $order->hasPaymentProof()
            );

            // Preparar botones (máx. 3 en WhatsApp)
            $buttons = [];

            if ($awaitingProofOrders->count() === 1) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'enviar_comprobante_'.$awaitingProofOrders->first()->id,
                        'title' => '📎 Enviar comprobante',
                    ],
                ];
            } elseif ($awaitingProofOrders->count() > 1) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'enviar_comprobante_menu',
                        'title' => '📎 Enviar comprobante',
                    ],
                ];
            } elseif ($paymentPendingOrders->isNotEmpty()) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'ver_instrucciones_pago',
                        'title' => '💳 Instrucciones de pago',
                    ],
                ];
            }

            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => 'menu_productos',
                    'title' => '🛍️ Ver productos',
                ],
            ];

            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => 'menu_principal',
                    'title' => '🏠 Menú principal',
                ],
            ];

            $buttons = array_slice($buttons, 0, 3);

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'buttons' => $buttons,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error al obtener menú de pedidos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al cargar el historial de pedidos.'],
            ];
        }
    }

    private function getInfoMenu()
    {
        try {
            $flowInfo = $this->buildMarketingStepPayload(MarketingStepKey::INFO_MENU);
            $infoStep = $this->getMarketingStep(MarketingStepKey::INFO_MENU);
            if ($flowInfo && $infoStep) {
                return $flowInfo;
            }

            $menu = $this->menuByActionId('info_menu');
            if (! $menu) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, el menú de información no está disponible en este momento.'],
                ];
            }

            // Obtener el menú principal para el botón de retorno
            $mainMenu = $this->menuByActionId('main_menu');
            $mainMenuButton = $mainMenu ? $mainMenu->button_text : 'Volver al Menú';

            // Obtener la respuesta de soporte
            $soporteResponse = WhatsappChatbotResponse::where('keyword', 'soporte')
                ->where('is_active', true)
                ->first();

            // Preparar las secciones del menú
            $sections = $menu->metadata['sections'] ?? [];

            // Agregar la sección de soporte si existe la respuesta
            if ($soporteResponse) {
                $sections[] = [
                    'title' => 'Soporte',
                    'rows' => [
                        [
                            'id' => 'soporte',
                            'title' => '🛟 Soporte Técnico',
                            'description' => 'Contacta con nuestro equipo de soporte',
                        ],
                    ],
                ];
            }

            // Agregar el botón de retorno al menú en cada sección
            $sections = array_map(function ($section) use ($mainMenuButton) {
                if (isset($section['rows'])) {
                    $section['rows'][] = [
                        'id' => 'return_to_menu',
                        'title' => $mainMenuButton,
                        'description' => 'Volver al menú principal',
                    ];
                }

                return $section;
            }, $sections);

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'body' => [
                        'text' => $menu->content,
                    ],
                    'action' => [
                        'button' => $menu->button_text,
                        'sections' => $sections,
                    ],
                    'footer' => [
                        'text' => 'Selecciona una opción o vuelve al menú principal',
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error al obtener menú de información', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al cargar el menú de información.'],
            ];
        }
    }

    private function isProcessActive(WhatsappContact $contact): bool
    {
        // Verificar si hay un carrito activo con nota pendiente
        $cart = WhatsappCart::where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        if ($cart && isset($cart->metadata['pending_note']) && $cart->metadata['pending_note']) {
            return true;
        }

        $proofCart = $this->findCartPendingProofUpload($contact);
        if ($proofCart) {
            return true;
        }

        return false;
    }

    private function confirmarPedido(WhatsappContact $contact, $cartId)
    {
        try {
            $cart = WhatsappCart::where('id', $cartId)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $cart) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se encontró el pedido.'],
                ];
            }

            $this->syncOrderDetails($cart);
            $cart->refresh();

            $orderNumber = $cart->getOrderNumber();

            // Los pedidos creados desde el micrositio aún no traen método de
            // pago. No se pueden confirmar como pagados o contra entrega sin
            // que el cliente lo elija explícitamente.
            if (empty($cart->payment_method)) {
                $metadata = $cart->metadata ?? [];
                $metadata['pending_payment_method'] = true;
                $cart->metadata = $metadata;
                $cart->save();

                return [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'body' => ['text' => "💳 *Selecciona el método de pago*\n\nPedido {$orderNumber}: elige cómo deseas pagar."],
                        'action' => [
                            'button' => 'Elegir pago',
                            'sections' => [[
                                'title' => 'Métodos disponibles',
                                'rows' => array_values(array_filter([
                                    $this->isPaymentMethodEnabled('transferencia') ? ['id' => 'pago_transferencia_'.$cart->id, 'title' => '🏦 Transferencia', 'description' => 'Transferencia o depósito · envías el comprobante'] : null,
                                    $this->isPaymentMethodEnabled('efectivo') ? ['id' => 'pago_efectivo_'.$cart->id, 'title' => '💵 Efectivo', 'description' => 'Pago al recibir o retirar'] : null,
                                    $this->isPaymentMethodEnabled('tarjeta') ? ['id' => 'pago_tarjeta_'.$cart->id, 'title' => '💳 Tarjeta', 'description' => 'Tarjeta · envías el comprobante'] : null,
                                ])),
                            ]],
                        ],
                    ],
                ];
            }

            $metadata = $cart->metadata ?? [];
            unset($metadata['awaiting_client_confirmation'], $metadata['pending_payment_method']);
            $metadata['confirmed_at'] = now()->toIso8601String();
            $metadata['confirmed_via'] = 'whatsapp';
            $cart->metadata = $metadata;
            $cart->save();

            $confirmationBody = "✅ *¡Pedido confirmado!*\n\n"
                ."📦 *Número de pedido:* {$orderNumber}\n"
                .$this->buildCostBreakdownText($cart, false)
                .'💳 *Método de pago:* '.$this->getPaymentMethodText($cart->payment_method)."\n\n"
                .$this->buildFulfillmentSummaryText($cart);

            if ($this->requiresPaymentProofForCart($cart)) {
                $cart = app(OrderLifecycleService::class)
                    ->transition($cart, WhatsappCart::STATUS_PAYMENT_PENDING);
                $this->syncOrderDetails($cart);

                // Si todavía falta que un vendedor confirme el envío o el
                // costo para llevar, el total no es el final: no le pedimos
                // el comprobante todavía (le estaríamos pidiendo que pague un
                // monto que va a cambiar). Se le pide en cuanto se confirmen
                // esos costos, ver WhatsappService::maybeRequestPaymentProofAfterCosts.
                if ($this->cartHasPendingFulfillmentCosts($cart)) {
                    $confirmationBody .= '🕐 Tu pedido se encuentra registrado. Pronto nuestro equipo te confirmará el total a pagar y ahí te pediremos tu comprobante.';

                    return [
                        'type' => 'text',
                        'text' => ['body' => $confirmationBody],
                    ];
                }

                $cart->markAwaitingPaymentProof();
                $this->syncOrderDetails($cart);

                $confirmationBody .= "🕐 Tu pedido queda *pendiente de verificación* hasta que recibamos tu comprobante. En cuanto lo enviemos a revisión, te confirmamos por este mismo chat.\n\n";

                $proofPayload = $this->buildPaymentProofRequestPayload($contact, $cart);
                if ($proofPayload && ($proofPayload['type'] ?? '') === 'text') {
                    $proofPayload['text']['body'] = $confirmationBody.($proofPayload['text']['body'] ?? '');

                    return $proofPayload;
                }

                return [
                    'type' => 'text',
                    'text' => ['body' => $confirmationBody.'📎 Por favor, envía una imagen o PDF de tu comprobante de pago.'],
                ];
            }

            $cart = app(OrderLifecycleService::class)
                ->transition($cart, WhatsappCart::STATUS_CONFIRMED);
            $cart->payment_status = $cart->payment_method === 'efectivo' ? 'cash_on_delivery' : 'confirmed';
            $cart->save();
            $this->syncOrderDetails($cart);

            $confirmationBody .= 'Te contactaremos pronto para coordinar los siguientes pasos.';

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => $confirmationBody],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => ['id' => 'menu_pedido', 'title' => '📦 Mis pedidos'],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => ['id' => 'menu_principal', 'title' => '🏠 Menú principal'],
                            ],
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al confirmar pedido', [
                'error' => $e->getMessage(),
                'cart_id' => $cartId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al confirmar tu pedido.'],
            ];
        }
    }

    private function cancelarPedido(WhatsappContact $contact, $cartId)
    {
        try {
            $cart = WhatsappCart::where('id', $cartId)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $cart) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se encontró el pedido.'],
                ];
            }

            $cart = app(OrderLifecycleService::class)
                ->transition($cart, WhatsappCart::STATUS_CANCELLED);
            $metadata = $cart->metadata ?? [];
            unset($metadata['awaiting_client_confirmation']);
            $metadata['cancelled_at'] = now()->toIso8601String();
            $metadata['cancelled_via'] = 'whatsapp';
            $cart->metadata = $metadata;
            $cart->save();

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => "❌ *Pedido cancelado*\n\n".
                            "Tu pedido ha sido cancelado.\n\n".
                            '¿Qué deseas hacer?',
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_productos',
                                    'title' => '🛍️ Ver productos',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'menu_principal',
                                    'title' => '🏠 Menú principal',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al cancelar pedido', [
                'error' => $e->getMessage(),
                'cart_id' => $cartId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al cancelar tu pedido.'],
            ];
        }
    }

    private function modificarPedido(WhatsappContact $contact, $cartId)
    {
        try {
            $cart = WhatsappCart::where('id', $cartId)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $cart) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se encontró el pedido.'],
                ];
            }

            if ($cart->status === WhatsappCart::STATUS_CANCELLED) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Este pedido ya fue cancelado. Puedes armar uno nuevo desde el menú.'],
                ];
            }

            $metadata = $cart->metadata ?? [];
            $metadata['modification_requested_at'] = now()->toIso8601String();
            unset($metadata['awaiting_client_confirmation']);
            $cart->metadata = $metadata;
            $cart->save();

            $orderNumber = $cart->getOrderNumber();
            $bulkService = app(BulkOrderService::class);

            if ($bulkService->isAvailable()) {
                $token = $bulkService->issueToken($contact);
                if ($token) {
                    $url = $bulkService->formUrl($token);

                    return [
                        'type' => 'interactive',
                        'interactive' => [
                            'type' => 'cta_url',
                            'body' => [
                                'text' => "✏️ *Modificar pedido*\n\n"
                                    ."Pedido *{$orderNumber}*.\n"
                                    ."Abre el formulario, ajusta productos y cantidades.\n"
                                    .'Al enviarlo, el pedido anterior será reemplazado.',
                            ],
                            'action' => [
                                'name' => 'cta_url',
                                'parameters' => [
                                    'display_text' => 'Abrir formulario',
                                    'url' => $url,
                                ],
                            ],
                        ],
                    ];
                }
            }

            return [
                'type' => 'text',
                'text' => [
                    'body' => "✏️ *Modificar pedido {$orderNumber}*\n\n"
                        .'Escríbenos por este chat indicando los cambios que necesitas y un asesor te ayudará.',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al modificar pedido', [
                'error' => $e->getMessage(),
                'cart_id' => $cartId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al procesar tu solicitud.'],
            ];
        }
    }

    /**
     * Si esta respuesta de método de pago vino del gate NUEVO (preguntado
     * antes de cantidad/agregar, con el carrito todavía vacío), retoma la
     * acción que había quedado pendiente en vez de saltar directo al resumen
     * -- retoma() vuelve a pasar por interceptForPaymentMethod(), pero como
     * ya hay payment_method guardado, esta vez sigue de largo.
     */
    private function resumePendingFirstAction(WhatsappContact $contact, WhatsappCart $cart): ?array
    {
        $pending = $cart->metadata['pending_first_action'] ?? null;

        if (! $pending) {
            return null;
        }

        $metadata = $cart->metadata ?? [];
        unset($metadata['pending_first_action']);
        $cart->metadata = $metadata;
        $cart->save();

        return match ($pending['action'] ?? null) {
            'quantity' => $this->showQuantitySelection($contact, $pending['product_id'], $pending['variation_index'] ?? null),
            'add' => $this->addToCart($contact, $pending['product_id'], $pending['quantity'] ?? 1, $pending['variation_index'] ?? null),
            default => $this->getProductsMenu($contact),
        };
    }

    private function procesarPagoTransferencia(WhatsappContact $contact, $cartId)
    {
        try {
            $cart = WhatsappCart::where('id', $cartId)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $cart) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se encontró el pedido.'],
                ];
            }

            // Actualizar el método de pago y estado
            $cart->payment_method = 'transferencia';
            $cart->payment_status = 'pending';
            $cart->save();

            if ($resumed = $this->resumePendingFirstAction($contact, $cart)) {
                return $resumed;
            }

            // Preparar los detalles del pedido para guardar en metadata
            $orderDetails = [
                'order_number' => 'ORD-'.str_pad($cart->id, 6, '0', STR_PAD_LEFT),
                'items' => [],
                'total' => $cart->total,
                'note' => $cart->note,
                'created_at' => $cart->created_at->format('Y-m-d H:i:s'),
                'status' => $cart->status,
                'payment_method' => $cart->payment_method,
                'payment_status' => $cart->payment_status,
                'branch' => $cart->branch?->name,
                'service_type' => $cart->metadata['service_type'] ?? null,
                'pickup_mode' => $cart->metadata['pickup_mode'] ?? null,
                'delivery_location' => $cart->metadata['delivery_location'] ?? null,
                'delivery_distance_km' => $cart->metadata['delivery_distance_km'] ?? null,
                'delivery_fee' => $cart->metadata['delivery_fee'] ?? null,
                'delivery_recipient_name' => $cart->metadata['delivery_recipient_name'] ?? null,
                'delivery_fee_pending_review' => $cart->metadata['delivery_fee_pending_review'] ?? false,
            ];

            foreach ($cart->items as $item) {
                $orderDetails['items'][] = [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->price * $item->quantity,
                ];
            }

            // Guardar los detalles del pedido en metadata
            $metadata = $cart->metadata ?? [];
            $metadata['order_details'] = $orderDetails;
            $cart->metadata = $metadata;
            $cart->save();

            // Preparar resumen del pedido
            $message = $this->buildOrderSummaryHeader('📋 *Resumen de tu pedido*', $orderDetails['order_number']);
            $message .= $this->buildOrderItemsText($cart);
            $message .= $this->buildFulfillmentSummaryText($cart);
            $message .= $this->buildPaymentMethodBlock('Transferencia o depósito bancario');
            $message .= $this->buildCostBreakdownText($cart, false);

            if ($cart->note && $cart->note !== 'sin nota') {
                $message .= "📝 *Nota:* {$cart->note}\n\n";
            }

            $message .= '¿Confirmas tu pedido?';

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'confirmar_pedido_'.$cart->id,
                                    'title' => '✅ Confirmar pedido',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'cancelar_pedido_'.$cart->id,
                                    'title' => '❌ Cancelar pedido',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error al procesar pago por transferencia', [
                'error' => $e->getMessage(),
                'cart_id' => $cartId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al procesar el pago.'],
            ];
        }
    }

    private function procesarPagoEfectivo(WhatsappContact $contact, $cartId)
    {
        try {
            $cart = WhatsappCart::where('id', $cartId)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $cart) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se encontró el pedido.'],
                ];
            }

            // Actualizar el método de pago y estado
            $cart->payment_method = 'efectivo';
            $cart->payment_status = 'pending';
            $cart->save();

            if ($resumed = $this->resumePendingFirstAction($contact, $cart)) {
                return $resumed;
            }

            // Preparar los detalles del pedido para guardar en metadata
            $orderDetails = [
                'order_number' => 'ORD-'.str_pad($cart->id, 6, '0', STR_PAD_LEFT),
                'items' => [],
                'total' => $cart->total,
                'note' => $cart->note,
                'created_at' => $cart->created_at->format('Y-m-d H:i:s'),
                'status' => $cart->status,
                'payment_method' => $cart->payment_method,
                'payment_status' => $cart->payment_status,
                'branch' => $cart->branch?->name,
                'service_type' => $cart->metadata['service_type'] ?? null,
                'pickup_mode' => $cart->metadata['pickup_mode'] ?? null,
                'delivery_location' => $cart->metadata['delivery_location'] ?? null,
                'delivery_distance_km' => $cart->metadata['delivery_distance_km'] ?? null,
                'delivery_fee' => $cart->metadata['delivery_fee'] ?? null,
                'delivery_recipient_name' => $cart->metadata['delivery_recipient_name'] ?? null,
                'delivery_fee_pending_review' => $cart->metadata['delivery_fee_pending_review'] ?? false,
            ];

            foreach ($cart->items as $item) {
                $orderDetails['items'][] = [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->price * $item->quantity,
                ];
            }

            // Guardar los detalles del pedido en metadata
            $metadata = $cart->metadata ?? [];
            $metadata['order_details'] = $orderDetails;
            $cart->metadata = $metadata;
            $cart->save();

            // Preparar resumen del pedido
            $message = $this->buildOrderSummaryHeader('📋 *Resumen de tu pedido*', $orderDetails['order_number']);
            $message .= $this->buildOrderItemsText($cart);
            $message .= $this->buildFulfillmentSummaryText($cart);
            $message .= $this->buildPaymentMethodBlock('Pago en efectivo');
            $message .= $this->buildCostBreakdownText($cart, false);

            if ($cart->note && $cart->note !== 'sin nota') {
                $message .= "📝 *Nota:* {$cart->note}\n\n";
            }

            $message .= '¿Confirmas tu pedido?';

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'buttons' => [
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'confirmar_pedido_'.$cart->id,
                                    'title' => '✅ Confirmar pedido',
                                ],
                            ],
                            [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => 'cancelar_pedido_'.$cart->id,
                                    'title' => '❌ Cancelar pedido',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al procesar pago en efectivo', [
                'error' => $e->getMessage(),
                'cart_id' => $cartId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al procesar el pago en efectivo.'],
            ];
        }
    }

    private function procesarPagoTarjeta(WhatsappContact $contact, $cartId)
    {
        try {
            $cart = WhatsappCart::where('id', $cartId)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $cart) {
                return [
                    'type' => 'text',
                    'text' => ['body' => 'Lo siento, no se encontró el pedido.'],
                ];
            }

            // El pago con tarjeta se resuelve fuera del chat, en la página web
            // externa del negocio (no manejamos pasarela de pago aquí). El bot
            // manda el link y no vuelve a preguntar nada más para este carrito
            // (ver guard de card_payment_link_sent en finalizarCompra()).
            $cart->payment_method = 'tarjeta';
            $cart->payment_status = 'pending';

            $metadata = $cart->metadata ?? [];
            $metadata['card_payment_link_sent'] = true;
            unset($metadata['pending_payment_method'], $metadata['pending_first_action']);
            $cart->metadata = $metadata;
            $cart->save();

            $chatbotConfig = $this->scopedChatbotConfig();
            $cardPaymentUrl = trim((string) ($chatbotConfig?->metadata['card_payment_url'] ?? ''));
            $cardPaymentMessage = trim((string) ($chatbotConfig?->metadata['card_payment_message'] ?? ''));
            if ($cardPaymentMessage === '') {
                $cardPaymentMessage = '💳 Puedes pagar con tarjeta directamente aquí:';
            }

            if ($cardPaymentUrl === '') {
                Log::warning('[procesarPagoTarjeta] Se seleccionó tarjeta pero no hay card_payment_url configurada', [
                    'cart_id' => $cart->id,
                ]);

                return [
                    'type' => 'text',
                    'text' => ['body' => 'El pago con tarjeta no está disponible por ahora. Por favor elige otro método de pago o escríbenos.'],
                ];
            }

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'cta_url',
                    'body' => ['text' => $cardPaymentMessage],
                    'action' => [
                        'name' => 'cta_url',
                        'parameters' => [
                            'display_text' => 'Pagar en línea',
                            'url' => $cardPaymentUrl,
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al procesar pago con tarjeta', [
                'error' => $e->getMessage(),
                'cart_id' => $cartId,
            ]);

            return [
                'type' => 'text',
                'text' => ['body' => 'Lo siento, ha ocurrido un error al procesar el pago con tarjeta.'],
            ];
        }
    }

    /**
     * Obtiene el contacto desde la base de datos basándose en el keyword
     * Busca en whatsapp_contacts usando metadata->role o metadata->type
     */
    private function getContactFromDatabase($keyword): ?string
    {
        try {
            // Buscar contacto en la base de datos por rol/tipo en metadata
            $contact = WhatsappContact::where('business_profile_id', $this->businessProfile->id)
                ->where('status', 'active')
                ->where(function ($query) use ($keyword) {
                    $query->whereJsonContains('metadata->role', $keyword)
                        ->orWhereJsonContains('metadata->type', $keyword)
                        ->orWhere('name', 'LIKE', '%'.$keyword.'%');
                })
                ->first();

            if ($contact) {
                // Obtener información del contacto
                $metadata = $contact->metadata ?? [];
                $name = $contact->name ?? 'Contacto';
                $phone = $contact->phone_number;

                // Extraer nombre completo (formatted_name|first_name|last_name)
                $nameParts = explode(' ', $name, 2);
                $firstName = $nameParts[0] ?? $name;
                $lastName = $nameParts[1] ?? '';

                // Construir el formato de contacto
                $formattedContact = sprintf(
                    '%s|%s|%s|%s|%s|%s|%s|%s',
                    $name,                                    // formatted_name
                    $firstName,                               // first_name
                    $lastName,                                // last_name
                    $phone,                                   // phone
                    $metadata['email'] ?? '',                 // email
                    $metadata['company'] ?? ($this->businessProfile->business_name ?? ''), // company
                    $metadata['department'] ?? '',            // department
                    $metadata['title'] ?? ''                  // title
                );

                Log::info('[getContactFromDatabase] ✅ Contacto encontrado en BD', [
                    'keyword' => $keyword,
                    'contact_id' => $contact->id,
                    'name' => $name,
                    'phone' => $phone,
                ]);

                return $formattedContact;
            }

            Log::info('[getContactFromDatabase] ⚠️ No se encontró contacto en BD', [
                'keyword' => $keyword,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('[getContactFromDatabase] ❌ Error al buscar contacto', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function formatContacts($contacts)
    {
        Log::info('Formateando contactos', ['input' => $contacts]);

        // Obtener el número de WhatsApp Business para usar como wa_id
        // Esto hace que WhatsApp muestre "Escribir mensaje" en lugar de "Invitar"
        $businessPhoneNumber = $this->businessProfile ? $this->businessProfile->phone_number : null;

        // Si es un string con formato plano (usando | como separador)
        if (is_string($contacts) && strpos($contacts, '|') !== false) {
            Log::info('Detectado formato plano con separador |');
            $fields = explode('|', $contacts);

            if (count($fields) < 8) {
                Log::error('Formato de contacto inválido', [
                    'campos_esperados' => 8,
                    'campos_recibidos' => count($fields),
                    'campos' => $fields,
                ]);

                return [];
            }

            // Usar el número del business profile como wa_id si está disponible
            // Esto asegura que WhatsApp muestre "Escribir mensaje" en lugar de "Invitar"
            $waId = $businessPhoneNumber ? preg_replace('/[^0-9]/', '', $businessPhoneNumber) : preg_replace('/[^0-9]/', '', $fields[3]);

            $contact = [
                'name' => [
                    'formatted_name' => $fields[0],
                    'first_name' => $fields[1],
                    'last_name' => $fields[2],
                ],
                'phones' => [
                    [
                        'phone' => $fields[3],
                        'type' => 'CELL',
                        'wa_id' => $waId,  // Usar el número del business para que muestre "Escribir mensaje"
                    ],
                ],
                'emails' => [
                    [
                        'email' => $fields[4],
                        'type' => 'WORK',
                    ],
                ],
                'org' => [
                    'company' => $fields[5],
                    'department' => $fields[6],
                    'title' => $fields[7],
                ],
            ];

            Log::info('Contacto formateado exitosamente', [
                'contacto' => $contact,
                'wa_id_usado' => $waId,
                'business_phone' => $businessPhoneNumber,
            ]);

            return [$contact];
        }

        // Si es un JSON string, decodificarlo
        if (is_string($contacts)) {
            Log::info('Intentando decodificar JSON string');
            $decoded = json_decode($contacts, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $contacts = $decoded;
                Log::info('JSON decodificado exitosamente');
            } else {
                Log::error('Error decodificando JSON', ['error' => json_last_error_msg()]);

                return [];
            }
        }

        // Si no es un array después de la decodificación, retornar array vacío
        if (! is_array($contacts)) {
            Log::error('Formato de contactos inválido después de procesamiento', [
                'tipo' => gettype($contacts),
            ]);

            return [];
        }

        // Obtener el número de WhatsApp Business para usar como wa_id
        $businessPhoneNumber = $this->businessProfile ? $this->businessProfile->phone_number : null;

        // Formatear los contactos según la API de WhatsApp
        $formattedContacts = [];
        foreach ($contacts as $contact) {
            $formattedContact = [
                'name' => [
                    'formatted_name' => $contact['name'] ?? '',
                    'first_name' => $contact['first_name'] ?? '',
                    'last_name' => $contact['last_name'] ?? '',
                ],
            ];

            // Agregar teléfono si existe
            if (! empty($contact['phone'])) {
                // Usar el número del business profile como wa_id si está disponible
                // Esto asegura que WhatsApp muestre "Escribir mensaje" en lugar de "Invitar"
                $waId = $businessPhoneNumber ? preg_replace('/[^0-9]/', '', $businessPhoneNumber) : preg_replace('/[^0-9]/', '', $contact['phone']);

                $formattedContact['phones'] = [
                    [
                        'phone' => $contact['phone'],
                        'type' => 'CELL',
                        'wa_id' => $waId,  // Usar el número del business para que muestre "Escribir mensaje"
                    ],
                ];
            }

            // Agregar email si existe
            if (! empty($contact['email'])) {
                $formattedContact['emails'] = [
                    [
                        'email' => $contact['email'],
                        'type' => 'WORK',
                    ],
                ];
            }

            // Agregar organización si existe
            if (! empty($contact['company']) || ! empty($contact['department']) || ! empty($contact['title'])) {
                $formattedContact['org'] = [
                    'company' => $contact['company'] ?? '',
                    'department' => $contact['department'] ?? '',
                    'title' => $contact['title'] ?? '',
                ];
            }

            $formattedContacts[] = $formattedContact;
        }

        Log::info('Contactos formateados exitosamente', ['cantidad' => count($formattedContacts)]);

        return $formattedContacts;
    }

    /**
     * Convierte información de contacto a mensaje de texto formateado
     * Útil para evitar el botón "Invitar" de WhatsApp cuando se comparten contactos
     */
    private function formatContactAsText($contacts): string
    {
        // Parsear el contacto desde el string
        $formattedContacts = $this->formatContacts($contacts);

        if (empty($formattedContacts)) {
            return 'Información de contacto no disponible.';
        }

        $contact = $formattedContacts[0];
        $text = "📞 *Información de Contacto*\n\n";

        // Nombre
        if (! empty($contact['name']['formatted_name'])) {
            $text .= '👤 *Nombre:* '.$contact['name']['formatted_name']."\n";
        }

        // Teléfono
        if (! empty($contact['phones'][0]['phone'])) {
            $phone = $contact['phones'][0]['phone'];
            $text .= '📱 *Teléfono:* '.$phone."\n";
            $text .= '💬 *Escribe directamente:* wa.me/'.preg_replace('/[^0-9]/', '', $phone)."\n\n";
        }

        // Email
        if (! empty($contact['emails'][0]['email'])) {
            $text .= '📧 *Email:* '.$contact['emails'][0]['email']."\n";
        }

        // Organización
        if (! empty($contact['org'])) {
            if (! empty($contact['org']['company'])) {
                $text .= '🏢 *Empresa:* '.$contact['org']['company']."\n";
            }
            if (! empty($contact['org']['title'])) {
                $text .= '💼 *Cargo:* '.$contact['org']['title']."\n";
            }
            if (! empty($contact['org']['department'])) {
                $text .= '📋 *Departamento:* '.$contact['org']['department']."\n";
            }
        }

        $text .= "\n_Puedes escribir directamente a este número para contactar con soporte._";

        return $text;
    }

    /**
     * Helper para armar automáticamente el array de variables según la estructura de la plantilla y los datos del contacto.
     * Puedes extender la lógica para mapear más campos del contacto o de otros modelos.
     */
    public function buildTemplateVariables(WhatsappTemplate $template, WhatsappContact $contact, array $customValues = [])
    {
        $variables = [];

        // Si hay valores personalizados, usarlos directamente
        if (! empty($customValues)) {
            $variables = $customValues;
        }
        // Si no hay valores personalizados, usar valores por defecto
        else {
            foreach ($template->components as $component) {
                if (strtolower($component['type']) === 'header' &&
                    isset($component['format']) &&
                    strtolower($component['format']) === 'text') {
                    $variables[] = $contact->name ?? 'Cliente';
                }
            }
        }

        Log::info('Variables construidas:', [
            'template' => $template->name,
            'customValues' => $customValues,
            'finalVariables' => $variables,
        ]);

        return $variables;
    }

    /**
     * Envía el catálogo configurado en el flujo comercial (categorías/productos del panel).
     *
     * @param  string  $to  Número de teléfono del destinatario
     */
    public function sendCatalog($to): bool
    {
        try {
            $contact = $this->findContactByPhone($to);
            if (! $contact) {
                return false;
            }

            $contact->refresh();

            if (! $this->botMayRespondToContact($contact)) {
                $this->logBotBlocked('sendCatalog', $contact, [
                    'to' => substr($to, 0, 4).'****'.substr($to, -4),
                ]);

                return false;
            }

            $response = $this->getProductsMenu($contact);
            if (! $response) {
                return false;
            }

            $sent = $this->sendMessage($to, $response);

            if ($sent) {
                Log::info('✅ Catálogo enviado exitosamente', [
                    'to' => substr($to, 0, 4).'****'.substr($to, -4),
                ]);
            }

            return (bool) $sent;
        } catch (\Exception $e) {
            Log::error('❌ Error al enviar catálogo', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'to' => $to,
            ]);

            return false;
        }
    }

    /**
     * Envía notificaciones de monitoreo (WhatsApp y Email) cuando se recibe un mensaje
     */
    private function sendMonitoringNotifications(array $message)
    {
        try {
            $profileId = $this->businessProfile?->id;
            $config = $profileId
                ? WhatsappChatbotConfig::where('business_profile_id', $profileId)->first()
                : WhatsappChatbotConfig::first();

            if (! $config || ! filter_var($config->monitoring_enabled, FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            // Obtener información del contacto
            $from = $message['from'];
            $contact = $this->findContactByPhone($from);
            $contactName = $contact ? $contact->name : 'Contacto sin nombre';

            // Extraer contenido del mensaje según su tipo
            $messageContent = $this->extractMessageContent($message);
            $messageType = $message['type'] ?? 'desconocido';
            $timestamp = isset($message['timestamp'])
                ? Carbon::createFromTimestamp((int) $message['timestamp'])->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');

            // Enviar mensaje de WhatsApp si está configurado
            // No enviar si el número de monitoreo es el mismo que el que está escribiendo
            if (! empty($config->monitoring_phone_number)) {
                // Normalizar números para comparación (quitar espacios, guiones, etc.)
                $normalizedFrom = preg_replace('/[^0-9]/', '', $from);
                $normalizedMonitoring = preg_replace('/[^0-9]/', '', $config->monitoring_phone_number);

                // Solo enviar si los números son diferentes
                if ($normalizedFrom !== $normalizedMonitoring) {
                    $this->sendMonitoringWhatsAppMessage(
                        $config->monitoring_phone_number,
                        $contactName,
                        $from,
                        $messageContent,
                        $messageType,
                        $timestamp
                    );
                } else {
                    Log::info('⏭️ Mensaje de monitoreo omitido: el número de monitoreo es el mismo que el remitente', [
                        'phone' => substr($from, 0, 4).'****'.substr($from, -4),
                    ]);
                }
            }

            // Enviar email si está configurado
            if (! empty($config->monitoring_email)) {
                $this->sendMonitoringEmail(
                    $config->monitoring_email,
                    $contactName,
                    $from,
                    $messageContent,
                    $messageType,
                    $timestamp
                );
            }
        } catch (\Exception $e) {
            Log::error('❌ Error enviando notificaciones de monitoreo', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extrae el contenido del mensaje según su tipo
     */
    private function extractMessageContent(array $message): string
    {
        $type = $message['type'] ?? 'text';

        switch ($type) {
            case 'text':
                if (is_array($message['text'] ?? null)) {
                    return $message['text']['body'] ?? 'Mensaje de texto sin contenido';
                }

                return $message['text'] ?? 'Mensaje de texto sin contenido';

            case 'interactive':
                $interactive = $message['interactive'] ?? null;
                if ($interactive) {
                    if (isset($interactive['button_reply']['title'])) {
                        return 'Botón: '.$interactive['button_reply']['title'];
                    }
                    if (isset($interactive['list_reply']['title'])) {
                        return 'Lista: '.$interactive['list_reply']['title'];
                    }
                }

                return 'Mensaje interactivo';

            case 'image':
                return '📷 Imagen enviada';

            case 'audio':
                return '🎵 Audio enviado';

            case 'video':
                return '🎥 Video enviado';

            case 'document':
                $document = $message['document'] ?? [];
                $filename = $document['filename'] ?? 'Documento';

                return '📄 Documento: '.$filename;

            case 'location':
                $location = $message['location'] ?? [];
                $latitude = $location['latitude'] ?? '';
                $longitude = $location['longitude'] ?? '';

                return '📍 Ubicación: '.$latitude.', '.$longitude;

            case 'sticker':
                return '😊 Sticker enviado';

            default:
                return 'Tipo de mensaje: '.$type;
        }
    }

    /**
     * Envía un mensaje de WhatsApp de monitoreo
     */
    private function sendMonitoringWhatsAppMessage(
        string $monitoringPhone,
        string $contactName,
        string $contactPhone,
        string $messageContent,
        string $messageType,
        string $timestamp
    ) {
        try {
            // Crear o obtener el contacto de monitoreo
            $monitoringContact = $this->findContactByPhone($monitoringPhone);

            if (! $monitoringContact) {
                // Crear contacto de monitoreo si no existe
                $monitoringContact = WhatsappContact::create([
                    'business_profile_id' => $this->businessProfile->id,
                    'phone_number' => $monitoringPhone,
                    'name' => 'Monitoreo',
                    'status' => 'active',
                ]);
            }

            // Formatear el mensaje de monitoreo
            $monitoringMessage = "🔔 *Nuevo mensaje recibido*\n\n";
            $monitoringMessage .= '👤 *Contacto:* '.$contactName."\n";
            $monitoringMessage .= '📱 *Teléfono:* '.$contactPhone."\n";
            $monitoringMessage .= '📝 *Tipo:* '.ucfirst($messageType)."\n";
            $monitoringMessage .= '🕐 *Fecha/Hora:* '.$timestamp."\n\n";
            $monitoringMessage .= "*Mensaje:*\n".$messageContent;

            // Enviar el mensaje
            $this->sendTextMessage($monitoringContact, $monitoringMessage, false);

            Log::info('✅ Mensaje de monitoreo enviado a WhatsApp', [
                'monitoring_phone' => substr($monitoringPhone, 0, 4).'****'.substr($monitoringPhone, -4),
                'contact' => substr($contactPhone, 0, 4).'****'.substr($contactPhone, -4),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error enviando mensaje de monitoreo a WhatsApp', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'monitoring_phone' => substr($monitoringPhone, 0, 4).'****'.substr($monitoringPhone, -4),
            ]);
        }
    }

    /**
     * Envía un email de monitoreo
     */
    private function sendMonitoringEmail(
        string $monitoringEmail,
        string $contactName,
        string $contactPhone,
        string $messageContent,
        string $messageType,
        string $timestamp
    ) {
        try {
            // Verificar que la configuración de correo esté disponible
            $mailDriver = config('mail.default', 'smtp');

            // Si el driver es 'log', solo registrar en logs (no intentar enviar realmente)
            if ($mailDriver === 'log') {
                Log::info('📧 Email de monitoreo (modo log)', [
                    'email' => $monitoringEmail,
                    'contact' => substr($contactPhone, 0, 4).'****'.substr($contactPhone, -4),
                    'message' => 'El email se registró en los logs. Configura un servidor SMTP para enviar emails reales.',
                ]);

                return;
            }

            // Verificar configuración SMTP básica
            if ($mailDriver === 'smtp') {
                $mailHost = config('mail.mailers.smtp.host');
                if (empty($mailHost) || $mailHost === 'smtp.mailgun.org') {
                    Log::warning('⚠️ Configuración de correo no válida', [
                        'mail_driver' => $mailDriver,
                        'mail_host' => $mailHost,
                        'message' => 'Por favor, configura MAIL_HOST, MAIL_USERNAME y MAIL_PASSWORD en tu archivo .env',
                    ]);

                    return;
                }
            }

            Mail::to($monitoringEmail)->send(
                new MonitoringNotification(
                    $contactName,
                    $contactPhone,
                    $messageContent,
                    $messageType,
                    $timestamp
                )
            );

            Log::info('✅ Email de monitoreo enviado', [
                'email' => $monitoringEmail,
                'contact' => substr($contactPhone, 0, 4).'****'.substr($contactPhone, -4),
            ]);
        } catch (\Exception $e) {
            // No lanzar excepción, solo registrar el error para que el sistema continúe funcionando
            Log::error('❌ Error enviando email de monitoreo', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'email' => $monitoringEmail,
                'suggestion' => 'Verifica tu configuración de correo en el archivo .env. Puedes usar MAIL_MAILER=log para desarrollo.',
            ]);
        }
    }

    private function requiresPaymentProofForCart(WhatsappCart $cart): bool
    {
        $step = $this->getMarketingStep(MarketingStepKey::PAYMENT_PROOF);
        if (! $step || ! $step->is_enabled) {
            return false;
        }

        return $step->requiresPaymentProofForMethod($cart->payment_method);
    }

    /**
     * true si todavía falta que un vendedor confirme el costo de envío y/o
     * el costo para llevar (ver AdminController::sendFulfillmentCosts) --
     * mientras eso esté pendiente, el total del pedido no es el final.
     * Misma lógica que usa buildCostBreakdownText() para decidir si mostrar
     * "por confirmar".
     */
    private function cartHasPendingFulfillmentCosts(WhatsappCart $cart): bool
    {
        $metadata = $cart->metadata ?? [];

        if (($metadata['pickup_mode'] ?? null) === 'delivery') {
            if (! empty($metadata['delivery_fee_pending_review'] ?? false) || ! array_key_exists('delivery_fee', $metadata)) {
                return true;
            }
        }

        if (($metadata['service_type'] ?? null) === 'llevar' && ! array_key_exists('pickup_fee', $metadata)) {
            return true;
        }

        return false;
    }

    /**
     * Se llama después de que un vendedor confirma envío y/o costo para
     * llevar (ver OrderLifecycleService::sendFulfillmentCostsMessage). Si el
     * pedido necesitaba comprobante de pago y no se le había pedido todavía
     * -- porque el total no era final -- lo marca como pendiente de
     * comprobante ahora. Devuelve un cierre corto para agregar al final del
     * mensaje de costos confirmados (que ya trae el número de pedido y el
     * total arriba, así que no hace falta repetirlos aquí), o null si no
     * aplica.
     */
    public function maybeRequestPaymentProofAfterCosts(WhatsappCart $cart): ?string
    {
        if ($cart->isAwaitingPaymentProof() || $cart->hasPaymentProof()) {
            return null;
        }

        if (! $this->requiresPaymentProofForCart($cart) || $this->cartHasPendingFulfillmentCosts($cart)) {
            return null;
        }

        $cart = app(OrderLifecycleService::class)
            ->transition($cart, WhatsappCart::STATUS_PAYMENT_PENDING);
        $cart->markAwaitingPaymentProof();
        $this->syncOrderDetails($cart);

        return '_Quedamos atentos a su comprobante de pago_';
    }

    private function cartFlowVariables(WhatsappCart $cart, ?WhatsappContact $contact = null): array
    {
        return array_merge($this->marketingFlowVariables($contact), [
            'total' => number_format((float) $cart->total, 2),
            'moneda' => 'USD',
            'cantidad_items' => (string) $cart->items()->count(),
            'numero_pedido' => $cart->getOrderNumber(),
            'estado_pedido' => $this->getOrderStatusLabel($cart),
            'metodo_pago' => $this->getPaymentMethodText($cart->payment_method),
        ]);
    }

    private function getOrderStatusLabel(WhatsappCart $cart): string
    {
        if ($cart->isAwaitingPaymentProof() && ! $cart->hasPaymentProof()) {
            return 'Pendiente de comprobante';
        }

        if ($cart->payment_status === 'proof_submitted') {
            return 'Comprobante en revisión';
        }

        return match ($cart->status) {
            WhatsappCart::STATUS_PAYMENT_PENDING => 'Pendiente de pago',
            WhatsappCart::STATUS_CONFIRMED => 'Confirmado',
            WhatsappCart::STATUS_PAID => 'Pagado',
            WhatsappCart::STATUS_COMPLETED => 'Completado',
            WhatsappCart::STATUS_CANCELLED => 'Cancelado',
            default => 'En proceso',
        };
    }

    private function syncOrderDetails(WhatsappCart $cart): array
    {
        $turnNumber = app(DailyOrderNumberService::class)->assign($cart);

        $orderDetails = [
            'order_number' => 'ORD-'.$turnNumber,
            'turn_number' => $turnNumber,
            'items' => [],
            'total' => $cart->total,
            'note' => $cart->note,
            'created_at' => $cart->created_at->format('Y-m-d H:i:s'),
            'status' => $cart->status,
            'payment_method' => $cart->payment_method,
            'payment_status' => $cart->payment_status,
            'branch' => $cart->branch?->name,
            'service_type' => $cart->metadata['service_type'] ?? null,
            'pickup_mode' => $cart->metadata['pickup_mode'] ?? null,
            'delivery_location' => $cart->metadata['delivery_location'] ?? null,
            'delivery_distance_km' => $cart->metadata['delivery_distance_km'] ?? null,
            'delivery_fee' => $cart->metadata['delivery_fee'] ?? null,
            'delivery_recipient_name' => $cart->metadata['delivery_recipient_name'] ?? null,
            'delivery_fee_pending_review' => $cart->metadata['delivery_fee_pending_review'] ?? false,
        ];

        foreach ($cart->items as $item) {
            $orderDetails['items'][] = [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->price * $item->quantity,
            ];
        }

        $metadata = $cart->metadata ?? [];
        $metadata['order_details'] = $orderDetails;
        $cart->metadata = $metadata;
        $cart->save();

        return $orderDetails;
    }

    private function buildPaymentProofRequestPayload(WhatsappContact $contact, WhatsappCart $cart): ?array
    {
        $step = $this->getMarketingStep(MarketingStepKey::PAYMENT_PROOF);
        if (! $step || ! $step->is_enabled) {
            return null;
        }

        $vars = $this->cartFlowVariables($cart, $contact);
        $body = $step->renderMessage($vars);

        if ($body === '') {
            $body = "📎 *Envío de Comprobante*\n\nPedido *{$vars['numero_pedido']}*\n\nEnvía una imagen o PDF de tu comprobante de pago.";
        }

        return [
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }

    private function buildPaymentProofSuccessPayload(WhatsappContact $contact, WhatsappCart $cart): array
    {
        $step = $this->getMarketingStep(MarketingStepKey::PAYMENT_PROOF);
        $vars = $this->cartFlowVariables($cart, $contact);
        $body = $step?->getPaymentProofSuccessMessage($vars)
            ?? "✅ Comprobante recibido para el pedido *{$vars['numero_pedido']}*. Lo verificaremos pronto.";

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => ['id' => 'menu_pedido', 'title' => '📦 Mis pedidos'],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => ['id' => 'menu_principal', 'title' => '🏠 Menú principal'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function findCartPendingProofUpload(WhatsappContact $contact): ?WhatsappCart
    {
        return WhatsappCart::where('contact_id', $contact->id)
            ->whereIn('status', [
                WhatsappCart::STATUS_PAYMENT_PENDING,
                WhatsappCart::STATUS_CONFIRMED,
            ])
            ->get()
            ->first(fn (WhatsappCart $cart) => $cart->isAwaitingPaymentProof() && ! $cart->hasPaymentProof());
    }

    private function iniciarEnvioComprobante(WhatsappContact $contact, int $cartId): array
    {
        $cart = WhatsappCart::where('id', $cartId)
            ->where('contact_id', $contact->id)
            ->first();

        if (! $cart) {
            return [
                'type' => 'text',
                'text' => ['body' => 'No se encontró el pedido seleccionado.'],
            ];
        }

        if ($cart->hasPaymentProof()) {
            return [
                'type' => 'text',
                'text' => ['body' => "El pedido *{$cart->getOrderNumber()}* ya tiene un comprobante registrado y está en revisión."],
            ];
        }

        $cart->markAwaitingPaymentProof();

        return $this->buildPaymentProofRequestPayload($contact, $cart)
            ?? [
                'type' => 'text',
                'text' => ['body' => "📎 Envía una imagen o PDF del comprobante de pago del pedido *{$cart->getOrderNumber()}*."],
            ];
    }

    private function buildPaymentProofOrderList(WhatsappContact $contact): array
    {
        $orders = WhatsappCart::where('contact_id', $contact->id)
            ->whereIn('status', [WhatsappCart::STATUS_PAYMENT_PENDING, WhatsappCart::STATUS_CONFIRMED])
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (WhatsappCart $cart) => $cart->isAwaitingPaymentProof() && ! $cart->hasPaymentProof());

        if ($orders->isEmpty()) {
            return [
                'type' => 'text',
                'text' => ['body' => 'No tienes pedidos pendientes de comprobante en este momento.'],
            ];
        }

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                'id' => 'enviar_comprobante_'.$order->id,
                'title' => Str::limit($order->getOrderNumber(), 24, ''),
                'description' => Str::limit('$'.number_format((float) $order->total, 2).' · '.$this->getPaymentMethodText($order->payment_method), 72, ''),
            ];
        }

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => "📎 *Enviar comprobante*\n\nSelecciona el pedido al que corresponde tu comprobante de pago:",
                ],
                'action' => [
                    'button' => 'Ver pedidos',
                    'sections' => [
                        ['title' => 'Pendientes', 'rows' => array_slice($rows, 0, 10)],
                    ],
                ],
            ],
        ];
    }

    private function registrarComprobantePago(
        WhatsappContact $contact,
        WhatsappCart $cart,
        array $message,
        string $mediaType
    ): array {
        $mediaData = $message[$mediaType] ?? $message['text'] ?? [];
        $messageId = $message['id'] ?? null;

        WhatsappMessage::updateOrCreate(
            ['message_id' => $messageId],
            [
                'contact_id' => $contact->id,
                'business_profile_id' => $this->businessProfile->id,
                'sender_type' => 'client',
                'receiver_type' => 'system',
                'content' => $mediaType === 'image'
                    ? ($mediaData['caption'] ?? '')
                    : json_encode($mediaData),
                'type' => $mediaType,
                'status' => 'received',
                'metadata' => [
                    'cart_id' => $cart->id,
                    'payment_proof' => true,
                    'timestamp' => $message['timestamp'] ?? null,
                    'media_id' => $mediaData['id'] ?? null,
                    'mime_type' => $mediaData['mime_type'] ?? null,
                    'caption' => $mediaData['caption'] ?? null,
                ],
            ]
        );

        $cart->attachPaymentProof([
            'message_id' => $messageId,
            'type' => $mediaType,
            'media_id' => $mediaData['id'] ?? null,
            'mime_type' => $mediaData['mime_type'] ?? null,
            'filename' => $mediaData['filename'] ?? null,
            'received_at' => now()->toIso8601String(),
        ]);

        // Meta puede expirar el enlace de medios; la descarga se ejecuta tras
        // responder al webhook para no demorar la recepción del mensaje.
        $cartId = $cart->id;
        $storedMessageId = $messageId;
        dispatch(function () use ($cartId, $storedMessageId) {
            $order = WhatsappCart::find($cartId);
            $proofMessage = $storedMessageId
                ? WhatsappMessage::where('message_id', $storedMessageId)->first()
                : null;

            if ($order && $proofMessage) {
                app(PaymentProofArchiveService::class)->archive($order, $proofMessage);
            }
        })->afterResponse();

        $this->syncOrderDetails($cart);

        Log::info('[registrarComprobantePago] Comprobante asociado al pedido', [
            'cart_id' => $cart->id,
            'contact_id' => $contact->id,
            'message_id' => $messageId,
            'media_type' => $mediaType,
        ]);

        return $this->buildPaymentProofSuccessPayload($contact, $cart);
    }

    private function triggerAgentHandoff(WhatsappContact $contact, string $phone, string $source = 'unknown'): void
    {
        $contact->requestAgentHandoff($source);
        $contact->update(['bot_enabled' => false]);

        $agentMessage = $this->buildMarketingStepPayload(MarketingStepKey::AGENT_HANDOFF, $contact)
            ?? ['type' => 'text', 'text' => ['body' => 'Te conectamos con un asesor. Espera un momento, por favor.']];

        Log::info('[triggerAgentHandoff] Solicitud de asesor registrada', [
            'contact_id' => $contact->id,
            'phone' => substr($phone, 0, 4).'****'.substr($phone, -4),
            'source' => $source,
        ]);

        $this->sendMessage($phone, $agentMessage);
    }

    private function isAgentRequestButton(string $buttonId, ?string $buttonTitle = null): bool
    {
        $buttonId = strtolower(trim($buttonId));

        if (in_array($buttonId, ['menu_agent', 'agent', 'hablar_asesor', 'hablar_con_asesor'], true)) {
            return true;
        }

        if (! $buttonTitle) {
            return false;
        }

        $title = mb_strtolower(trim($buttonTitle));

        return str_contains($title, 'asesor')
            || str_contains($title, 'humano')
            || str_contains($title, 'agente');
    }

    private function isAgentRequestText(string $text): bool
    {
        $normalized = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)));
        $patterns = [
            'asesor',
            'agente',
            'humano',
            'persona real',
            'atencion humana',
            'atención humana',
            'hablar con asesor',
            'hablar con un asesor',
            'hablar con humano',
            'necesito un asesor',
            'quiero un asesor',
            'quiero hablar con',
            'especialista',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reconoce palabras comunes (saludo, agradecimiento, o que pide
     * catálogo/categorías/información) para responder algo acorde en vez del
     * genérico "no entendí ese mensaje", usado cuando el cliente escribe
     * texto libre teniendo un paso de checkout pendiente (ver
     * cartHasPendingCheckoutStep). No interrumpe el paso pendiente: solo
     * cambia el mensaje con el que se reenvía.
     */
    private function matchCommonIntentReply(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text));

        if ($this->isGreetingMessage($normalized)) {
            return '👋 ¡Hola! Antes de seguir, solo falta este paso de tu pedido:';
        }

        if (preg_match('/^(gracias|muchas gracias|thanks|thank you)\b/u', $normalized)) {
            return '🙏 ¡Con gusto! Solo falta este paso para terminar tu pedido:';
        }

        if (preg_match('/(catalogo|catálogo|producto|categor[ií]a)/u', $normalized)) {
            return '🛍️ Claro, en un momento te muestro eso — primero terminemos este paso de tu pedido:';
        }

        if (preg_match('/(informacion|información|ayuda|horario|direccion|dirección|ubicaci[oó]n)/u', $normalized)) {
            return 'ℹ️ Con gusto te ayudo con eso — primero terminemos este paso de tu pedido:';
        }

        return null;
    }
}
