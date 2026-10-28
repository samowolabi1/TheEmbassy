<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Service;
use App\Models\Unit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Headcounts for each service, and which members were there.
 */
class AttendanceController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.index', [
            'upcoming' => Service::whereNotNull('starts_at')->where('starts_at', '>=', now()->subHours(6))->orderBy('starts_at')->take(6)->get(),
            'services' => Service::withCount('members')->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<', now()->subHours(6)))->orderByDesc('held_on')->paginate(15),
            'sundays' => Service::where('kind', 'sunday')->orderByDesc('held_on')->take(8)->get()->reverse()->values(),
            'kinds' => Service::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $repeat = (int) ($request->validate(['repeat' => ['nullable', 'integer', 'min:0', 'max:12']])['repeat'] ?? 0);
        $service = Service::create($data);

        // "Repeat weekly" adds the same service for the following weeks, skipping any already there.
        $added = 1;
        for ($week = 1; $week <= $repeat; $week++) {
            $next = collect($data)->merge([
                'held_on' => $service->held_on->copy()->addWeeks($week),
                'starts_at' => $service->starts_at?->copy()->addWeeks($week),
                'ends_at' => $service->ends_at?->copy()->addWeeks($week),
                'in_person' => 0,
                'online' => 0,
            ])->all();
            if (! Service::whereDate('held_on', $next['held_on'])->where('kind', $next['kind'])->exists()) {
                Service::create($next);
                $added++;
            }
        }

        return redirect()->route('admin.attendance.show', $service)
            ->with('status', $added > 1 ? "{$added} services scheduled." : 'Service saved.');
    }

    public function show(Request $request, Service $service): View
    {
        $present = $service->members()->pluck('members.id')->all();

        return view('admin.attendance.show', [
            'service' => $service,
            'checkedIn' => $service->members()->wherePivotIn('mode', ['in_person', 'online'])->orderByPivot('checked_in_at')->get(),
            'kinds' => Service::KINDS,
            'present' => $present,
            'members' => Member::active()->search($request->query('q'))
                ->when($request->filled('unit'), fn ($q) => $q->where('unit_id', $request->integer('unit')))
                ->orderBy('name')->get(['id', 'name', 'phone', 'unit_id']),
            'units' => Unit::orderBy('name')->get(),
            'filters' => $request->only(['q', 'unit']),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request, $service));

        return back()->with('status', 'Numbers saved.');
    }

    /**
     * Save who was present among the members shown on the page (others are left as they were).
     */
    public function mark(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'shown' => ['array'],
            'shown.*' => ['integer'],
            'present' => ['array'],
            'present.*' => ['integer', Rule::in($request->input('shown', []))],
        ]);
        $shown = Member::active()->whereKey($data['shown'] ?? [])->pluck('id');
        $present = $shown->intersect($data['present'] ?? []);

        DB::transaction(function () use ($service, $shown, $present) {
            $service->members()->detach($shown->diff($present)->all());
            $service->members()->syncWithoutDetaching($present->all());

            // Everyone shown gets their "last attended" recalculated from what's recorded.
            foreach ($shown as $id) {
                $last = DB::table('member_service')->join('services', 'services.id', '=', 'member_service.service_id')
                    ->where('member_id', $id)->max('services.held_on');
                Member::whereKey($id)->update(['last_attended_on' => $last ? substr($last, 0, 10) : null]);
            }
        });

        return back()->with('status', $present->count().' marked present.');
    }

    /**
     * The code members enter, for a screen in church or an overlay on the live stream. It changes
     * every minute; the page refreshes itself.
     */
    public function screen(Service $service): View
    {
        $code = $service->code();
        $url = route('check-in', ['code' => $code]);

        return view('admin.attendance.screen', [
            'service' => $service,
            'code' => $code,
            'qr' => (new Writer(new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd)))->writeString($url),
            'secondsLeft' => 60 - now()->second,
        ]);
    }

    public function destroy(Service $service): RedirectResponse
    {
        $ids = $service->members()->pluck('members.id');
        $service->delete();
        foreach ($ids as $id) {
            $last = DB::table('member_service')->join('services', 'services.id', '=', 'member_service.service_id')->where('member_id', $id)->max('services.held_on');
            Member::whereKey($id)->update(['last_attended_on' => $last ? substr($last, 0, 10) : null]);
        }

        return redirect()->route('admin.attendance.index')->with('status', 'Service removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'held_on' => ['required', 'date', 'before:+1 year'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'kind' => ['required', Rule::in(array_keys(Service::KINDS))],
            'title' => ['nullable', 'string', 'max:120'],
            'in_person' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'online' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['end_time.after' => 'The end time must be after the start.']);

        $day = Carbon::parse($data['held_on'])->toDateString();
        $data['starts_at'] = ($data['start_time'] ?? null) ? Carbon::parse($day.' '.$data['start_time']) : null;
        $data['ends_at'] = ($data['end_time'] ?? null) ? Carbon::parse($day.' '.$data['end_time']) : null;
        $data['in_person'] = (int) ($data['in_person'] ?? 0);
        $data['online'] = (int) ($data['online'] ?? 0);
        $data['in_person_checkin'] = $request->boolean('in_person_checkin');
        $data['online_checkin'] = $request->boolean('online_checkin');
        if (($data['in_person_checkin'] || $data['online_checkin']) && ! $data['starts_at']) {
            throw ValidationException::withMessages(['start_time' => 'Set a start time so members know when check-in opens.']);
        }
        unset($data['start_time'], $data['end_time']);

        $taken = Service::whereDate('held_on', $data['held_on'])->where('kind', $data['kind'])->when($service, fn ($q) => $q->whereKeyNot($service->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['held_on' => 'That service is already recorded. Open it to change the numbers.']);
        }

        return $data;
    }
}
