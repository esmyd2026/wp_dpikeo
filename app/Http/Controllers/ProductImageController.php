<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    /**
     * Serves uploaded product photos when public/storage is a real directory
     * (for example, because it also contains branded assets) instead of the
     * usual Laravel storage symlink.
     */
    public function show(string $filename): Response
    {
        abort_unless(
            preg_match('/^[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$/i', $filename) === 1,
            404
        );

        $path = 'product-images/'.$filename;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}
