<?php

namespace App\Services;

use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;

class WhatsappMediaService
{
    public function resolveMediaId(WhatsappMessage $message): ?string
    {
        $metadata = $message->metadata ?? [];
        if (!empty($metadata['media_id'])) {
            return (string) $metadata['media_id'];
        }

        $content = json_decode((string) $message->content, true);
        if (is_array($content) && !empty($content['id'])) {
            return (string) $content['id'];
        }

        return null;
    }

    public function resolveFilename(WhatsappMessage $message): string
    {
        $metadata = $message->metadata ?? [];
        if (!empty($metadata['filename'])) {
            return (string) $metadata['filename'];
        }

        $content = json_decode((string) $message->content, true);
        if (is_array($content) && !empty($content['filename'])) {
            return (string) $content['filename'];
        }

        return $message->type === 'document' ? 'comprobante.pdf' : 'comprobante.jpg';
    }

    /** @return array{body: string, content_type: string, filename: string}|null */
    public function fetchMedia(WhatsappMessage $message): ?array
    {
        $mediaId = $this->resolveMediaId($message);
        if (!$mediaId) {
            return null;
        }

        $apiVersion = config('whatsapp.api_version', 'v22.0');
        $token = config('whatsapp.token');

        $metaResponse = Http::withToken($token)
            ->timeout(10)
            ->get("https://graph.facebook.com/{$apiVersion}/{$mediaId}");

        if (!$metaResponse->successful()) {
            return null;
        }

        $mediaUrl = $metaResponse->json('url');
        if (!$mediaUrl) {
            return null;
        }

        $fileResponse = Http::withToken($token)
            ->timeout(15)
            ->get($mediaUrl);

        if (!$fileResponse->successful()) {
            return null;
        }

        return [
            'body' => $fileResponse->body(),
            'content_type' => $fileResponse->header('Content-Type') ?? 'application/octet-stream',
            'filename' => $this->resolveFilename($message),
        ];
    }
}
