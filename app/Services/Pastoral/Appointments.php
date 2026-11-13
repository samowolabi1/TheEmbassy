<?php

namespace App\Services\Pastoral;

use App\Mail\AppointmentUpdate;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\PastorHour;
use App\Models\PastorTimeOff;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\WebPush;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Booking visits with the pastors: the open times (from each pastor's weekly hours, less time off
 * and visits already booked), asking for one, and the pastor or office confirming, moving or
 * declining it. Each step is emailed, and the member's phone is told when it's confirmed.
 */
class Appointments
{
    /** How far ahead members can book. */
    public const DAYS_AHEAD = 21;

    /** How many visits a member can have waiting or coming up at once. */
    public const MAX_OPEN = 2;

    public function __construct(private WebPush $push) {}

    /**
     * Pastors members can book: the pastor in charge and pastors who have set visiting hours.
     *
     * @return Collection<int, User>
     */
    public function pastors(): Collection
    {
        return User::whereIn('id', PastorHour::distinct()->pluck('user_id'))->whereIn('role', ['super', 'pastor'])->with('member')->orderBy('name')->get();
    }

    /**
     * Open times for a pastor, by day: [date => [['start' => Carbon, 'modes' => [...], 'location' => ?string], …]].
     *
     * @return array<string, list<array{start: Carbon, end: Carbon, modes: list<string>, location: ?string}>>
     */
    public function openTimes(User $pastor, ?Carbon $from = null, int $days = self::DAYS_AHEAD): array
    {
        $from ??= now();
        $earliest = now()->addHours((int) config('church.appointments.notice_hours', 12));
        $hours = PastorHour::where('user_id', $pastor->id)->get()->groupBy('weekday');
        $off = PastorTimeOff::where('user_id', $pastor->id)->where('ends_on', '>=', $from->toDateString())->get();
        $taken = Appointment::where('pastor_id', $pastor->id)->whereIn('status', Appointment::ACTIVE)
            ->where('starts_at', '>=', $from->copy()->startOfDay())->get(['starts_at', 'ends_at']);

        $open = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->startOfDay()->addDays($i);
            if ($off->contains(fn (PastorTimeOff $t) => $date->between($t->starts_on->copy()->startOfDay(), $t->ends_on->copy()->endOfDay()))) {
                continue;
            }
            foreach ($hours->get($date->dayOfWeek, collect()) as $h) {
                $start = $date->copy()->setTimeFromTimeString($h->starts);
                $close = $date->copy()->setTimeFromTimeString($h->ends);
                for ($slot = $start; $slot->copy()->addMinutes($h->slot_minutes)->lte($close); $slot = $slot->copy()->addMinutes($h->slot_minutes)) {
                    $end = $slot->copy()->addMinutes($h->slot_minutes);
                    if ($slot->lt($earliest) || $taken->contains(fn ($a) => $slot->lt($a->ends_at) && $end->gt($a->starts_at))) {
                        continue;
                    }
                    $open[$date->toDateString()][] = ['start' => $slot, 'end' => $end, 'modes' => $h->modes(), 'location' => $h->location];
                }
            }
            if (isset($open[$date->toDateString()])) {
                usort($open[$date->toDateString()], fn ($a, $b) => $a['start'] <=> $b['start']);
            }
        }

        return $open;
    }

    /**
     * Ask for a time. It must still be open (checked again inside the lock, so two people can't
     * take the same time).
     */
    public function book(Member $member, User $pastor, Carbon $start, string $mode, string $topic, ?string $note): Appointment
    {
        if (Appointment::where('member_id', $member->id)->whereIn('status', Appointment::ACTIVE)->where('starts_at', '>', now())->count() >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['start' => 'You already have '.self::MAX_OPEN.' visits booked. Cancel one to book another.']);
        }

        $appointment = DB::transaction(function () use ($member, $pastor, $start, $mode, $topic, $note) {
            $slot = collect($this->openTimes($pastor, $start->copy()->startOfDay(), 1)[$start->toDateString()] ?? [])
                ->first(fn ($s) => $s['start']->eq($start));
            if (! $slot) {
                throw ValidationException::withMessages(['start' => 'Sorry, that time has just been taken or isn\'t open. Please choose another.']);
            }
            if (! in_array($mode, $slot['modes'], true)) {
                throw ValidationException::withMessages(['mode' => 'At that time the pastor sees people '.mb_strtolower(PastorHour::MODES[$slot['modes'][0]]).' only.']);
            }

            return Appointment::create([
                'member_id' => $member->id, 'pastor_id' => $pastor->id, 'starts_at' => $start, 'ends_at' => $slot['end'],
                'mode' => $mode, 'topic' => $topic, 'note' => $note,
            ]);
        });

        $this->mail([$pastor->email], $appointment, 'New visit request: '.$member->name,
            "{$member->name} would like to see you on {$appointment->when()} (".mb_strtolower(Appointment::MODES[$mode]).') about '.mb_strtolower(Appointment::TOPICS[$topic]).'. Please confirm it, or suggest another time.', true);

        return $appointment;
    }

    public function confirm(Appointment $appointment, User $by, ?string $message, ?string $callLink, ?Carbon $newTime = null): void
    {
        if (! $appointment->isActive()) {
            throw ValidationException::withMessages(['status' => 'This visit is already '.mb_strtolower($appointment->statusLabel()).'.']);
        }
        $moved = $newTime && ! $newTime->eq($appointment->starts_at);
        if ($moved) {
            $clash = Appointment::where('pastor_id', $appointment->pastor_id)->whereIn('status', Appointment::ACTIVE)->whereKeyNot($appointment->id)
                ->where('starts_at', '<', $newTime->copy()->addMinutes($appointment->starts_at->diffInMinutes($appointment->ends_at)))
                ->where('ends_at', '>', $newTime)->exists();
            if ($clash || $newTime->isPast()) {
                throw ValidationException::withMessages(['starts_at' => $clash ? 'The pastor already has a visit then.' : 'Choose a time in the future.']);
            }
            $length = $appointment->starts_at->diffInMinutes($appointment->ends_at);
            $appointment->starts_at = $newTime;
            $appointment->ends_at = $newTime->copy()->addMinutes($length);
            $appointment->reminded_at = null;
        }
        $appointment->forceFill([
            'status' => 'confirmed', 'staff_note' => $message, 'call_link' => $callLink,
            'decided_by' => $by->id, 'decided_at' => now(),
        ])->save();

        $what = $moved ? 'Your visit has a new time: '.$appointment->when().'.' : 'Your visit on '.$appointment->when().' is confirmed.';
        $this->toMember($appointment, $moved ? 'New time for your visit' : 'Your visit is confirmed', $what.($message ? ' '.$message : ''));
    }

    public function decline(Appointment $appointment, User $by, string $reason): void
    {
        if (! $appointment->isActive()) {
            throw ValidationException::withMessages(['reason' => 'This visit is already '.mb_strtolower($appointment->statusLabel()).'.']);
        }
        $appointment->forceFill(['status' => 'declined', 'staff_note' => $reason, 'decided_by' => $by->id, 'decided_at' => now()])->save();
        $this->toMember($appointment, 'About your visit request', 'The pastor can\'t see you on '.$appointment->when().': '.$reason.' You can book another time from your account.');
    }

    /**
     * The member cancels a visit that hasn't happened.
     */
    public function cancel(Appointment $appointment): void
    {
        if (! $appointment->isUpcoming()) {
            throw ValidationException::withMessages(['cancel' => 'This visit can\'t be cancelled now.']);
        }
        $appointment->forceFill(['status' => 'cancelled'])->save();
        $this->mail([$appointment->pastor?->email], $appointment, 'Visit cancelled: '.$appointment->member->name,
            $appointment->member->name.' has cancelled their visit on '.$appointment->when().'. The time is open again.', true);
    }

    /**
     * After the time: it happened, or they didn't come.
     */
    public function finish(Appointment $appointment, bool $happened): void
    {
        if ($appointment->status !== 'confirmed' || $appointment->starts_at->isFuture()) {
            throw ValidationException::withMessages(['status' => 'Only a confirmed visit that has started can be marked.']);
        }
        $appointment->forceFill(['status' => $happened ? 'completed' : 'missed'])->save();
    }

    /**
     * The day before: remind members of confirmed visits, and send each pastor their day.
     */
    public function remind(): int
    {
        $tomorrow = Appointment::with('member.user', 'pastor')->where('status', 'confirmed')->whereNull('reminded_at')
            ->whereBetween('starts_at', [today()->addDay()->startOfDay(), today()->addDay()->endOfDay()])->orderBy('starts_at')->get();
        foreach ($tomorrow as $a) {
            $this->toMember($a, 'See you tomorrow', 'A reminder of your visit with '.$a->pastor?->communityName().' tomorrow, '.$a->starts_at->format('l j F \a\t g:i A').'.'.($a->staff_note ? ' '.$a->staff_note : ''));
            $a->forceFill(['reminded_at' => now()])->save();
        }
        foreach ($tomorrow->groupBy('pastor_id') as $visits) {
            $pastor = $visits->first()->pastor;
            $list = $visits->map(fn (Appointment $a) => $a->starts_at->format('g:i A').': '.$a->member->name.' ('.mb_strtolower(Appointment::MODES[$a->mode]).')')->join('; ');
            $this->mail([$pastor?->email], $visits->first(), 'Your visits tomorrow', 'Tomorrow you are seeing: '.$list.'.', true);
        }

        return $tomorrow->count();
    }

    private function toMember(Appointment $appointment, string $subject, string $message): void
    {
        $member = $appointment->member;
        $this->mail([$member->user?->email ?? $member->email], $appointment, $subject, $message, false);
        if ($member->user && $this->push->configured()) {
            $this->push->send(PushSubscription::where('user_id', $member->user_id)->get(), [
                'title' => $subject, 'body' => $message, 'url' => route('account.appointments'), 'tag' => 'appointment-'.$appointment->id,
            ]);
        }
    }

    /**
     * @param  array<int, ?string>  $emails
     */
    private function mail(array $emails, Appointment $appointment, string $subject, string $message, bool $forStaff): void
    {
        foreach (array_unique(array_filter($emails)) as $email) {
            try {
                Mail::to($email)->send(new AppointmentUpdate($appointment, $subject, $message, $forStaff));
            } catch (Throwable $e) {
                Log::error('Could not send appointment email', ['appointment' => $appointment->reference, 'error' => $e->getMessage()]);
            }
        }
    }
}
