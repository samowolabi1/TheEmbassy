<?php

namespace App\Services\Care;

use App\Mail\ReminderMail;
use App\Models\FirstTimer;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Service;
use App\Services\Messaging\Reminders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
    /**
     * Call a first-timer back. Someone whose card matches an earlier one (same phone or email)
     * has come back a second time: that's a different call, inviting them to join.
     */
    public function forFirstTimer(FirstTimer $firstTimer): void
    {
        $due = $firstTimer->visited_on->copy()->addDays((int) config('church.care.first_timer_days'));
        $earlier = $this->earlierVisit($firstTimer);

        FollowUp::firstOrCreate(
            ['first_timer_id' => $firstTimer->id, 'reason' => $earlier ? 'second_visit' : 'first_timer'],
            ['due_on' => $due->lt(today()) ? today() : $due, 'note' => $earlier
                ? 'Came back! First visited '.$earlier->visited_on->format('j M Y').'. Invite them to join the family.'.($firstTimer->wants_to_join ? ' They\'d like to become a member.' : '')
                : ($firstTimer->wants_to_join ? 'Would like to become a member' : null)],
        );
    }

    private function earlierVisit(FirstTimer $firstTimer): ?FirstTimer
    {
        $phone = Member::internationalPhone($firstTimer->phone);
        $email = $firstTimer->email ? mb_strtolower(trim($firstTimer->email)) : null;
        if (! $phone && ! $email) {
            return null;
        }

        return FirstTimer::whereKeyNot($firstTimer->id)->where('visited_on', '<=', $firstTimer->visited_on)->orderBy('visited_on')->get()
            ->first(fn (FirstTimer $f) => ($phone && Member::internationalPhone($f->phone) === $phone) || ($email && mb_strtolower(trim((string) $f->email)) === $email));
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

        [$absent] = $this->absentFor($threshold);
        $absent->each(function (Member $member) use (&$result, $threshold) {
            $seen = $member->last_attended_on?->toDateString();
            $already = FollowUp::where('member_id', $member->id)->where('reason', 'absent')
                ->where(fn ($q) => $q->whereNull('done_at')->orWhere(fn ($q) => $seen ? $q->whereDate('reference_on', $seen) : $q->whereNull('reference_on')))
                ->exists();
            if ($already) {
                return;
            }
            $followUp = FollowUp::create([
                'member_id' => $member->id,
                'reason' => 'absent',
                'reference_on' => $seen,
                'due_on' => today(),
                'note' => "Missed the last {$threshold} Sunday services".($seen ? ', last seen '.$member->last_attended_on->format('j M') : ''),
            ]);
            $this->tellUnitHeads($followUp);
            $result['raised']++;
        });

        return $result;
    }

    /**
     * Active members who've missed the last $count Sundays where attendance was taken (and
     * weren't seen at any service since), counting only Sundays after they joined.
     *
     * @return array{0: Collection<int, Member>, 1: ?Carbon}
     */
    public function absentFor(int $count): array
    {
        $sundays = Service::where('kind', 'sunday')->where('held_on', '<=', today()->endOfDay())
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('member_service')->whereColumn('member_service.service_id', 'services.id'))
            ->orderByDesc('held_on')->take($count)->get(['id', 'held_on']);
        if ($sundays->count() < $count) {
            return [collect(), null];
        }

        $oldest = $sundays->last()->held_on;
        $present = DB::table('member_service')->whereIn('service_id', $sundays->pluck('id'))->pluck('member_id')->unique()->flip();

        $members = Member::active()->with('user')
            ->where(fn ($q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $oldest))
            ->where(fn ($q) => $q->whereNull('last_attended_on')->orWhere('last_attended_on', '<', $oldest))
            ->get()
            ->reject(fn (Member $m) => $present->has($m->id))
            ->values();

        return [$members, $oldest];
    }

    /**
     * "We missed you" to members who've missed the set number of Sundays, once per absence
     * (they get it again only after they've been back). Set on Reminders & greetings.
     */
    public function missedMessages(): int
    {
        $s = Reminders::settings('missed');
        if (! $s['enabled']) {
            return 0;
        }
        [$absent] = $this->absentFor(max(1, $s['timing']));
        $due = $absent->filter(fn (Member $m) => ! $m->missed_message_at || ($m->last_attended_on && $m->missed_message_at->lt($m->last_attended_on)))->values();
        if ($due->isEmpty()) {
            return 0;
        }
        Member::whereKey($due->modelKeys())->update(['missed_message_at' => now()]);

        return app(Reminders::class)->deliver($due, 'missed', $s, fn (Member $m) => [
            'first_name' => $m->firstName(), 'church' => config('church.short_name'), 'weeks' => (string) max(1, $s['timing']),
        ], 'We missed you at church', route('account'));
    }

    /**
     * Let the heads of the member's units know someone in their unit hasn't been seen.
     */
    private function tellUnitHeads(FollowUp $followUp): void
    {
        $member = $followUp->member;
        $heads = $member->units()->get()->flatMap(fn ($u) => [$u->hod, $u->assistant])->filter()->unique('id')
            ->reject(fn (Member $h) => $h->is($member));
        foreach ($heads as $head) {
            if ($email = $head->user?->email ?? $head->email) {
                app(Reminders::class)->mail($email, new ReminderMail(
                    "{$member->name} hasn't been in church lately",
                    'Hi '.$head->firstName().", {$member->name} from your unit has missed the last few Sunday services. A call from you would mean a lot: you'll find it under Follow-up in your unit area, where you can note how it went.",
                    route('unit.follow-ups', $member->units->first(fn ($u) => $u->isLedBy($head)) ?? $member->unit_id),
                ));
            }
        }
    }
}
