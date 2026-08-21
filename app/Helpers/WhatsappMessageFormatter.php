<?php

namespace App\Helpers;

class WhatsappMessageFormatter
{
    // WhatsApp API limits
    const BUTTON_TITLE_MAX_LENGTH = 20;
    const BUTTON_DESCRIPTION_MAX_LENGTH = 72;
    const BODY_TEXT_MAX_LENGTH = 1024;

    /**
     * Format a button title to meet WhatsApp's length requirements
     *
     * @param string $title
     * @return string
     */
    public static function formatButtonTitle(string $title): string
    {
        if (strlen($title) <= self::BUTTON_TITLE_MAX_LENGTH) {
            return $title;
        }

        // If the title is too long, truncate it and add ellipsis
        return substr($title, 0, self::BUTTON_TITLE_MAX_LENGTH - 3) . '...';
    }

    /**
     * Format a button description to meet WhatsApp's length requirements
     *
     * @param string $description
     * @return string
     */
    public static function formatButtonDescription(string $description): string
    {
        if (strlen($description) <= self::BUTTON_DESCRIPTION_MAX_LENGTH) {
            return $description;
        }

        // If the description is too long, truncate it and add ellipsis
        return substr($description, 0, self::BUTTON_DESCRIPTION_MAX_LENGTH - 3) . '...';
    }

    /**
     * Format body text to meet WhatsApp's length requirements
     *
     * @param string $text
     * @return string
     */
    public static function formatBodyText(string $text): string
    {
        if (strlen($text) <= self::BODY_TEXT_MAX_LENGTH) {
            return $text;
        }

        // If the text is too long, truncate it and add ellipsis
        return substr($text, 0, self::BODY_TEXT_MAX_LENGTH - 3) . '...';
    }

    /**
     * Format an interactive message to ensure all components meet WhatsApp's requirements
     *
     * @param array $message
     * @return array
     */
    public static function formatInteractiveMessage(array $message): array
    {
        if (isset($message['interactive']['body']['text'])) {
            $message['interactive']['body']['text'] = self::formatBodyText($message['interactive']['body']['text']);
        }

        if (isset($message['interactive']['action']['buttons'])) {
            foreach ($message['interactive']['action']['buttons'] as &$button) {
                if (isset($button['reply']['title'])) {
                    $button['reply']['title'] = self::formatButtonTitle($button['reply']['title']);
                }
            }
        }

        return $message;
    }

    /**
     * Intenta decodificar contenido JSON de respuestas interactivas.
     */
    public static function parseJsonContent(?string $content): ?array
    {
        if ($content === null || $content === '') {
            return null;
        }

        $content = trim($content);
        if ($content === '' || !in_array($content[0], ['{', '['], true)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Texto legible para mostrar en el panel de chat (no JSON crudo).
     */
    public static function displayText(?string $content, ?string $type = null, ?array $metadata = null): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        if (!empty($metadata['interactive']) && is_array($metadata['interactive'])) {
            $interactive = $metadata['interactive'];
            if (!empty($interactive['button_reply']['title'])) {
                return (string) $interactive['button_reply']['title'];
            }
            if (!empty($interactive['list_reply']['title'])) {
                return (string) $interactive['list_reply']['title'];
            }
        }

        $parsed = self::parseJsonContent($content);
        if ($parsed) {
            if (($parsed['type'] ?? null) === 'button_reply' && !empty($parsed['button_reply']['title'])) {
                return (string) $parsed['button_reply']['title'];
            }
            if (($parsed['type'] ?? null) === 'list_reply' && !empty($parsed['list_reply']['title'])) {
                return (string) $parsed['list_reply']['title'];
            }
            if (!empty($parsed['button_reply']['title'])) {
                return (string) $parsed['button_reply']['title'];
            }
            if (!empty($parsed['list_reply']['title'])) {
                return (string) $parsed['list_reply']['title'];
            }
            if (!empty($parsed['title'])) {
                return (string) $parsed['title'];
            }
        }

        if ($type === 'interactive' && !str_starts_with(trim($content), '{')) {
            return $content;
        }

        return $content;
    }

    /**
     * Descripción secundaria (listas / botones con detalle).
     */
    public static function displayDescription(?string $content, ?array $metadata = null): ?string
    {
        if (!empty($metadata['interactive']['list_reply']['description'])) {
            return (string) $metadata['interactive']['list_reply']['description'];
        }
        if (!empty($metadata['interactive']['button_reply']['description'])) {
            return (string) $metadata['interactive']['button_reply']['description'];
        }

        $parsed = self::parseJsonContent($content);
        if (!$parsed) {
            return null;
        }

        if (!empty($parsed['list_reply']['description'])) {
            return (string) $parsed['list_reply']['description'];
        }
        if (!empty($parsed['button_reply']['description'])) {
            return (string) $parsed['button_reply']['description'];
        }
        if (!empty($parsed['description'])) {
            return (string) $parsed['description'];
        }

        return null;
    }

    /**
     * Indica si el mensaje es una respuesta de botón o lista del cliente.
     */
    public static function isInteractiveReply(?string $content, ?string $type = null, ?array $metadata = null): bool
    {
        if ($type === 'interactive') {
            return true;
        }

        if (!empty($metadata['interactive'])) {
            return true;
        }

        $parsed = self::parseJsonContent($content);

        return $parsed && (
            isset($parsed['button_reply']) ||
            isset($parsed['list_reply']) ||
            in_array($parsed['type'] ?? null, ['button_reply', 'list_reply'], true)
        );
    }

    /**
     * Fecha/hora compacta para la lista de chats (estilo WhatsApp).
     */
    public static function formatSidebarDateTime(?\Carbon\Carbon $date): string
    {
        if (!$date) {
            return '';
        }

        if ($date->isToday()) {
            return $date->format('H:i');
        }

        if ($date->isYesterday()) {
            return 'Ayer ' . $date->format('H:i');
        }

        if ($date->isSameYear(now())) {
            return $date->format('d/m H:i');
        }

        return $date->format('d/m/Y H:i');
    }
}
