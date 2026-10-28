<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Mail\MeetingScheduled;
use App\Models\Member;
use App\Models\Unit;
use App\Models\UnitMeeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Unit meetings: scheduling (members are emailed), attendance and minutes.
 */
class MeetingController extends Controller
{
    public function index(Unit $unit): View
    {
        return view('unit.meetings.index', [
            'unit' => $unit,
            'upcoming' => $unit->meetings()->withCount('attendees')->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at')->get(),
            'past' => $unit->meetings()->withCount('attendees')->where('starts_at', '<', now()->subHours(3))->latest('starts_at')->paginate(10),
        ]);
    }

    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $meeting = $unit->meetings()->make($this->validated($request));
        $meeting->created_by = $request->user()->id;
        $meeting->save();

        $sent = 0;
        if ($request->boolean('notify')) {
            foreach ($unit->members()->active()->with('user')->get() as $member) {
                if ($email = $member->email ?? $member->user?->email) {
                    try {
                        Mail::to($email)->send(new MeetingScheduled($meeting));
                        $sent++;
                    } catch (Throwable $e) {
                        Log::error('Could not email meeting notice', ['meeting' => $meeting->id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        return redirect()->route('unit.meetings.show', [$unit, $meeting])->with('status', 'Meeting scheduled.'.($sent ? " Emailed {$sent} ".str('member')->plural($sent).'.' : ''));
    }

    public function show(Unit $unit, UnitMeeting $meeting): View
    {
        $this->belongs($unit, $meeting);

        return view('unit.meetings.show', [
            'unit' => $unit,
            'meeting' => $meeting,
            'members' => $unit->members()->active()->orderBy('name')->get(['id', 'name']),
            'present' => $meeting->attendees()->pluck('members.id')->all(),
        ]);
    }

    public function update(Request $request, Unit $unit, UnitMeeting $meeting): RedirectResponse
    {
        $this->belongs($unit, $meeting);
        $meeting->update([...$this->validated($request), 'minutes' => $request->validate(['minutes' => ['nullable', 'string', 'max:20000']])['minutes'] ?? null]);

        return back()->with('status', 'Meeting saved.');
    }

    public function mark(Request $request, Unit $unit, UnitMeeting $meeting): RedirectResponse
    {
        $this->belongs($unit, $meeting);
        $ids = $unit->members()->active()->pluck('id');
        $present = $ids->intersect(array_map('intval', (array) $request->input('present', [])));

        // Keep how people checked in; only add or remove.
        $meeting->attendees()->detach($ids->diff($present)->all());
        $already = $meeting->attendees()->pluck('members.id');
        $meeting->attendees()->attach($present->diff($already)->mapWithKeys(fn ($id) => [$id => ['mode' => 'marked']])->all());

        return back()->with('status', $present->count().' marked present.');
    }

    /**
     * A full-screen code for members to check in with, at the meeting or on a video call.
     */
    public function screen(Unit $unit, UnitMeeting $meeting): View
    {
        $this->belongs($unit, $meeting);

        return view('unit.meetings.screen', ['unit' => $unit, 'meeting' => $meeting, 'code' => $meeting->code(), 'secondsLeft' => 60 - now()->second]);
    }

    public function destroy(Unit $unit, UnitMeeting $meeting): RedirectResponse
    {
        $this->belongs($unit, $meeting);
        $meeting->delete();

        return redirect()->route('unit.meetings.index', $unit)->with('status', 'Meeting removed.');
    }

    /**
     * A member checking themselves in with the code (from their account page).
     */
    public function checkIn(Request $request, UnitMeeting $meeting): RedirectResponse
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE && $member->unit_id === $meeting->unit_id, 403);

        if (! $meeting->checkInIsOpen()) {
            return back()->withErrors(['meeting' => 'Check-in for this meeting isn\'t open right now.']);
        }
        if (! $meeting->acceptsCode($request->input('code'))) {
            return back()->withErrors(['meeting' => 'That code doesn\'t match. Ask your HOD for the one showing now.']);
        }
        $meeting->attendees()->syncWithoutDetaching([$member->id => ['mode' => 'code', 'checked_in_at' => now()]]);

        return back()->with('status', 'You\'re checked in to '.$meeting->title.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_url' => ['nullable', 'url:https', 'max:500'],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ], ['online_url.url' => 'Use the https:// link to the call (Zoom, Google Meet, Teams…).']);
    }

    private function belongs(Unit $unit, UnitMeeting $meeting): void
    {
        abort_unless($meeting->unit_id === $unit->id, 404);
    }
}
