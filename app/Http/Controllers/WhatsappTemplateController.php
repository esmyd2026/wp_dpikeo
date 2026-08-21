<?php

namespace App\Http\Controllers;

use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappTemplateController extends Controller
{
    protected $baseUrl;
    protected $apiVersion;
    protected $apiToken;
    protected $businessId;

    public function __construct()
    {
        // Antes leía env() directamente: con `php artisan config:cache` (que
        // deploy.sh corre en producción) esas llamadas devuelven null, porque
        // env() deja de leer el .env real una vez cacheada la config. Se usa
        // config('whatsapp.*'), que sí respeta la caché.
        $this->baseUrl = config('whatsapp.api_url', 'https://graph.facebook.com');
        $this->apiVersion = config('whatsapp.api_version', 'v22.0');
        $this->apiToken = config('whatsapp.token');
        $this->businessId = config('whatsapp.business_id');
    }

    public function getApprovedTemplates()
    {
        try {
            // Log environment variables (without sensitive data)
            Log::info('WhatsApp Template API Configuration', [
                'base_url' => $this->baseUrl,
                'api_version' => $this->apiVersion,
                'business_id' => $this->businessId ? 'Configured' : 'Not Configured',
                'WHATSAPP_TOKEN' => $this->apiToken ? 'Configured' : 'Not Configured'
            ]);

            if (!$this->businessId) {
                Log::error('WHATSAPP_BUSINESS_ID not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'WHATSAPP_BUSINESS_ID no está configurado en el archivo .env',
                    'debug_info' => [
                        'base_url' => $this->baseUrl,
                        'api_version' => $this->apiVersion
                    ]
                ], 400);
            }

            if (!$this->apiToken) {
                Log::error('WHATSAPP_TOKEN not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'WHATSAPP_TOKEN no está configurado en el archivo .env',
                    'debug_info' => [
                        'base_url' => $this->baseUrl,
                        'api_version' => $this->apiVersion,
                        'business_id' => $this->businessId
                    ]
                ], 400);
            }

            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->businessId}/message_templates";
            Log::info('Making API request to WhatsApp', ['url' => $url]);

            try {
                $response = Http::withToken($this->apiToken)
                    ->timeout(30)
                    ->get($url);

                // Log raw response for debugging
                Log::info('Raw WhatsApp API Response', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body()
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (!isset($data['data'])) {
                        Log::error('Invalid API response format', ['response' => $data]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Formato de respuesta inválido de la API de WhatsApp',
                            'debug_info' => $data
                        ], 500);
                    }

                    $templates = $data['data'];
                    Log::info('Templates found', [
                        'count' => count($templates),
                        'templates' => $templates
                    ]);

                    // Filtrar solo las plantillas aprobadas
                    $approvedTemplates = array_filter($templates, function($template) {
                        $isApproved = strtoupper($template['status']) === 'APPROVED';
                        Log::info('Template status check', [
                            'template_id' => $template['id'],
                            'name' => $template['name'],
                            'status' => $template['status'],
                            'is_approved' => $isApproved
                        ]);
                        return $isApproved;
                    });

                    Log::info('Approved templates', [
                        'count' => count($approvedTemplates),
                        'templates' => $approvedTemplates
                    ]);

                    // Actualizar o crear las plantillas en la base de datos
                    foreach ($approvedTemplates as $template) {
                        try {
                            $savedTemplate = WhatsappTemplate::updateOrCreate(
                                ['template_id' => $template['id']],
                                [
                                    'name' => $template['name'],
                                    'category' => $template['category'],
                                    'content' => $this->extractTemplateContent($template['components'] ?? []),
                                    'language' => $template['language'],
                                    'status' => strtolower($template['status']),
                                    'variables' => $this->extractVariables($template['components'] ?? []),
                                    'components' => $template['components'] ?? []
                                ]
                            );
                            Log::info('Template saved', [
                                'template_id' => $template['id'],
                                'name' => $template['name'],
                                'status' => $template['status']
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error saving template', [
                                'template_id' => $template['id'],
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'templates' => array_values($approvedTemplates),
                        'debug_info' => [
                            'total_templates' => count($templates),
                            'approved_templates' => count($approvedTemplates),
                            'api_url' => $url,
                            'raw_templates' => $templates
                        ]
                    ]);
                }

                Log::error('WhatsApp API Error', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener las plantillas',
                    'error' => $response->json(),
                    'debug_info' => [
                        'status_code' => $response->status(),
                        'api_url' => $url,
                        'raw_response' => $response->body()
                    ]
                ], $response->status());

            } catch (\Exception $e) {
                Log::error('HTTP Request Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al conectar con la API de WhatsApp',
                    'error' => $e->getMessage(),
                    'debug_info' => [
                        'api_url' => $url
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al obtener plantillas de WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las plantillas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function extractVariables($components)
    {
        $variables = [];
        foreach ($components as $component) {
            if (isset($component['parameters'])) {
                foreach ($component['parameters'] as $parameter) {
                    if (isset($parameter['text'])) {
                        $variables[] = $parameter['text'];
                    }
                }
            }
        }
        return $variables;
    }

    protected function extractTemplateContent($components)
    {
        $content = '';
        foreach ($components as $component) {
            if ($component['type'] === 'BODY') {
                $content = $component['text'] ?? '';
                break;
            }
        }
        return $content;
    }
}
