<?php

namespace App\Services\Care;

use App\Mail\CareListMail;
use App\Mail\ReminderMail;
use App\Models\FirstTimer;
use App\Models\FollowUp;
use App\Models\ReminderLog;
use App\Models\User;
use App\Models\Visit;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WhatsApp;
use Illuminate\Support\Collection;

/**
 * Emails that keep follow-up and visitation moving: each morning, staff hear about their overdue
 * follow-ups and the visits they're making; every Monday, the pastors get a summary of the week.
 */
class CareNotices
{
    public function __construct(private Reminders $reminders) {}

    /**
     * Each staff member with overdue follow-ups assigned to them gets one email listing them.
     */
    public function overdueDigest(): int
    {
        if (! config('church.care.overdue_digest')) {
            return 0;
        }
        $sent = 0;
        FollowUp::open()->where('due_on', '<', today())->whereNotNull('assigned_to')->with('assignee', 'member', 'firstTimer')->orderBy('due_on')->get()
            ->groupBy('assigned_to')
            ->each(function (Collection $overdue) use (&$sent) {
                $user = $overdue->first()->assignee;
                if (! $user?->email) {
                    return;
                }
                $n = $overdue->count();
                $this->reminders->mail($user->email, new CareListMail(
                    $n === 1 ? '1 follow-up is overdue' : "{$n} follow-ups are overdue",
                    'Hi '.explode(' ', $user->name)[0].', these are waiting for a call. When you\'ve reached someone, note how it went so the team knows.',
                    $overdue->take(15)->map(fn (FollowUp $f) => [$f->personName().' · '.$f->reasonLabel(), 'Was due '.$f->due_on->format('D j M').($f->phone() ? ' · '.$f->phone() : '')])->values()->all(),
                    route('admin.care.index', ['tab' => 'mine']), 'Open my follow-ups',
                ));
                $sent++;
            });

        return $sent;
    }

    /**
     * The week in follow-up and visitation, to the pastors.
     */
    public function weeklySummary(): int
    {
        if (! config('church.care.weekly_summary')) {
            return 0;
        }
        $since = now()->subDays(7);
        $done = FollowUp::where('done_at', '>=', $since)->get();
        $figures = [
            'raised' => FollowUp::where('created_at', '>=', $since)->count(),
            'done' => $done->count(),
            'reached' => $done->whereIn('outcome', ['reached', 'visited', 'prayed', 'returned'])->count(),
            'open' => FollowUp::open()->count(),
            'overdue' => FollowUp::open()->where('due_on', '<', today())->count(),
            'unassigned' => FollowUp::open()->whereNull('assigned_to')->count(),
            'visits_done' => Visit::where('status', 'done')->where('done_at', '>=', $since)->count(),
            'visits_next' => Visit::planned()->whereBetween('scheduled_at', [now(), now()->addDays(7)])->count(),
            'first_timers' => FirstTimer::where('visited_on', '>=', $since->copy()->startOfDay())->count(),
            'missed' => ReminderLog::where('kind', 'missed')->where('created_at', '>=', $since)->count(),
        ];
        $overdue = FollowUp::open()->where('due_on', '<', today())->with('member', 'firstTimer', 'assignee')->orderBy('due_on')->take(10)->get()
            ->map(fn (FollowUp $f) => [$f->personName().' · '.$f->reasonLabel(), 'Due '.$f->due_on->format('j M').' · '.($f->assignee?->name ?? 'not assigned')])->all();

        $intro = "This week: {$figures['raised']} new follow-ups, {$figures['done']} done ({$figures['reached']} reached), "
            ."{$figures['visits_done']} visits made and {$figures['visits_next']} planned for the week ahead. "
            ."{$figures['first_timers']} first-timers, and {$figures['missed']} \"we missed you\" messages went out."
            .($overdue ? ' These are overdue:' : ' Nothing is overdue. Well done, team!');

        $sent = 0;
        foreach (User::whereIn('role', ['super', 'pastor'])->whereNotNull('email')->get() as $pastor) {
            $this->reminders->mail($pastor->email, new CareListMail('Follow-up this week', $intro, $overdue, route('admin.care.index', ['tab' => 'open']), 'Open follow-up', [
                'Open' => $figures['open'], 'Overdue' => $figures['overdue'], 'Not assigned' => $figures['unassigned'], 'Visits ahead' => $figures['visits_next'],
            ]));
            $sent++;
        }

        return $sent;
    }

    /**
     * The day before a visit (or on the day, for one planned late): remind whoever's going and,
     * if asked, let the person know.
     */
    public function visitReminders(): int
    {
        $sent = 0;
        Visit::planned()->whereNull('reminded_at')->whereBetween('scheduled_at', [now(), now()->addDay()->endOfDay()])->with('visitors', 'member.user')->get()
            ->each(function (Visit $visit) use (&$sent) {
                $visit->forceFill(['reminded_at' => now()])->save();
                $this->tellVisitors($visit, $visit->visitors, 'Reminder: visit to '.$visit->name);
                if ($visit->tell_them) {
                    $this->tellPerson($visit);
                }
                $sent++;
            });

        return $sent;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public function tellVisitors(Visit $visit, Collection $users, ?string $headline = null): void
    {
        $others = fn (User $u) => $visit->visitors->reject(fn ($v) => $v->is($u))->pluck('name')->join(', ');
        foreach ($users as $user) {
            if (! $user->email) {
                continue;
            }
            $this->reminders->mail($user->email, new CareListMail(
                $headline ?? 'You\'re visiting '.$visit->name,
                'Hi '.explode(' ', $user->name)[0].', you\'re down to visit '.$visit->name.' on '.$visit->scheduled_at->format('l j F \a\t g:i A').'.'
                    .(($with = $others($user)) ? " Going with you: {$with}." : '').' Afterwards, write a short report on the visit so the pastors know how it went.',
                array_values(array_filter([
                    ['Why', $visit->purposeLabel()],
                    $visit->address ? ['Where', $visit->address] : null,
                    $visit->phone ? ['Phone', $visit->phone] : null,
                    $visit->notes ? ['Notes', $visit->notes] : null,
                ])),
                route('admin.visits.index'), 'Open visitation',
            ));
        }
    }

    /**
     * Let the person know when to expect the visit.
     */
    public function tellPerson(Visit $visit): void
    {
        $first = explode(' ', $visit->member?->firstName() ?? $visit->name)[0];
        $who = $visit->visitors->pluck('name')->join(', ', ' and ') ?: 'Someone from the church';
        $title = 'We\'re coming to see you';
        $body = "Hi {$first}, {$who} will visit you on ".$visit->scheduled_at->format('l j F \a\t g:i A').'. If that time doesn\'t suit you, just reply or call the church office. We look forward to seeing you!';
        if ($visit->member) {
            if ($email = $visit->member->user?->email ?? $visit->member->email) {
                $this->reminders->mail($email, new ReminderMail($title, $body));
            }
            app(WhatsApp::class)->toMember($visit->member, $title, $body);
        }
    }
}
