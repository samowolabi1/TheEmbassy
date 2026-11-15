<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections on every page: no framing by other sites, no guessing file types, a
 * careful referrer, only the device features the app uses (location for check-in, camera and
 * microphone for the studio), and HTTPS-only once the site runs on https.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(self), payment=(), usb=()', false);
        if (! $headers->has('X-Frame-Options') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if ($request->isSecure() && app()->environment('production')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
