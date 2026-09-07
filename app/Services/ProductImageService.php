<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageService
{
    public function resolveUrl(?string $image): ?string
    {
        if (!$image || trim($image) === '') {
            return null;
        }

        $image = trim($image);

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return asset('storage/' . ltrim($image, '/'));
    }

    /**
     * Devuelve una URL segura para vistas web abiertas desde cualquier host.
     *
     * Los archivos locales usan una ruta relativa al origen para evitar que un
     * micrositio abierto mediante otro puerto, dominio o túnel intente cargar
     * las imágenes desde el APP_URL configurado en el servidor. Las imágenes
     * externas conservan su URL absoluta.
     */
    public function resolveWebUrl(?string $image): ?string
    {
        if (!$image || trim($image) === '') {
            return null;
        }

        $image = trim($image);

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return '/storage/' . ltrim($image, '/');
    }

    public function store(UploadedFile $file, ?string $oldPath = null, string $directory = 'product-images'): string
    {
        $this->delete($oldPath);

        return $file->store($directory, 'public');
    }

    /**
     * Copia un archivo ya almacenado a una nueva ruta independiente, para que
     * dos registros (por ejemplo, un producto duplicado) no compartan el
     * mismo archivo físico y puedan eliminarse por separado sin afectarse.
     */
    public function copy(?string $path, string $directory = 'product-images'): ?string
    {
        if (!$path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $newPath = $directory . '/' . Str::random(40) . '.' . $extension;
        Storage::disk('public')->copy($path, $newPath);

        return $newPath;
    }

    public function delete(?string $path): void
    {
        if (!$path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
