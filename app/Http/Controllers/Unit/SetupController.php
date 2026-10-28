<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceSetup;
use App\Models\Setting;
use App\Models\SetupTask;
use App\Models\StreamDestination;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Getting each service ready: the media unit's checklist, the stream's title and description,
 * the stream keys for the streaming software, and going live on the Watch page.
 *
 * The streaming itself runs in OBS, vMix or a hardware encoder: browsers can't send RTMP, so the
 * app keeps the keys and settings the encoder needs and switches the website's player on and off.
 */
class SetupController extends Controller
{
    public function index(Unit $unit): View
    {
        return view('unit.media.setup-index', [
            'unit' => $unit,
            'services' => Service::where('held_on', '>=', today()->subDay()->startOfDay())->orderBy('held_on')->orderBy('starts_at')->take(8)->get()
                ->each(fn (Service $s) => $s->setRelation('setup', ServiceSetup::withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('done_at')])->firstWhere('service_id', $s->id))),
            'destinations' => StreamDestination::orderBy('platform')->get(),
            'canLead' => Gate::allows('lead-media', $unit),
            'live' => ServiceSetup::whereNotNull('went_live_at')->whereNull('ended_at')->with('service')->first(),
        ]);
    }

    /**
     * Opening a service's setup the first time copies in the unit's checklist.
     */
    public function show(Unit $unit, Service $service): View
    {
        $setup = ServiceSetup::firstOrCreate(['service_id' => $service->id], ['stream_title' => $service->label().' · '.($service->starts_at ?? $service->held_on)->format('j M Y')]);
        if ($setup->wasRecentlyCreated) {
            foreach ($unit->checklistItems() as $i => $item) {
                $setup->tasks()->create(['title' => $item, 'sort' => $i]);
            }
        }

        return view('unit.media.setup', [
            'unit' => $unit,
            'service' => $service,
            'setup' => $setup->load('tasks.member', 'tasks.doer'),
            'members' => $unit->members()->active()->orderBy('name')->get(['id', 'name']),
            'canLead' => Gate::allows('lead-media', $unit),
            'destinations' => StreamDestination::where('is_active', true)->get(),
            'duties' => $unit->duties()->with('member')->where('service_id', $service->id)->get(),
        ]);
    }

    public function update(Request $request, Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::where('service_id', $service->id)->firstOrFail();
        $setup->update($request->validate([
            'stream_title' => ['nullable', 'string', 'max:100'],
            'stream_description' => ['nullable', 'string', 'max:5000'],
            'youtube_video_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{6,20}$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['youtube_video_id.regex' => 'Paste just the video ID, e.g. dQw4w9WgXcQ.']));

        return back()->with('status', 'Saved.');
    }

    public function addTask(Request $request, Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::where('service_id', $service->id)->firstOrFail();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', Rule::exists('members', 'id')->where('unit_id', $unit->id)],
        ]);
        $setup->tasks()->create([...$data, 'sort' => $setup->tasks()->max('sort') + 1]);

        return back()->with('status', 'Task added.');
    }

    public function assignTask(Request $request, Unit $unit, SetupTask $task): RedirectResponse
    {
        $data = $request->validate(['member_id' => ['nullable', Rule::exists('members', 'id')->where('unit_id', $unit->id)]]);
        $task->update($data);

        return back();
    }

    /**
     * Anyone on the team ticks tasks off (or back on).
     */
    public function toggleTask(Request $request, Unit $unit, SetupTask $task): RedirectResponse
    {
        $task->forceFill($task->done_at ? ['done_at' => null, 'done_by' => null] : ['done_at' => now(), 'done_by' => $request->user()->id])->save();

        return back();
    }

    /**
     * Show the stream on the Watch page, marked as live.
     */
    public function goLive(Request $request, Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::where('service_id', $service->id)->firstOrFail();
        if (! $setup->youtube_video_id) {
            return back()->withErrors(['youtube_video_id' => 'Add the YouTube video ID for this stream first.']);
        }

        ServiceSetup::whereNotNull('went_live_at')->whereNull('ended_at')->whereKeyNot($setup->id)->update(['ended_at' => now()]);
        $setup->forceFill(['went_live_at' => $setup->went_live_at ?? now(), 'ended_at' => null])->save();
        Setting::putMany(['church.live.video_id' => $setup->youtube_video_id, 'church.live.is_live' => '1']);

        return back()->with('status', 'Live: the Watch page is showing the stream.');
    }

    public function endLive(Unit $unit, Service $service): RedirectResponse
    {
        $setup = ServiceSetup::where('service_id', $service->id)->firstOrFail();
        $setup->forceFill(['ended_at' => now()])->save();
        // Keep the video on the Watch page as the replay; just drop the "live" badge.
        Setting::putMany(['church.live.is_live' => '0']);

        return back()->with('status', 'Stream ended. The Watch page now shows it as a replay.');
    }

    /**
     * The checklist new service setups start from.
     */
    public function checklist(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validate(['checklist' => ['nullable', 'string', 'max:5000']]));

        return back()->with('status', 'Checklist saved. New service setups will start from it.');
    }

    public function storeDestination(Request $request, Unit $unit): RedirectResponse
    {
        StreamDestination::create($this->destination($request, true));

        return back()->with('status', 'Stream destination saved.');
    }

    public function updateDestination(Request $request, Unit $unit, StreamDestination $destination): RedirectResponse
    {
        $data = $this->destination($request, false);
        if (blank($data['stream_key'] ?? null)) {
            unset($data['stream_key']); // keep the saved key
        }
        $destination->update([...$data, 'is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Stream destination saved.');
    }

    public function destroyDestination(Unit $unit, StreamDestination $destination): RedirectResponse
    {
        $destination->delete();

        return back()->with('status', 'Stream destination removed.');
    }

    /**
     * Show a stream key in full, for copying into the encoder. Leaders only.
     */
    public function revealKey(Unit $unit, StreamDestination $destination): View
    {
        return view('unit.media.stream-key', ['unit' => $unit, 'destination' => $destination]);
    }

    /**
     * @return array<string, mixed>
     */
    private function destination(Request $request, bool $keyRequired): array
    {
        return $request->validate([
            'platform' => ['required', Rule::in(array_keys(StreamDestination::PLATFORMS))],
            'label' => ['required', 'string', 'max:100'],
            'server_url' => ['required', 'string', 'max:500', 'regex:#^rtmps?://#i'],
            'stream_key' => [$keyRequired ? 'required' : 'nullable', 'string', 'max:500'],
        ], ['server_url.regex' => 'Use the rtmp:// or rtmps:// server address from the platform\'s live settings.']);
    }
}
