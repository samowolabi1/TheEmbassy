<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The message library, sermon series, and the live stream on the Watch page.
 */
class SermonController extends Controller
{
    public function index(): View
    {
        return view('admin.sermons.index', [
            'sermons' => Sermon::with('series')->orderByDesc('preached_on')->orderByDesc('id')->paginate(20),
            'series' => SermonSeries::withCount('sermons')->orderByDesc('is_current')->latest()->get(),
            'kinds' => Sermon::KINDS,
            'styles' => SermonSeries::STYLES,
            'live' => ['video_id' => config('church.live.video_id'), 'is_live' => (bool) config('church.live.is_live')],
        ]);
    }

    public function create(): View
    {
        return view('admin.sermons.form', $this->formData(new Sermon(['preached_on' => today(), 'kind' => 'sunday', 'is_published' => true, 'swatch' => 'navy'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $sermon = Sermon::create($this->validated($request));

        return redirect()->route('admin.sermons.index')->with('status', "\"{$sermon->title}\" added to the library.");
    }

    public function edit(Sermon $sermon): View
    {
        return view('admin.sermons.form', $this->formData($sermon));
    }

    public function update(Request $request, Sermon $sermon): RedirectResponse
    {
        $sermon->update($this->validated($request));

        return redirect()->route('admin.sermons.index')->with('status', "\"{$sermon->title}\" saved.");
    }

    public function destroy(Sermon $sermon): RedirectResponse
    {
        $sermon->delete();

        return redirect()->route('admin.sermons.index')->with('status', "\"{$sermon->title}\" removed.");
    }

    public function storeSeries(Request $request): RedirectResponse
    {
        $series = SermonSeries::create($this->seriesData($request));
        $this->onlyOneCurrent($series);

        return back()->with('status', "Series \"{$series->fullTitle()}\" added.");
    }

    public function updateSeries(Request $request, SermonSeries $series): RedirectResponse
    {
        $series->update($this->seriesData($request, $series));
        $this->onlyOneCurrent($series);

        return back()->with('status', "Series \"{$series->fullTitle()}\" saved.");
    }

    public function destroySeries(SermonSeries $series): RedirectResponse
    {
        $series->delete(); // its messages stay, without a series

        return back()->with('status', 'Series removed.');
    }

    /**
     * The YouTube video in the Watch page player, and whether to show "Live now".
     */
    public function live(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'video_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{6,20}$/'],
            'is_live' => ['nullable', 'boolean'],
        ], ['video_id.regex' => 'Paste just the video ID, e.g. dQw4w9WgXcQ (the part after watch?v= in the link).']);

        Setting::putMany(['church.live.video_id' => $data['video_id'] ?? null, 'church.live.is_live' => $request->boolean('is_live') ? '1' : '']);

        return back()->with('status', $request->boolean('is_live') ? 'The Watch page now shows you\'re live.' : 'Live stream saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(array_keys(Sermon::KINDS))],
            'sermon_series_id' => ['nullable', Rule::exists('sermon_series', 'id')],
            'preacher' => ['nullable', 'string', 'max:100'],
            'preached_on' => ['required', 'date'],
            'scripture' => ['nullable', 'string', 'max:60'],
            'length' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'swatch' => ['required', Rule::in(Sermon::SWATCHES)],
        ]);

        return [...$data, 'is_published' => $request->boolean('is_published')];
    }

    /**
     * @return array<string, mixed>
     */
    private function seriesData(Request $request, ?SermonSeries $series = null): array
    {
        $data = $request->validateWithBag('series', [
            'title' => ['required', 'string', 'max:40'],
            'accent' => ['nullable', 'string', 'max:40'],
            'style' => ['required', Rule::in(array_keys(SermonSeries::STYLES))],
            'url' => ['nullable', 'url:http,https', 'max:500'],
        ]);

        return [...$data, 'is_current' => $request->boolean('is_current')];
    }

    private function onlyOneCurrent(SermonSeries $series): void
    {
        if ($series->is_current) {
            DB::table('sermon_series')->where('id', '!=', $series->id)->update(['is_current' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Sermon $sermon): array
    {
        return [
            'sermon' => $sermon,
            'kinds' => Sermon::KINDS,
            'series' => SermonSeries::latest()->get(),
            'swatches' => Sermon::SWATCHES,
        ];
    }
}
