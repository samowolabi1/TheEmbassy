<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands out library files to people allowed to see them. Files are on the private disk, so
 * there is no public address to share by mistake.
 */
class MediaFileController extends Controller
{
    public function show(Request $request, MediaFile $file, string $mode): BinaryFileResponse|RedirectResponse
    {
        abort_unless($file->isVisibleTo($request->user()), 404);
        if ($file->isLink()) {
            return redirect()->away($file->url);
        }

        $path = $mode === 'thumb' ? ($file->thumb_path ?? abort(404)) : $file->path;
        $disk = Storage::disk(MediaFile::DISK);
        abort_unless($path && $disk->exists($path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];

        if ($mode === 'thumb') {
            return response()->file($disk->path($path), [...$headers, 'Content-Type' => 'image/webp']);
        }
        if ($mode === 'view' && $file->showsInline()) {
            // A file response, so videos can be skipped through (range requests).
            $csp = $file->extension() === 'pdf' ? [] : ['Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'"];

            return response()->file($disk->path($path), [...$headers, ...$csp, 'Content-Type' => $file->mime ?: 'application/octet-stream']);
        }

        return response()->download($disk->path($path), $file->original_name ?: basename($path), $headers);
    }
}
