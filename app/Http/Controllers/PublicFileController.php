<?php

namespace App\Http\Controllers;

use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Old picture addresses (/storage/...) from before uploads moved to public/uploads:
 * links in emails, shared posts and search results. They're sent to the new address,
 * or, if a file wasn't moved yet, served from the old folder.
 */
class PublicFileController extends Controller
{
    private const TYPES = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'mp4', 'mp3', 'm4a', 'ico'];

    public function __invoke(string $path): RedirectResponse|BinaryFileResponse
    {
        abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TYPES, true), 404);

        if (Storage::disk('public')->exists($path)) {
            return redirect()->to(Storage::disk('public')->url($path), 301);
        }

        $old = Uploads::oldFolder().DIRECTORY_SEPARATOR.$path;
        abort_unless(is_file($old), 404);
        $headers = ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        if (str_ends_with(strtolower($path), '.svg')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($old, $headers);
    }
}
