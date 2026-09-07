<?php

namespace App\Services\Whatsapp;

class WhatsappMessagePayload
{
    public static function text(string $body): array
    {
        return ['type' => 'text', 'text' => ['body' => $body]];
    }

    public static function image(string $url, ?string $caption = null): array
    {
        $payload = [
            'type' => 'image',
            'image' => ['link' => $url],
        ];

        if ($caption !== null && $caption !== '') {
            $payload['image']['caption'] = self::truncate($caption, 1024);
        }

        return $payload;
    }

    /**
     * Mensaje con el botón nativo "Enviar ubicación" de WhatsApp (abre el
     * selector de ubicación del cliente) -- el texto del botón lo pone
     * WhatsApp según el idioma del dispositivo, no es personalizable, solo
     * el cuerpo de arriba. Al compartir, llega como mensaje type=location al
     * webhook (ver WhatsappService::handleLocationMessage()).
     */
    public static function locationRequest(string $body): array
    {
        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'location_request_message',
                'body' => ['text' => self::truncate($body, 1024)],
                'action' => ['name' => 'send_location'],
            ],
        ];
    }

    public static function buttons(string $body, array $buttons, ?array $header = null, ?string $footer = null): array
    {
        $formatted = [];
        foreach (array_slice($buttons, 0, 3) as $button) {
            $formatted[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string) ($button['id'] ?? ''),
                    'title' => mb_substr((string) ($button['title'] ?? 'Opción'), 0, 20),
                ],
            ];
        }

        $interactive = [
            'type' => 'button',
            'body' => ['text' => self::truncate($body, 1024)],
            'action' => ['buttons' => $formatted],
        ];

        if ($header) {
            $interactive['header'] = self::normalizeHeader($header);
        }
        if ($footer) {
            $interactive['footer'] = ['text' => self::truncate($footer, 60)];
        }

        return ['type' => 'interactive', 'interactive' => $interactive];
    }

    public static function list(string $body, string $buttonLabel, array $sections, ?array $header = null, ?string $footer = null): array
    {
        $normalizedSections = [];
        $rowCount = 0;

        foreach ($sections as $section) {
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                if ($rowCount >= 10) {
                    break 2;
                }
                $item = [
                    'id' => (string) ($row['id'] ?? ''),
                    'title' => self::truncate((string) ($row['title'] ?? ''), 24),
                ];
                if (!empty($row['description'])) {
                    $item['description'] = self::truncate((string) $row['description'], 72);
                }
                $rows[] = $item;
                $rowCount++;
            }
            if (!empty($rows)) {
                $normalizedSections[] = [
                    'title' => self::truncate((string) ($section['title'] ?? 'Opciones'), 24),
                    'rows' => $rows,
                ];
            }
        }

        $interactive = [
            'type' => 'list',
            'body' => ['text' => self::truncate($body, 1024)],
            'action' => [
                'button' => self::truncate($buttonLabel, 20),
                'sections' => $normalizedSections,
            ],
        ];

        // A diferencia de los mensajes de botones, Meta rechaza (#131009) un
        // encabezado que no sea de texto en mensajes tipo "list" -- si el
        // encabezado configurado es de imagen/video/documento, se descarta acá
        // como resguardo (quien arma el mensaje es responsable de mandar esa
        // imagen aparte antes, ver UsesMarketingFlow::buildMarketingStepPayload()).
        if ($header && ($header['type'] ?? null) === 'text') {
            $interactive['header'] = self::normalizeHeader($header);
        }
        if ($footer) {
            $interactive['footer'] = ['text' => self::truncate($footer, 60)];
        }

        return ['type' => 'interactive', 'interactive' => $interactive];
    }

    public static function flow(
        string $body,
        string $flowId,
        string $flowToken,
        string $cta = 'Continuar',
        string $flowMessageVersion = '3',
        string $flowAction = 'navigate',
        ?array $flowActionPayload = null,
        ?array $header = null,
        ?string $footer = null
    ): array {
        $parameters = [
            'flow_message_version' => $flowMessageVersion,
            'flow_token' => $flowToken,
            'flow_id' => $flowId,
            'flow_cta' => self::truncate($cta, 20),
            'flow_action' => $flowAction,
        ];

        if ($flowActionPayload !== null) {
            $parameters['flow_action_payload'] = $flowActionPayload;
        }

        $interactive = [
            'type' => 'flow',
            'body' => ['text' => self::truncate($body, 1024)],
            'action' => [
                'name' => 'flow',
                'parameters' => $parameters,
            ],
        ];

        if ($header) {
            $interactive['header'] = self::normalizeHeader($header);
        }
        if ($footer) {
            $interactive['footer'] = ['text' => self::truncate($footer, 60)];
        }

        return ['type' => 'interactive', 'interactive' => $interactive];
    }

    /** Abre el catálogo visual nativo de Commerce Manager dentro de WhatsApp. */
    public static function catalog(string $body, string $catalogId, ?string $thumbnailRetailerId = null, ?string $footer = null): array
    {
        // En catalog_message el catálogo se toma de la vinculación del número
        // en WhatsApp Manager. Meta solo permite la miniatura opcional aquí;
        // catalog_id es válido en product/product_list, no en este payload.
        $parameters = [];
        if ($thumbnailRetailerId) {
            $parameters['thumbnail_product_retailer_id'] = $thumbnailRetailerId;
        }

        $interactive = [
            'type' => 'catalog_message',
            'body' => ['text' => self::truncate($body, 1024)],
            'action' => [
                'name' => 'catalog_message',
            ],
        ];

        if ($parameters !== []) {
            $interactive['action']['parameters'] = $parameters;
        }

        if ($footer) {
            $interactive['footer'] = ['text' => self::truncate($footer, 60)];
        }

        return ['type' => 'interactive', 'interactive' => $interactive];
    }

    public static function ctaUrl(string $body, string $buttonText, string $url, ?array $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type' => 'cta_url',
            'body' => ['text' => self::truncate($body, 1024)],
            'action' => [
                'name' => 'cta_url',
                'parameters' => [
                    'display_text' => self::truncate($buttonText, 20),
                    'url' => $url,
                ],
            ],
        ];

        if ($header) {
            $interactive['header'] = self::normalizeHeader($header);
        }
        if ($footer) {
            $interactive['footer'] = ['text' => self::truncate($footer, 60)];
        }

        return ['type' => 'interactive', 'interactive' => $interactive];
    }

    protected static function normalizeHeader(array $header): array
    {
        if (($header['type'] ?? '') === 'image' && !empty($header['_image_path'])) {
            return [
                'type' => 'image',
                'image' => [
                    'link' => asset('storage/' . ltrim($header['_image_path'], '/')),
                ],
            ];
        }

        unset($header['_image_path']);

        return $header;
    }

    public static function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }
}
