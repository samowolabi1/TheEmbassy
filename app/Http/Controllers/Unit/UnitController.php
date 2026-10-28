<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Mail\UnitMessageMail;
use App\Models\Member;
use App\Models\Service;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * The unit leaders' area: overview, members, attendance and messages.
 */
class UnitController extends Controller
{
    public function show(Unit $unit): View
    {
        $members = $unit->members()->active()->get();

        return view('unit.show', [
            'unit' => $unit->load('hod', 'assistant'),
            'memberCount' => $members->count(),
            'attendance' => $this->sundayRate($unit),
            'meetings' => $unit->meetings()->where('starts_at', '>=', now()->subHours(2))->orderBy('starts_at')->take(3)->get(),
            'duties' => $unit->duties()->with('member', 'service')->whereHas('service', fn ($q) => $q->where('held_on', '>=', today()->startOfDay()))->get()
                ->sortBy(fn ($d) => $d->service->starts_at ?? $d->service->held_on)->take(8),
            'away' => $members->filter(fn (Member $m) => ! $m->last_attended_on || $m->last_attended_on->lt(today()->subWeeks(3)))->take(6),
            'birthdays' => $members->filter(fn (Member $m) => $m->birthday && $this->daysUntilBirthday($m) <= 14)->sortBy(fn ($m) => $this->daysUntilBirthday($m))->take(5),
        ]);
    }

    public function members(Unit $unit): View
    {
        return view('unit.members', [
            'unit' => $unit,
            'members' => $unit->members()->active()->with('fellowship')->orderBy('name')->get(),
            'others' => Member::active()->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', '!=', $unit->id))->orderBy('name')->get(['id', 'name', 'unit_id']),
        ]);
    }

    public function addMember(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate(['member_id' => ['required', Rule::exists('members', 'id')->where('status', Member::ACTIVE)]]);
        $member = Member::findOrFail($data['member_id']);
        $member->update(['unit_id' => $unit->id]);

        return back()->with('status', "{$member->name} added to {$unit->name}.");
    }

    public function removeMember(Unit $unit, Member $member): RedirectResponse
    {
        abort_unless($member->unit_id === $unit->id, 404);
        abort_if($unit->isLedBy($member), 422, 'Change the HOD or assistant in church admin first.');
        $member->update(['unit_id' => null]);

        return back()->with('status', "{$member->name} removed from {$unit->name}.");
    }

    /**
     * Each member's Sunday services and unit meetings over the last two months.
     */
    public function attendance(Unit $unit): View
    {
        $sundays = Service::where('kind', 'sunday')->where('held_on', '<=', today()->endOfDay())->whereHas('members')
            ->orderByDesc('held_on')->take(8)->get()->reverse()->values();
        $meetings = $unit->meetings()->where('starts_at', '<=', now())->orderByDesc('starts_at')->take(6)->get()->reverse()->values();
        $members = $unit->members()->active()->orderBy('name')->get();

        $atService = DB::table('member_service')->whereIn('service_id', $sundays->pluck('id'))->whereIn('member_id', $members->pluck('id'))
            ->get()->groupBy('member_id')->map(fn ($rows) => $rows->pluck('service_id')->all());
        $atMeeting = DB::table('member_unit_meeting')->whereIn('unit_meeting_id', $meetings->pluck('id'))->whereIn('member_id', $members->pluck('id'))
            ->get()->groupBy('member_id')->map(fn ($rows) => $rows->pluck('unit_meeting_id')->all());

        return view('unit.attendance', compact('unit', 'sundays', 'meetings', 'members', 'atService', 'atMeeting'));
    }

    public function messages(Unit $unit): View
    {
        return view('unit.messages', ['unit' => $unit, 'messages' => $unit->messages()->with('sender')->latest()->paginate(15)]);
    }

    /**
     * Email the whole unit, and show the message on their account pages.
     */
    public function sendMessage(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:5000']]);
        $message = $unit->messages()->make($data);
        $message->sent_by = $request->user()->id;
        $message->save();

        $sent = 0;
        foreach ($unit->members()->active()->with('user')->get() as $member) {
            $email = $member->email ?? $member->user?->email;
            if (! $email) {
                continue;
            }
            try {
                Mail::to($email)->send(new UnitMessageMail($message));
                $sent++;
            } catch (Throwable $e) {
                Log::error('Could not email unit message', ['message' => $message->id, 'member' => $member->id, 'error' => $e->getMessage()]);
            }
        }
        $message->forceFill(['emailed' => $sent])->save();

        return back()->with('status', "Message sent. Emailed to {$sent} ".str('member')->plural($sent).'; everyone sees it on their account page.');
    }

    /**
     * Of the last eight Sundays where attendance was taken, the share of members present.
     */
    private function sundayRate(Unit $unit): ?int
    {
        $ids = Service::where('kind', 'sunday')->where('held_on', '<=', today()->endOfDay())->whereHas('members')->orderByDesc('held_on')->take(8)->pluck('id');
        $memberIds = $unit->members()->active()->pluck('id');
        if ($ids->isEmpty() || $memberIds->isEmpty()) {
            return null;
        }
        $present = DB::table('member_service')->whereIn('service_id', $ids)->whereIn('member_id', $memberIds)->count();

        return (int) round($present * 100 / ($ids->count() * $memberIds->count()));
    }

    private function daysUntilBirthday(Member $member): int
    {
        $next = $member->birthday->copy()->year(today()->year);
        if ($next->lt(today())) {
            $next->addYear();
        }

        return (int) today()->diffInDays($next);
    }
}
