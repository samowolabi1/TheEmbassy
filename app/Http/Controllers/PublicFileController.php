<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves uploaded pictures from storage/app/public when the "public/storage" shortcut
 * (php artisan storage:link) can't be made, as on some shared hosts. Where the shortcut exists,
 * the web server sends the files itself and this never runs.
 */
class PublicFileController extends Controller
{
    private const TYPES = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'mp4', 'mp3', 'm4a', 'ico'];

    public function __invoke(string $path): BinaryFileResponse
    {
        abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TYPES, true), 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $headers = ['Cache-Control' => 'public, max-age=2592000, immutable', 'X-Content-Type-Options' => 'nosniff'];
        if (str_ends_with(strtolower($path), '.svg')) {
            // Pictures, never pages that could run scripts.
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($disk->path($path), $headers);
    }
}
