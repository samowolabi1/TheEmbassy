<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrayerRequestRequest;
use App\Models\PrayerRequest;
use Illuminate\Http\RedirectResponse;

class PrayerRequestController extends Controller
{
    /**
     * Save a prayer request from the home page form.
     */
    public function store(StorePrayerRequestRequest $request): RedirectResponse
    {
        // A hidden "website" field that people never see; bots that fill it in are quietly ignored.
        if (! $request->filled('website')) {
            PrayerRequest::create([
                ...$request->safe()->only(['name', 'contact', 'request']),
                'is_private' => $request->boolean('is_private'),
            ]);
        }

        return redirect()->to(route('home').'#prayer')->with('prayer_sent', true);
    }
}
