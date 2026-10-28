<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\FirstTimerController;
use App\Models\FirstTimer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The first-timer card visitors fill in on their phone (e.g. from a QR code at the service).
 */
class WelcomeController extends Controller
{
    public function create(): View
    {
        return view('pages.welcome');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(FirstTimerController::rules());

        // A hidden "website" field that people never see; bots that fill it in are quietly ignored.
        if (! $request->filled('website')) {
            FirstTimer::create([...$data, 'visited_on' => today(), 'wants_to_join' => $request->boolean('wants_to_join')]);
        }

        return redirect()->route('welcome')->with('welcomed', strtok($data['name'], ' '));
    }
}
