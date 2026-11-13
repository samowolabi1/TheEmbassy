<?php

namespace App\Http\Controllers;

use App\Models\StudioScreen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The overlay OBS shows over the cameras (add it as a 1920 × 1080 browser source). It needs no
 * sign-in, so the link carries a long secret; the media leader can replace it.
 */
class StudioOverlayController extends Controller
{
    public function show(string $token): Response
    {
        $screen = $this->screen($token);

        return response()->view('studio.overlay', ['token' => $screen->token, 'state' => $screen->state()])
            ->header('X-Robots-Tag', 'noindex')->header('Referrer-Policy', 'no-referrer');
    }

    public function state(string $token): JsonResponse
    {
        return response()->json($this->screen($token)->state())->header('Cache-Control', 'no-store');
    }

    private function screen(string $token): StudioScreen
    {
        $screen = StudioScreen::current();
        abort_unless(hash_equals($screen->token, $token), 404);

        return $screen;
    }
}
