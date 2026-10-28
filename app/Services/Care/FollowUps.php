<?php

namespace App\Services\Care;

use App\Models\FirstTimer;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Raising follow-ups so nobody slips through: first-timers are called within a couple of days,
 * new members are welcomed, and members who've missed several Sundays in a row are checked on.
 *
 * Only Sundays where attendance was actually taken count as missed, so a week with no records
 * (or a member who simply didn't check in) doesn't send anyone a "we missed you" call by mistake.
 */
class FollowUps
{
    public function forFirstTimer(FirstTimer $firstTimer): void
    {
        $due = $firstTimer->visited_on->copy()->addDays((int) config('church.care.first_timer_days'));

        FollowUp::firstOrCreate(
            ['first_timer_id' => $firstTimer->id, 'reason' => 'first_timer'],
            ['due_on' => $due->lt(today()) ? today() : $due, 'note' => $firstTimer->wants_to_join ? 'Would like to become a member' : null],
        );
    }

    public function forNewMember(Member $member): void
    {
        FollowUp::firstOrCreate(
            ['member_id' => $member->id, 'reason' => 'new_member'],
            ['due_on' => today()->addDays((int) config('church.care.new_member_days')), 'note' => 'Welcome them and help them find a unit and fellowship'],
        );
    }

    /**
     * Raise "not seen lately" follow-ups, and close the ones for people who've come back.
     *
     * @return array{raised: int, closed: int}
     */
    public function scanAbsences(): array
    {
        $threshold = max(1, (int) config('church.care.absent_after'));
        $result = ['raised' => 0, 'closed' => 0];

        // Close open absence follow-ups for members seen since they were raised.
        FollowUp::open()->where('reason', 'absent')->with('member')->get()->each(function (FollowUp $f) use (&$result) {
            if ($f->member && $f->member->last_attended_on && (! $f->reference_on || $f->member->last_attended_on->gt($f->reference_on))) {
                $f->forceFill(['done_at' => now(), 'outcome' => 'returned', 'outcome_note' => 'Back on '.$f->member->last_attended_on->format('j M')])->save();
                $result['closed']++;
            }
        });

        // The most recent Sundays where attendance was taken (someone was marked or checked in).
        $sundays = Service::where('kind', 'sunday')->where('held_on', '<=', today()->endOfDay())
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('member_service')->whereColumn('member_service.service_id', 'services.id'))
            ->orderByDesc('held_on')->take($threshold)->get(['id', 'held_on']);
        if ($sundays->count() < $threshold) {
            return $result;
        }

        $oldest = $sundays->last()->held_on;
        $present = DB::table('member_service')->whereIn('service_id', $sundays->pluck('id'))->pluck('member_id')->unique()->flip();

        Member::active()
            ->where(fn ($q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $oldest))
            // Seen at any service since (e.g. midweek) counts as seen.
            ->where(fn ($q) => $q->whereNull('last_attended_on')->orWhere('last_attended_on', '<', $oldest))
            ->get()
            ->reject(fn (Member $m) => $present->has($m->id))
            ->each(function (Member $member) use (&$result, $threshold) {
                $seen = $member->last_attended_on?->toDateString();
                $already = FollowUp::where('member_id', $member->id)->where('reason', 'absent')
                    ->where(fn ($q) => $q->whereNull('done_at')->orWhere(fn ($q) => $seen ? $q->whereDate('reference_on', $seen) : $q->whereNull('reference_on')))
                    ->exists();
                if ($already) {
                    return;
                }
                FollowUp::create([
                    'member_id' => $member->id,
                    'reason' => 'absent',
                    'reference_on' => $seen,
                    'due_on' => today(),
                    'note' => "Missed the last {$threshold} Sundays".($seen ? ', last seen '.$member->last_attended_on->format('j M') : ''),
                ]);
                $result['raised']++;
            });

        return $result;
    }
}
