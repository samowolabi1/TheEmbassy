<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The installable app: manifest, icons, service worker, offline page, and notification sign-ups.
 */
class AppController extends Controller
{
    public const ICON_SIZES = [192, 512];

    public function manifest(): JsonResponse
    {
        $navy = config('church.brand.colors.navy');
        $theme = is_string($navy) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $navy) ? $navy : '#0A1D3F';

        return response()->json([
            'name' => config('church.brand.app_name'),
            'short_name' => config('church.brand.wordmark') ? ucwords(strtolower((string) config('church.brand.wordmark'))) : config('church.short_name'),
            'description' => config('church.description'),
            'id' => '/',
            'start_url' => '/?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#FBF8F1',
            'theme_color' => $theme,
            'icons' => collect(self::ICON_SIZES)->map(fn ($size) => [
                'src' => route('app.icon', $size, false),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ])->all(),
            'shortcuts' => [
                ['name' => 'Watch live', 'url' => '/watch'],
                ['name' => 'Check in', 'url' => '/check-in'],
                ['name' => 'Give', 'url' => '/give'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * The logo on a square, padded so phones can crop it to a circle or rounded square.
     */
    public function icon(int $size): Response
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);
        $logo = public_path(config('church.brand.logo'));
        $key = 'app-icon.'.$size.'.'.md5($logo.'|'.(is_file($logo) ? filemtime($logo) : 0));

        $png = Cache::rememberForever($key, function () use ($logo, $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            $source = is_file($logo) ? @imagecreatefromstring((string) file_get_contents($logo)) : false;
            if ($source) {
                // Keep the logo inside the middle 70%, the part every icon shape shows.
                $scale = min($size * 0.7 / imagesx($source), $size * 0.7 / imagesy($source));
                $w = (int) round(imagesx($source) * $scale);
                $h = (int) round(imagesy($source) * $scale);
                imagecopyresampled($canvas, $source, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, imagesx($source), imagesy($source));
            }
            ob_start();
            imagepng($canvas, null, 9);

            return base64_encode((string) ob_get_clean());
        });

        return response(base64_decode($png), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    /**
     * The service worker. Served from the root so it covers the whole site.
     */
    public function serviceWorker(): Response
    {
        // A new build changes the version, so phones pick up the new worker and clear old files.
        $version = substr(md5((string) @filemtime(public_path('build/manifest.json')).config('church.brand.logo')), 0, 10);
        $assets = [route('app.offline', [], false), route('app.icon', 192, false)];

        return response()
            ->view('app.service-worker', ['version' => $version, 'precache' => $assets])
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Cache-Control', 'no-cache')
            ->header('Service-Worker-Allowed', '/');
    }

    public function offline(): View
    {
        return view('app.offline');
    }

    /**
     * Save a phone or browser that allowed notifications.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);
        // Only real push services: the server sends to this address.
        abort_unless(PushSubscription::allowedEndpoint($data['endpoint']), 422, 'Unsupported push service.');

        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'user_id' => $request->user()?->id,
            'failures' => 0,
        ]);

        return response()->json(['subscribed' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::where('endpoint_hash', hash('sha256', $request->string('endpoint')))->delete();

        return response()->json(['subscribed' => false]);
    }
}
