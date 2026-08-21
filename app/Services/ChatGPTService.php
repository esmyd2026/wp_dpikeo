<?php

namespace App\Services;

use App\Models\WhatsappChatbotConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatGPTService
{
    protected $config;
    protected $client;

    public function __construct(WhatsappChatbotConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Verifica si el servicio de ChatGPT está habilitado
     */
    public function isEnabled(): bool
    {
        if (!config('whatsapp.chatgpt.enabled', false)) {
            return false;
        }

        return $this->config->chatgpt_enabled && !empty($this->config->chatgpt_api_key);
    }

    /**
     * Realiza una consulta a ChatGPT
     */
    public function query(string $message): ?string
    {
        if (!$this->isEnabled()) {
            Log::error('ChatGPT Service Error: Service not enabled or missing API key');
            return null;
        }

        try {
            $requestData = [
                'model' => $this->config->chatgpt_model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->config->chatgpt_system_prompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $message
                    ]
                ],
                'max_tokens' => $this->config->chatgpt_max_tokens,
                'temperature' => $this->config->chatgpt_temperature,
                ...$this->getAdditionalParams()
            ];

            Log::info('ChatGPT Request', [
                'model' => $this->config->chatgpt_model,
                'max_tokens' => $this->config->chatgpt_max_tokens,
                'temperature' => $this->config->chatgpt_temperature
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config->chatgpt_api_key,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('ChatGPT Response', ['response' => $responseData]);
                return $responseData['choices'][0]['message']['content'] ?? null;
            }

            $errorData = $response->json();
            Log::error('ChatGPT API Error', [
                'status' => $response->status(),
                'error' => $errorData,
                'request' => $requestData
            ]);

            throw new \Exception(
                "Error de API: " . ($errorData['error']['message'] ?? 'Error desconocido') .
                " (Status: " . $response->status() . ")"
            );

        } catch (\Exception $e) {
            Log::error('ChatGPT Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene los parámetros adicionales de configuración
     */
    protected function getAdditionalParams(): array
    {
        if (empty($this->config->chatgpt_additional_params)) {
            return [];
        }

        // Si es un string JSON, lo convertimos a array
        if (is_string($this->config->chatgpt_additional_params)) {
            $params = json_decode($this->config->chatgpt_additional_params, true);
            return is_array($params) ? $params : [];
        }

        // Si ya es un array, lo retornamos directamente
        return is_array($this->config->chatgpt_additional_params)
            ? $this->config->chatgpt_additional_params
            : [];
    }
}

