<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\BookLoan;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Expense;
use App\Models\FirstTimer;
use App\Models\FollowUp;
use App\Models\Gift;
use App\Models\Income;
use App\Models\Member;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\Shop;
use App\Models\Suggestion;
use App\Models\Testimony;
use App\Models\TicketOrder;
use App\Models\TokenTransfer;
use App\Models\Unit;
use App\Models\UnitMeeting;
use App\Models\VendorSubscription;
use App\Models\Visit;
use App\Models\WelfareCase;
use App\Models\WelfarePackageApplication;
use App\Support\TokenName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reports for the church's administrators, each an Excel workbook: a summary sheet with the
 * key figures for the dates chosen, then the detail sheets behind them.
 */
class Reports
{
    /**
     * @return array<string, array{title: string, about: string, giving?: bool, dated?: bool}>
     */
    public static function all(): array
    {
        return [
            'members' => ['title' => 'Members', 'about' => 'The whole register: contact details, status, role, units, fellowship, training and last attendance, with totals by status, role, gender, unit and fellowship. New members in the dates chosen.'],
            'attendance' => ['title' => 'Attendance', 'about' => 'Each service in the dates chosen with headcounts and check-ins, and how often each member came.'],
            'care' => ['title' => 'Follow-up & visitation', 'about' => 'First-timer cards, follow-ups raised and how they ended, and pastoral visits with their reports.'],
            'units' => ['title' => 'Units', 'about' => 'Each unit with its heads, workers, ministers, training, meetings and attendance, and everyone in each unit.'],
            'giving' => ['title' => 'Giving', 'about' => 'Every gift in the dates chosen, by fund and method, with totals. For the pastor in charge only.', 'giving' => true],
            'finance' => ['title' => 'Income & expenses', 'about' => 'Income received and money spent, by category, with the balance for the dates chosen.'],
            'welfare' => ['title' => 'Welfare', 'about' => 'Welfare cases raised and amounts approved, and applications for welfare packages.'],
            'market' => ['title' => 'Tribe Market', 'about' => 'Shops, and seller subscriptions paid in the dates chosen.'],
            'events' => ['title' => 'Events & tickets', 'about' => 'Events in the dates chosen with tickets sold, money taken and check-ins, and every confirmed order.'],
            'library' => ['title' => 'Library', 'about' => 'Books lent and returned in the dates chosen, late returns, and the most borrowed books.'],
            'tokens' => ['title' => TokenName::title(), 'about' => 'Every '.TokenName::one().' movement in the dates chosen, by kind, with totals.'],
            'outreach' => ['title' => 'Message centre', 'about' => 'Messages sent to members, sellers and staff, with how many were reached each way.'],
            'feedback' => ['title' => 'Feedback, testimonies & appointments', 'about' => 'Service ratings, suggestions, testimonies and pastor appointments in the dates chosen.'],
        ];
    }

    /**
     * @return list<array<string, mixed>> the workbook's sheets
     */
    public function build(string $report, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $sheets = $this->{$report}($from, $to);
        $sheets[0]['title'] = [
            config('church.name').': '.self::all()[$report]['title'],
            $from->format('j M Y').' to '.$to->format('j M Y').' · made '.now()->format('j M Y, g:i A'),
            '',
        ];

        return $sheets;
    }

    // ---------------------------------------------------------------- Members

    private function members(Carbon $from, Carbon $to): array
    {
        $members = Member::with('units', 'fellowship', 'user')->orderBy('name')->get();
        $active = $members->where('status', Member::ACTIVE);

        return [
            ['name' => 'Summary', 'pairs' => [
                ['On the register', $members->count()],
                ['Members (approved)', $active->count()],
                ['Waiting for approval', $members->where('status', Member::PENDING)->count()],
                ['Inactive', $members->where('status', Member::INACTIVE)->count()],
                ['New members in these dates', $members->filter(fn ($m) => $m->joined_on && $m->joined_on->between($from, $to))->count()],
                ['With a login to the app', $active->whereNotNull('user_id')->count()],
                ['', null],
                ['Roles (approved members)', null],
                ...collect(Member::RANKS)->map(fn ($label, $key) => [$label.'s', $active->where('rank', $key)->count()])->values()->all(),
                ['', null],
                ['Gender (approved members)', null],
                ['Female', $active->where('gender', 'female')->count()],
                ['Male', $active->where('gender', 'male')->count()],
                ['Not given', $active->whereNull('gender')->count()],
                ['', null],
                ['Training (approved members)', null],
                ...collect(Member::MILESTONES)->map(fn ($label, $f) => [$label, $active->where($f, true)->count()])->values()->all(),
                ['', null],
                ['Units (approved members)', null],
                ...Unit::orderBy('name')->get()->map(fn ($u) => [$u->name, $active->filter(fn ($m) => $m->units->contains('id', $u->id))->count()])->all(),
                ['In no unit', $active->filter(fn ($m) => $m->units->isEmpty())->count()],
                ['', null],
                ['House fellowships (approved members)', null],
                ...$active->groupBy(fn ($m) => $m->fellowship?->name ?? 'None')->map->count()->sortKeys()->map(fn ($n, $name) => [$name, $n])->values()->all(),
            ]],
            ['name' => 'Members', 'columns' => [
                ['Name'], ['Phone'], ['Email'], ['Gender'], ['Birthday', 'date'], ['Address'], ['Status'], ['Role'], ['Units'], ['Fellowship'],
                ['Joined', 'date'], ['Last at church', 'date'], ...collect(Member::MILESTONES)->map(fn ($l) => [$l])->values()->all(), ['Has a login'],
            ], 'rows' => $members->map(fn (Member $m) => [
                $m->name, $m->phone, $m->email, ucfirst((string) $m->gender), $m->birthday?->toDateString(), $m->address, $m->statusLabel(), $m->rankLabel(),
                $m->unitNames(), $m->fellowship?->name, $m->joined_on?->toDateString(), $m->last_attended_on?->toDateString(),
                ...collect(Member::MILESTONES)->keys()->map(fn ($f) => $m->{$f} ? 'Yes' : 'No')->all(), $m->user_id ? 'Yes' : 'No',
            ])->all()],
        ];
    }

    // ---------------------------------------------------------------- Attendance

    private function attendance(Carbon $from, Carbon $to): array
    {
        $services = Service::whereBetween('held_on', [$from, $to])->withCount([
            'members as checked_in' => fn ($q) => $q->whereIn('member_service.mode', ['in_person', 'online']),
            'members as marked',
            'members as online_checked' => fn ($q) => $q->where('member_service.mode', 'online'),
        ])->orderBy('held_on')->get();
        $firstTimers = FirstTimer::whereBetween('visited_on', [$from, $to])->get()->groupBy(fn ($f) => $f->visited_on->toDateString());
        $ids = $services->pluck('id');
        $sundays = $services->where('kind', 'sunday')->filter(fn ($s) => $s->marked > 0);
        $attended = DB::table('member_service')->whereIn('service_id', $ids)->select('member_id', DB::raw('count(*) as n'))->groupBy('member_id')->pluck('n', 'member_id');
        $sundayAttended = DB::table('member_service')->whereIn('service_id', $sundays->pluck('id'))->select('member_id', DB::raw('count(*) as n'))->groupBy('member_id')->pluck('n', 'member_id');
        $members = Member::active()->with('units')->orderBy('name')->get();
        $headcounts = $services->map(fn ($s) => $s->in_person + $s->online);

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Services held', $services->count()],
                ['Sunday services with attendance taken', $sundays->count()],
                ['Average headcount per service', $services->count() ? (int) round($headcounts->avg()) : 0],
                ['Highest headcount', (int) $headcounts->max()],
                ['Members marked present (all services)', (int) $services->sum('marked')],
                ['Of which checked in online', (int) $services->sum('online_checked')],
                ['First-timers', FirstTimer::whereBetween('visited_on', [$from, $to])->count()],
                ['Members at one or more services', $attended->count()],
                ['Approved members at none', $members->reject(fn ($m) => $attended->has($m->id))->count()],
            ]],
            ['name' => 'Services', 'columns' => [['Date', 'date'], ['Service'], ['Kind'], ['Headcount in person', 'int'], ['Headcount online', 'int'], ['Members present', 'int'], ['Checked in themselves', 'int'], ['Online check-ins', 'int'], ['First-timers', 'int'], ['Notes']],
                'rows' => $services->map(fn (Service $s) => [$s->held_on->toDateString(), $s->label(), Service::KINDS[$s->kind] ?? ucfirst($s->kind), $s->in_person, $s->online, $s->marked, $s->checked_in, $s->online_checked, $firstTimers->get($s->held_on->toDateString())?->count() ?? 0, $s->notes])->all()],
            ['name' => 'Members', 'columns' => [['Name'], ['Phone'], ['Units'], ['Services attended', 'int'], ['Sundays attended', 'int'], ['Sundays held', 'int'], ['Sunday attendance', 'percent'], ['Last at church', 'date']],
                'rows' => $members->map(fn (Member $m) => [$m->name, $m->phone, $m->unitNames(), (int) ($attended[$m->id] ?? 0), (int) ($sundayAttended[$m->id] ?? 0), $sundays->count(),
                    $sundays->count() ? ($sundayAttended[$m->id] ?? 0) / $sundays->count() : null, $m->last_attended_on?->toDateString()])->all()],
        ];
    }

    // ---------------------------------------------------------------- Follow-up & visitation

    private function care(Carbon $from, Carbon $to): array
    {
        $cards = FirstTimer::with('follower')->whereBetween('visited_on', [$from, $to])->orderBy('visited_on')->get();
        $followUps = FollowUp::with('member', 'firstTimer', 'assignee', 'doer')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $visits = Visit::with('visitors', 'doer')->whereBetween('scheduled_at', [$from, $to])->orderBy('scheduled_at')->get();
        $done = $followUps->whereNotNull('done_at');

        return [
            ['name' => 'Summary', 'pairs' => [
                ['First-timer cards', $cards->count()],
                ['Would like to become members', $cards->where('wants_to_join', true)->count()],
                ['First-timers called', $cards->whereNotNull('followed_up_at')->count()],
                ['', null],
                ['Follow-ups raised', $followUps->count()],
                ...collect(FollowUp::REASONS)->map(fn ($l, $k) => ['   '.$l, $followUps->where('reason', $k)->count()])->values()->all(),
                ['Done', $done->count()],
                ['Still open', $followUps->whereNull('done_at')->count()],
                ['Open and overdue', $followUps->whereNull('done_at')->filter(fn ($f) => $f->isOverdue())->count()],
                ['Average days to complete', $done->count() ? round($done->avg(fn ($f) => $f->created_at->diffInDays($f->done_at)), 1) : 0, 'text'],
                ['', null],
                ['How follow-ups ended', null],
                ...collect(FollowUp::OUTCOMES)->map(fn ($l, $k) => [$l, $done->where('outcome', $k)->count()])->values()->all(),
                ['', null],
                ['Visits planned', $visits->count()],
                ['Visits made', $visits->where('status', 'done')->count()],
                ['Visits cancelled', $visits->where('status', 'cancelled')->count()],
                ['Visits still to make', $visits->where('status', 'planned')->count()],
            ]],
            ['name' => 'First-timers', 'columns' => [['Visited', 'date'], ['Name'], ['Phone'], ['Email'], ['Invited by'], ['Wants to join'], ['Called', 'date'], ['Called by'], ['Note']],
                'rows' => $cards->map(fn ($f) => [$f->visited_on->toDateString(), $f->name, $f->phone, $f->email, $f->invited_by, $f->wants_to_join ? 'Yes' : 'No', $f->followed_up_at?->toDateString(), $f->follower?->name, $f->follow_up_note])->all()],
            ['name' => 'Follow-ups', 'columns' => [['Raised', 'date'], ['Person'], ['Phone'], ['Why'], ['Note'], ['Due', 'date'], ['Assigned to'], ['Status'], ['Done', 'date'], ['Done by'], ['Outcome'], ['Outcome note'], ['Call log']],
                'rows' => $followUps->map(fn (FollowUp $f) => [$f->created_at->toDateString(), $f->personName(), $f->phone(), $f->reasonLabel(), $f->note, $f->due_on?->toDateString(), $f->assignee?->name,
                    $f->done_at ? 'Done' : ($f->isOverdue() ? 'Overdue' : 'Open'), $f->done_at?->toDateString(), $f->doer?->name ?? ($f->done_at ? 'Automatically' : null),
                    FollowUp::OUTCOMES[$f->outcome] ?? null, $f->outcome_note, implode("\n", $f->logLines())])->all()],
            ['name' => 'Visits', 'columns' => [['Date', 'date'], ['Time'], ['Person'], ['Why'], ['Address'], ['Going'], ['Status'], ['Outcome'], ['Report'], ['Recorded by']],
                'rows' => $visits->map(fn (Visit $v) => [$v->scheduled_at->toDateString(), $v->scheduled_at->format('g:i A'), $v->name, $v->purposeLabel(), $v->address, $v->visitors->pluck('name')->join(', '),
                    ucfirst($v->status), Visit::OUTCOMES[$v->outcome] ?? null, $v->report, $v->doer?->name])->all()],
        ];
    }

    // ---------------------------------------------------------------- Units

    private function units(Carbon $from, Carbon $to): array
    {
        $units = Unit::with('hod', 'assistant', 'members')->orderBy('name')->get();
        $meetings = UnitMeeting::whereBetween('starts_at', [$from, $to])->withCount('attendees')->get()->groupBy('unit_id');
        $openFollowUps = FollowUp::open()->whereNotNull('member_id')->pluck('member_id')->flip();
        $rows = $units->map(function (Unit $u) use ($meetings, $openFollowUps) {
            $active = $u->members->where('status', Member::ACTIVE);
            $m = $meetings->get($u->id, collect());

            return [$u->name, Unit::KINDS[$u->kind] ?? $u->kind, $u->hod?->name, $u->assistant?->name, $active->count(), $active->where('rank', 'worker')->count(), $active->where('rank', 'minister')->count(),
                $active->filter(fn ($x) => in_array($x->rank, ['worker', 'minister'], true) && ! $x->missingRequirements())->count(),
                $active->filter(fn ($x) => $x->needsTrainingAnswers())->count(), $active->filter(fn ($x) => $openFollowUps->has($x->id))->count(),
                $m->count(), $m->count() ? (int) round($m->avg('attendees_count')) : 0];
        });

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Units', $units->count()],
                ['Units with a head (HOD)', $units->whereNotNull('hod_member_id')->count()],
                ['Workers', Member::active()->where('rank', 'worker')->count()],
                ['Ministers', Member::active()->where('rank', 'minister')->count()],
                ['Serving in more than one unit', Member::active()->has('units', '>', 1)->count()],
                ['Unit meetings in these dates', $meetings->flatten()->count()],
                ['Waiting to join a unit', DB::table('unit_requests')->where('status', 'pending')->count()],
            ]],
            ['name' => 'Units', 'columns' => [['Unit'], ['Type'], ['Head (HOD)'], ['Assistant'], ['Members', 'int'], ['Workers', 'int'], ['Ministers', 'int'], ['Training complete', 'int'], ['Training not answered', 'int'], ['Open follow-ups', 'int'], ['Meetings', 'int'], ['Average at meetings', 'int']],
                'rows' => $rows->all()],
            ['name' => 'Unit members', 'columns' => [['Unit'], ['Name'], ['Phone'], ['Role'], ['Leads it'], ...collect(Member::MILESTONES)->map(fn ($l) => [$l])->values()->all(), ['Last at church', 'date']],
                'rows' => $units->flatMap(fn (Unit $u) => $u->members->where('status', Member::ACTIVE)->sortBy('name')->map(fn (Member $m) => [
                    $u->name, $m->name, $m->phone, $m->rankLabel(), $u->hod_member_id === $m->id ? 'HOD' : ($u->assistant_member_id === $m->id ? 'Assistant' : ''),
                    ...collect(Member::MILESTONES)->keys()->map(fn ($f) => $m->{$f} ? 'Yes' : 'No')->all(), $m->last_attended_on?->toDateString(),
                ]))->values()->all()],
        ];
    }

    // ---------------------------------------------------------------- Giving

    private function giving(Carbon $from, Carbon $to): array
    {
        $gifts = Gift::with('fund', 'member')->where('status', Gift::PAID)->whereBetween('given_on', [$from, $to])->orderBy('given_on')->get();
        $naira = fn ($kobo) => $kobo / 100;

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Total given', $naira($gifts->sum('amount')), 'money'],
                ['Gifts', $gifts->count()],
                ['Givers (on the register)', $gifts->whereNotNull('member_id')->unique('member_id')->count()],
                ['', null],
                ['By fund', null],
                ...$gifts->groupBy(fn ($g) => $g->fund?->name ?? 'General')->map(fn ($g, $name) => [$name, $naira($g->sum('amount')), 'money'])->sortKeys()->values()->all(),
                ['', null],
                ['By method', null],
                ...$gifts->groupBy('method')->map(fn ($g, $m) => [Gift::METHODS[$m] ?? ucfirst((string) $m), $naira($g->sum('amount')), 'money'])->values()->all(),
                ['', null],
                ['By month', null],
                ...$gifts->groupBy(fn ($g) => $g->given_on->format('Y-m'))->map(fn ($g, $m) => [Carbon::parse($m.'-01')->format('F Y'), $naira($g->sum('amount')), 'money'])->values()->all(),
            ]],
            ['name' => 'Gifts', 'columns' => [['Date', 'date'], ['Code'], ['Fund'], ['Name'], ['Member'], ['Email'], ['Method'], ['Amount', 'money'], ['Notes']],
                'rows' => $gifts->map(fn (Gift $g) => [$g->given_on->toDateString(), $g->code, $g->fund?->name, $g->name, $g->member?->name, $g->email, Gift::METHODS[$g->method] ?? $g->method, $naira($g->amount), $g->notes])->all()],
        ];
    }

    // ---------------------------------------------------------------- Income & expenses

    private function finance(Carbon $from, Carbon $to): array
    {
        $income = Income::with('recorder')->whereBetween('received_on', [$from, $to])->orderBy('received_on')->get();
        $expenses = Expense::with('requester')->whereBetween('spent_on', [$from, $to])->orderBy('spent_on')->get();
        $in = $income->where('status', Income::CONFIRMED);
        $out = $expenses->where('status', Expense::APPROVED);
        $naira = fn ($kobo) => $kobo / 100;

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Income (confirmed)', $naira($in->sum('amount')), 'money'],
                ['Expenses (approved)', $naira($out->sum('amount')), 'money'],
                ['Balance', $naira($in->sum('amount') - $out->sum('amount')), 'money'],
                ['Income waiting to be confirmed', $naira($income->where('status', Income::PENDING)->sum('amount')), 'money'],
                ['Expenses waiting for approval', $naira($expenses->where('status', Expense::PENDING)->sum('amount')), 'money'],
                ['', null],
                ['Income by category', null],
                ...$in->groupBy('category')->map(fn ($g, $c) => [$c ?: 'Other', $naira($g->sum('amount')), 'money'])->sortKeys()->values()->all(),
                ['', null],
                ['Expenses by category', null],
                ...$out->groupBy('category')->map(fn ($g, $c) => [$c ?: 'Other', $naira($g->sum('amount')), 'money'])->sortKeys()->values()->all(),
            ]],
            ['name' => 'Income', 'columns' => [['Date', 'date'], ['Title'], ['Category'], ['Method'], ['Amount', 'money'], ['Status'], ['Recorded by'], ['Description']],
                'rows' => $income->map(fn (Income $i) => [$i->received_on->toDateString(), $i->title, $i->category, Income::METHODS[$i->method] ?? $i->method, $naira($i->amount), ucfirst($i->status), $i->recorder?->name, $i->description])->all()],
            ['name' => 'Expenses', 'columns' => [['Date', 'date'], ['Title'], ['Category'], ['Amount', 'money'], ['Status'], ['Requested by'], ['Description'], ['Why declined']],
                'rows' => $expenses->map(fn (Expense $e) => [$e->spent_on->toDateString(), $e->title, $e->category, $naira($e->amount), ucfirst($e->status), $e->requester?->name, $e->description, $e->decline_reason])->all()],
        ];
    }

    // ---------------------------------------------------------------- Welfare

    private function welfare(Carbon $from, Carbon $to): array
    {
        $cases = WelfareCase::with('member')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $apps = WelfarePackageApplication::with('package', 'member')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $naira = fn ($kobo) => $kobo === null ? null : $kobo / 100;
        $label = fn ($s) => ucfirst(str_replace('_', ' ', (string) $s));

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Cases raised', $cases->count()],
                ['Amount requested', $naira($cases->sum('amount_requested')), 'money'],
                ['Amount approved', $naira($cases->sum('amount_approved')), 'money'],
                ['Still open', $cases->whereIn('status', WelfareCase::OPEN)->count()],
                ['', null],
                ['Cases by category', null],
                ...$cases->groupBy('category')->map(fn ($g, $c) => [$label($c), $g->count()])->values()->all(),
                ['', null],
                ['Package applications', $apps->count()],
                ...$apps->groupBy('status')->map(fn ($g, $s) => ['   '.(WelfarePackageApplication::STATUSES[$s] ?? $label($s)), $g->count()])->values()->all(),
            ]],
            ['name' => 'Cases', 'columns' => [['Raised', 'date'], ['Reference'], ['For'], ['Phone'], ['Category'], ['Urgency'], ['Summary'], ['Requested', 'money'], ['Approved', 'money'], ['Status'], ['Decided', 'date'], ['Closed', 'date']],
                'rows' => $cases->map(fn (WelfareCase $c) => [$c->created_at->toDateString(), $c->reference, $c->beneficiary_name ?? $c->member?->name, $c->beneficiary_phone ?? $c->member?->phone, $label($c->category), $label($c->urgency), $c->summary,
                    $naira($c->amount_requested), $naira($c->amount_approved), $label($c->status), $c->decided_at?->toDateString(), $c->closed_at?->toDateString()])->all()],
            ['name' => 'Package applications', 'columns' => [['Applied', 'date'], ['Package'], ['Member'], ['Phone'], ['Household', 'int'], ['Status'], ['Collected', 'date']],
                'rows' => $apps->map(fn ($a) => [$a->created_at->toDateString(), $a->package?->title, $a->member?->name, $a->member?->phone, $a->household_size, WelfarePackageApplication::STATUSES[$a->status] ?? $label($a->status), $a->collected_at?->toDateString()])->all()],
        ];
    }

    // ---------------------------------------------------------------- Market

    private function market(Carbon $from, Carbon $to): array
    {
        $shops = Shop::with('user')->orderBy('name')->get();
        $subs = VendorSubscription::with('shop', 'plan')->whereIn('status', [VendorSubscription::ACTIVE, 'expired', 'ended'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $naira = fn ($kobo) => $kobo / 100;

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Shops on the market (approved)', $shops->where('status', Shop::APPROVED)->count()],
                ['Waiting for approval', $shops->where('status', Shop::PENDING)->count()],
                ['Subscriptions in these dates', $subs->count()],
                ['Paid in money', $naira($subs->where('tokens_paid', null)->sum('amount')), 'money'],
                ['Paid in '.TokenName::many(), (int) $subs->sum('tokens_paid')],
                ['Discounts given', $naira($subs->sum('discount')), 'money'],
            ]],
            ['name' => 'Shops', 'columns' => [['Shop'], ['Owner'], ['Category'], ['Phone'], ['Email'], ['Status'], ['Approved', 'date'], ['Subscribed until', 'date'], ['Visits from shares', 'int']],
                'rows' => $shops->map(fn (Shop $s) => [$s->name, $s->owner_name ?? $s->user?->name, $s->category, $s->phone, $s->email, ucfirst($s->status), $s->approved_at?->toDateString(), $s->subscribedUntil()?->toDateString(), (int) ($s->share_visits ?? 0)])->all()],
            ['name' => 'Subscriptions', 'columns' => [['Paid', 'date'], ['Shop'], ['Plan'], ['Months', 'int'], ['List price', 'money'], ['Discount', 'money'], ['Paid', 'money'], [TokenName::many().' paid', 'int'], ['From', 'date'], ['Until', 'date']],
                'rows' => $subs->map(fn (VendorSubscription $v) => [$v->created_at->toDateString(), $v->shop?->name, $v->plan?->name, $v->months, $naira($v->list_price), $naira($v->discount),
                    $v->tokens_paid ? 0 : $naira($v->amount), $v->tokens_paid, $v->starts_at?->toDateString(), $v->ends_at?->toDateString()])->all()],
        ];
    }

    // ---------------------------------------------------------------- Events

    private function events(Carbon $from, Carbon $to): array
    {
        $events = Event::with('ticketTypes')->whereBetween('starts_at', [$from, $to])->orderBy('starts_at')->get();
        $orders = TicketOrder::with('event', 'tickets')->where('status', TicketOrder::CONFIRMED)->whereIn('event_id', $events->pluck('id'))->orderBy('confirmed_at')->get();
        $byEvent = $orders->groupBy('event_id');
        $naira = fn ($kobo) => $kobo / 100;

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Events', $events->count()],
                ['Tickets', $orders->sum(fn ($o) => $o->tickets->count())],
                ['Checked in at the door', $orders->sum(fn ($o) => $o->tickets->whereNotNull('checked_in_at')->count())],
                ['Money taken', $naira($orders->sum('total')), 'money'],
            ]],
            ['name' => 'Events', 'columns' => [['Date', 'date'], ['Event'], ['Venue'], ['Orders', 'int'], ['Tickets', 'int'], ['Checked in', 'int'], ['Money taken', 'money']],
                'rows' => $events->map(function (Event $e) use ($byEvent, $naira) {
                    $o = $byEvent->get($e->id, collect());

                    return [$e->starts_at->toDateString(), $e->title, $e->venue, $o->count(), $o->sum(fn ($x) => $x->tickets->count()), $o->sum(fn ($x) => $x->tickets->whereNotNull('checked_in_at')->count()), $naira($o->sum('total'))];
                })->all()],
            ['name' => 'Orders', 'columns' => [['Confirmed', 'date'], ['Event'], ['Code'], ['Name'], ['Email'], ['Phone'], ['Tickets', 'int'], ['Checked in', 'int'], ['Total', 'money']],
                'rows' => $orders->map(fn (TicketOrder $o) => [$o->confirmed_at?->toDateString(), $o->event?->title, $o->code, $o->name, $o->email, $o->phone, $o->tickets->count(), $o->tickets->whereNotNull('checked_in_at')->count(), $naira($o->total)])->all()],
        ];
    }

    // ---------------------------------------------------------------- Library

    private function library(Carbon $from, Carbon $to): array
    {
        $loans = BookLoan::with('book', 'member')->where(fn ($q) => $q->whereBetween('lent_at', [$from, $to])->orWhereBetween('returned_at', [$from, $to]))->orderBy('lent_at')->get();
        $late = fn (BookLoan $l) => $l->due_on && (($l->returned_at && $l->returned_at->gt($l->due_on->copy()->endOfDay())) || (! $l->returned_at && $l->status === BookLoan::ON_LOAN && $l->due_on->lt(today())));

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Books lent', $loans->filter(fn ($l) => $l->lent_at && $l->lent_at->between($from, $to))->count()],
                ['Books returned', $loans->filter(fn ($l) => $l->returned_at && $l->returned_at->between($from, $to))->count()],
                ['Late (returned late or still overdue)', $loans->filter($late)->count()],
                ['On loan now', BookLoan::where('status', BookLoan::ON_LOAN)->count()],
                ['Overdue now', BookLoan::where('status', BookLoan::ON_LOAN)->where('due_on', '<', today())->count()],
                ['Borrowers', $loans->unique('member_id')->count()],
                ['', null],
                ['Most borrowed', null],
                ...$loans->whereNotNull('lent_at')->groupBy('book_id')->map(fn ($g) => [$g->first()->book?->title, $g->count()])->sortByDesc(1)->take(10)->values()->all(),
            ]],
            ['name' => 'Loans', 'columns' => [['Lent', 'date'], ['Book'], ['Author'], ['Member'], ['Phone'], ['Due', 'date'], ['Returned', 'date'], ['Status'], ['Late'], ['Renewals', 'int']],
                'rows' => $loans->map(fn (BookLoan $l) => [$l->lent_at?->toDateString(), $l->book?->title, $l->book?->author, $l->member?->name, $l->member?->phone, $l->due_on?->toDateString(), $l->returned_at?->toDateString(),
                    BookLoan::LABELS[$l->status] ?? $l->status, $late($l) ? 'Yes' : 'No', $l->renewals])->all()],
        ];
    }

    // ---------------------------------------------------------------- Tokens

    private function tokens(Carbon $from, Carbon $to): array
    {
        $transfers = TokenTransfer::with('from.user', 'to.user')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $holder = fn ($account) => $account ? ($account->user?->name ?? ucfirst(str_replace('_', ' ', $account->kind))) : null;

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Movements', $transfers->count()],
                [TokenName::many().' moved', (int) $transfers->sum('amount')],
                ['', null],
                ['By kind', null],
                ...$transfers->groupBy('kind')->map(fn ($g, $k) => [TokenTransfer::KINDS[$k] ?? ucfirst(str_replace('_', ' ', $k)), (int) $g->sum('amount')])->values()->all(),
            ]],
            ['name' => 'Movements', 'columns' => [['Date', 'date'], ['Time'], ['From'], ['To'], ['Kind'], [TokenName::many(), 'int'], ['Description']],
                'rows' => $transfers->map(fn (TokenTransfer $t) => [$t->created_at->toDateString(), $t->created_at->format('g:i A'), $holder($t->from), $holder($t->to), TokenTransfer::KINDS[$t->kind] ?? $t->kind, $t->amount, $t->description])->all()],
        ];
    }

    // ---------------------------------------------------------------- Message centre

    private function outreach(Carbon $from, Carbon $to): array
    {
        $campaigns = Campaign::with('creator')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Messages', $campaigns->count()],
                ['Sent', $campaigns->where('status', 'sent')->count()],
                ['People reached', (int) $campaigns->sum('recipients_count')],
                ['Emails sent', (int) $campaigns->sum('email_sent')],
                ['WhatsApp messages sent', (int) $campaigns->sum('whatsapp_sent')],
                ['App notifications sent', (int) $campaigns->sum('push_sent')],
                ['Couldn\'t be reached', (int) $campaigns->sum('failed')],
            ]],
            ['name' => 'Messages', 'columns' => [['Created', 'date'], ['Subject'], ['Status'], ['Ways'], ['Sent', 'date'], ['People', 'int'], ['Email', 'int'], ['WhatsApp', 'int'], ['App', 'int'], ['Not reached', 'int'], ['By']],
                'rows' => $campaigns->map(fn (Campaign $c) => [$c->created_at->toDateString(), $c->subject, Campaign::STATUSES[$c->status] ?? $c->status, implode(', ', (array) $c->channels), $c->finished_at?->toDateString(),
                    $c->recipients_count, $c->email_sent, $c->whatsapp_sent, $c->push_sent, $c->failed, $c->creator?->name])->all()],
        ];
    }

    // ---------------------------------------------------------------- Feedback

    private function feedback(Carbon $from, Carbon $to): array
    {
        $ratings = ServiceFeedback::with('service', 'member')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $suggestions = Suggestion::with('member')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $testimonies = Testimony::with('member')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();
        $appointments = Appointment::with('member', 'pastor')->whereBetween('starts_at', [$from, $to])->orderBy('starts_at')->get();

        return [
            ['name' => 'Summary', 'pairs' => [
                ['Service ratings', $ratings->count()],
                ['Average rating (out of 5)', $ratings->count() ? round($ratings->avg('rating'), 2) : 0, 'text'],
                ['Suggestions', $suggestions->count()],
                ['Testimonies shared', $testimonies->count()],
                ['Testimonies published', $testimonies->where('status', 'published')->count()],
                ['Appointments with pastors', $appointments->count()],
                ...collect(Appointment::STATUSES)->map(fn ($l, $k) => ['   '.$l, $appointments->where('status', $k)->count()])->values()->all(),
            ]],
            ['name' => 'Service ratings', 'columns' => [['Date', 'date'], ['Service'], ['Rating', 'int'], ['Went well'], ['Could improve'], ['Comment'], ['From']],
                'rows' => $ratings->map(fn ($r) => [$r->created_at->toDateString(), $r->service?->label(), $r->rating,
                    collect($r->went_well)->map(fn ($a) => ServiceFeedback::ASPECTS[$a] ?? $a)->join(', '), collect($r->could_improve)->map(fn ($a) => ServiceFeedback::ASPECTS[$a] ?? $a)->join(', '), $r->comment, $r->anonymous ? 'Anonymous' : $r->member?->name])->all()],
            ['name' => 'Suggestions', 'columns' => [['Date', 'date'], ['Category'], ['Subject'], ['Suggestion'], ['From'], ['Status'], ['Answer']],
                'rows' => $suggestions->map(fn ($s) => [$s->created_at->toDateString(), Suggestion::CATEGORIES[$s->category] ?? $s->category, $s->subject, $s->body, $s->anonymous ? 'Anonymous' : $s->member?->name, Suggestion::STATUSES[$s->status] ?? $s->status, $s->response])->all()],
            ['name' => 'Testimonies', 'columns' => [['Date', 'date'], ['Title'], ['Category'], ['From'], ['Status'], ['Published', 'date']],
                'rows' => $testimonies->map(fn ($t) => [$t->created_at->toDateString(), $t->title, Testimony::CATEGORIES[$t->category] ?? $t->category, $t->member?->name, Testimony::STATUSES[$t->status] ?? $t->status, $t->published_at?->toDateString()])->all()],
            ['name' => 'Appointments', 'columns' => [['Date', 'date'], ['Time'], ['Member'], ['Pastor'], ['Topic'], ['How'], ['Status']],
                'rows' => $appointments->map(fn ($a) => [$a->starts_at->toDateString(), $a->starts_at->format('g:i A'), $a->member?->name, $a->pastor?->name, Appointment::TOPICS[$a->topic] ?? $a->topic, Appointment::MODES[$a->mode] ?? $a->mode, Appointment::STATUSES[$a->status] ?? $a->status])->all()],
        ];
    }
}
