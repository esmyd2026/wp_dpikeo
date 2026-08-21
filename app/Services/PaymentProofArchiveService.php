<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Conserva una copia privada de comprobantes que WhatsApp puede expirar. */
class PaymentProofArchiveService
{
    private const MAX_BYTES = 12 * 1024 * 1024;

    public function archive(WhatsappCart $order, WhatsappMessage $message): bool
    {
        $media = app(WhatsappMediaService::class)->fetchMedia($message);
        if (!$media || strlen($media['body']) > self::MAX_BYTES) {
            return false;
        }

        $extension = $this->extension($media['filename'], $media['content_type']);
        $path = 'payment-proofs/order-'.$order->id.'/'.now()->format('YmdHis').'-'.Str::random(12).'.'.$extension;
        Storage::disk('local')->put($path, $media['body']);

        $metadata = $order->metadata ?? [];
        $proof = is_array($metadata['payment_proof'] ?? null) ? $metadata['payment_proof'] : [];
        $proof['backup_path'] = $path;
        $proof['backup_filename'] = $this->safeFilename($media['filename'], $extension);
        $proof['backup_content_type'] = $media['content_type'];
        $proof['archived_at'] = now()->toIso8601String();
        $metadata['payment_proof'] = $proof;
        $order->metadata = $metadata;
        $order->save();

        return true;
    }

    /** @return array{body:string,content_type:string,filename:string}|null */
    public function read(WhatsappCart $order): ?array
    {
        $proof = is_array($order->metadata['payment_proof'] ?? null) ? $order->metadata['payment_proof'] : [];
        $path = (string) ($proof['backup_path'] ?? '');

        if (!str_starts_with($path, 'payment-proofs/') || !Storage::disk('local')->exists($path)) {
            return null;
        }

        // La extensión real del archivo ya está en su propia ruta (se fijó al
        // archivar, ver archive()); usarla aquí evita el bug de antes, que
        // pasaba 'bin' fijo a safeFilename() y terminaba duplicando la
        // extensión (p.ej. "comprobante.jpg.bin").
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';

        return [
            'body' => Storage::disk('local')->get($path),
            'content_type' => (string) ($proof['backup_content_type'] ?? 'application/octet-stream'),
            'filename' => $this->safeFilename((string) ($proof['backup_filename'] ?? 'comprobante'), $extension),
        ];
    }

    private function extension(string $filename, string $contentType): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            return $ext;
        }

        return match (strtolower(explode(';', $contentType)[0])) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf', default => 'bin',
        };
    }

    private function safeFilename(string $filename, string $extension): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($filename)) ?: 'comprobante.'.$extension;

        return Str::endsWith(strtolower($base), '.'.$extension) ? $base : $base.'.'.$extension;
    }
}
