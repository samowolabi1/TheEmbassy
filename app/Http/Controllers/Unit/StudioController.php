<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\StudioGuest;
use App\Models\StudioPreset;
use App\Models\StudioScreen;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The live studio's control room. The team puts things on screen here (scenes, name captions,
 * scriptures, a ticker, the logo) and OBS shows them over the cameras through the overlay link.
 * Remote guests join with a link and appear in OBS as a source.
 */
class StudioController extends Controller
{
    public function index(Unit $unit): View
    {
        $screen = StudioScreen::current();

        return view('unit.media.studio', [
            'unit' => $unit,
            'screen' => $screen,
            'captions' => StudioPreset::where('type', 'caption')->orderBy('line1')->get(),
            'scriptures' => StudioPreset::where('type', 'scripture')->latest('id')->get(),
            'guests' => StudioGuest::latest('id')->get(),
            'nextService' => Service::where('starts_at', '>=', now())->orderBy('starts_at')->first(),
            'canLead' => Gate::allows('lead-media', $unit),
        ]);
    }

    /**
     * A full-screen scene (starting soon, be right back, thanks for watching, a message), or none.
     */
    public function scene(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'scene' => ['nullable', Rule::in(array_keys(StudioScreen::SCENES))],
            'scene_text' => ['nullable', 'string', 'max:200'],
            'countdown_to' => ['nullable', 'date'],
        ]);
        $scene = $data['scene'] ?? null;
        StudioScreen::current()->change([
            'scene' => $scene,
            'scene_text' => $scene ? ($data['scene_text'] ?? null) : null,
            'countdown_to' => $scene === 'starting' && ! empty($data['countdown_to']) ? Carbon::parse($data['countdown_to'], config('app.timezone')) : null,
        ]);

        return $this->done($request, $scene ? StudioScreen::SCENES[$scene].' is on screen.' : 'Scene cleared: the cameras are showing.');
    }

    public function caption(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        if ($request->input('action') === 'hide') {
            StudioScreen::current()->change(['lower_third' => null]);

            return $this->done($request, 'Caption hidden.');
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'title' => ['nullable', 'string', 'max:120']]);
        StudioScreen::current()->change(['lower_third' => ['name' => $data['name'], 'title' => $data['title'] ?? null]]);
        if ($request->boolean('save')) {
            StudioPreset::firstOrCreate(['type' => 'caption', 'line1' => $data['name'], 'line2' => $data['title'] ?? null]);
        }

        return $this->done($request, 'Caption on screen.');
    }

    public function scripture(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        if ($request->input('action') === 'hide') {
            StudioScreen::current()->change(['scripture' => null]);

            return $this->done($request, 'Scripture hidden.');
        }
        $data = $request->validate(['reference' => ['required', 'string', 'max:120'], 'text' => ['required', 'string', 'max:600']]);
        StudioScreen::current()->change(['scripture' => ['reference' => $data['reference'], 'text' => $data['text']]]);
        if ($request->boolean('save')) {
            StudioPreset::firstOrCreate(['type' => 'scripture', 'line1' => $data['reference'], 'line2' => $data['text']]);
        }

        return $this->done($request, 'Scripture on screen.');
    }

    public function ticker(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        $text = $request->input('action') === 'hide' ? null : $request->validate(['ticker' => ['required', 'string', 'max:500']])['ticker'];
        StudioScreen::current()->change(['ticker' => $text]);

        return $this->done($request, $text ? 'Ticker running.' : 'Ticker hidden.');
    }

    public function logo(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        $screen = StudioScreen::current();
        $screen->change(['show_logo' => ! $screen->show_logo]);

        return $this->done($request, $screen->show_logo ? 'Logo on screen.' : 'Logo hidden.');
    }

    /**
     * Take everything off the screen at once.
     */
    public function clear(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        StudioScreen::current()->change(['scene' => null, 'scene_text' => null, 'countdown_to' => null, 'lower_third' => null, 'scripture' => null, 'ticker' => null]);

        return $this->done($request, 'Everything cleared.');
    }

    public function destroyPreset(Request $request, Unit $unit, StudioPreset $preset): RedirectResponse
    {
        $preset->delete();

        return back()->with('status', 'Removed.');
    }

    public function storeGuest(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $guest = new StudioGuest($data);
        $guest->stream_id = Str::lower(Str::random(16));
        $guest->created_by = $request->user()->id;
        $guest->save();

        return back()->with('status', "Guest link made for {$guest->name}. Send them their link and add theirs to OBS.");
    }

    public function destroyGuest(Request $request, Unit $unit, StudioGuest $guest): RedirectResponse
    {
        $guest->delete();

        return back()->with('status', 'Guest link removed. It stops working for new connections.');
    }

    /**
     * A new overlay link, if the old one got out. OBS needs the new link.
     */
    public function regenerate(Request $request, Unit $unit): RedirectResponse
    {
        StudioScreen::current()->forceFill(['token' => Str::random(48)])->save();

        return back()->with('status', 'New overlay link made. Update the browser source in OBS.');
    }

    private function done(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'state' => StudioScreen::current()->state()]);
        }

        return back()->with('status', $message);
    }
}
