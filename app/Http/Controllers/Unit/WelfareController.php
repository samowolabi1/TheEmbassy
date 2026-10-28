<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Unit;
use App\Models\WelfareCase;
use App\Models\WelfarePayment;
use App\Services\Welfare\WelfareDesk;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The welfare unit's case desk, for its HOD and assistant, and pastors.
 */
class WelfareController extends Controller
{
    private const TABS = [
        'open' => 'Open',
        'new' => 'New',
        'awaiting_pastor' => 'Waiting for a pastor',
        'check' => 'Due a check-in',
        'closed' => 'Closed',
    ];

    public function __construct(private WelfareDesk $desk) {}

    public function index(Request $request, Unit $unit): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $monthSpent = (int) WelfarePayment::where('paid_on', '>=', now()->startOfMonth()->startOfDay())->sum('amount');

        return view('unit.welfare.index', [
            'unit' => $unit,
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => $key === 'closed' ? null : $this->filter($key)->count()]),
            'cases' => $this->filter($tab)->with('member', 'worker')->orderByRaw("case when urgency = 'urgent' then 0 else 1 end")->latest()->paginate(20)->withQueryString(),
            'stats' => [
                'open' => WelfareCase::open()->count(),
                'urgent' => WelfareCase::open()->where('urgency', 'urgent')->count(),
                'spent' => $monthSpent,
                'budget' => config('church.welfare.monthly_budget') ? (int) config('church.welfare.monthly_budget') * 100 : null,
                'year' => (int) WelfarePayment::where('paid_on', '>=', now()->startOfYear()->startOfDay())->sum('amount'),
            ],
            'byCategory' => WelfareCase::where('created_at', '>=', now()->startOfYear())->selectRaw('category, count(*) as cases')->groupBy('category')->pluck('cases', 'category'),
            'limit' => (int) config('church.welfare.approval_limit'),
        ]);
    }

    public function create(Unit $unit): View
    {
        return view('unit.welfare.create', ['unit' => $unit, 'members' => Member::active()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['nullable', 'required_without:beneficiary_name', Rule::exists('members', 'id')],
            'beneficiary_name' => ['nullable', 'required_without:member_id', 'string', 'max:100'],
            'beneficiary_phone' => ['nullable', 'string', 'max:40'],
            ...self::requestRules(),
        ], ['member_id.required_without' => 'Choose a member, or enter the name of someone outside the church.']);

        $case = new WelfareCase([...$data, 'amount_requested' => isset($data['amount_requested']) ? (int) round($data['amount_requested'] * 100) : null]);
        $this->desk->open($case, $request->user(), $request->user()->isStaff() ? 'staff' : 'leader');

        return redirect()->route('unit.welfare.show', [$unit, $case])->with('status', "Case {$case->reference} opened.");
    }

    public function show(Unit $unit, WelfareCase $case): View
    {
        return view('unit.welfare.show', [
            'unit' => $unit,
            'case' => $case->load('member.unit', 'member.fellowship', 'worker', 'decider', 'notes.author', 'payments.recorder'),
            'workers' => $unit->members()->active()->orderBy('name')->get(['id', 'name']),
            'isPastor' => request()->user()->canManage('welfare'),
            'limit' => (int) config('church.welfare.approval_limit'),
            'paid' => $case->paid(),
            'history' => $case->member ? WelfareCase::where('member_id', $case->member_id)->whereKeyNot($case->id)->latest()->get() : collect(),
        ]);
    }

    public function update(Request $request, Unit $unit, WelfareCase $case): RedirectResponse
    {
        $data = $request->validate([
            'assigned_member_id' => ['nullable', Rule::exists('members', 'id')->where('unit_id', $unit->id)],
            'next_check_on' => ['nullable', 'date'],
            'urgency' => ['required', Rule::in(['normal', 'urgent'])],
        ], ['assigned_member_id.exists' => 'Case workers come from the welfare unit.']);

        $case->update($data);
        if ($case->wasChanged('assigned_member_id') && $case->worker) {
            $this->desk->log($case, $request->user(), 'status', 'Assigned to '.$case->worker->name.'.');
        }

        return back()->with('status', 'Saved.');
    }

    public function act(Request $request, Unit $unit, WelfareCase $case): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['review', 'approve', 'decline', 'close', 'reopen'])]])['action'];
        $user = $request->user();

        match ($action) {
            'review' => $this->desk->review($case, $user),
            'approve' => $this->desk->approve($case, $user, (int) round(((float) $request->validate([
                'amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
                'note' => ['nullable', 'string', 'max:255'],
            ])['amount'] ?? 0) * 100), $request->input('note'), $user->canManage('welfare')),
            'decline' => $this->desk->decline($case, $user, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']),
            'close' => $this->desk->close($case, $user, $request->input('note')),
            'reopen' => $this->desk->reopen($case, $user),
        };

        return back()->with('status', match ($case->fresh()->status) {
            'awaiting_pastor' => 'Above your limit, so it\'s gone to a pastor to approve.',
            default => 'Case updated: '.$case->fresh()->statusLabel().'.',
        });
    }

    public function pay(Request $request, Unit $unit, WelfareCase $case): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::in(array_keys(config('welfare.payment_methods')))],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $this->desk->pay($case, $request->user(), (int) round($data['amount'] * 100), $data['method'], $data['paid_on'], $data['reference'] ?? null);

        return back()->with('status', 'Payment recorded and added to the church\'s expenses.');
    }

    public function note(Request $request, Unit $unit, WelfareCase $case): RedirectResponse
    {
        $data = $request->validate(self::noteRules());
        $this->desk->log($case, $request->user(), $data['kind'], $data['body'], $data['happened_on']);
        if (! empty($data['next_check_on'])) {
            $case->update(['next_check_on' => $data['next_check_on']]);
        }

        return back()->with('status', 'Added to the case log.');
    }

    /**
     * Birthdays and wedding anniversaries in the next 30 days, for the welfare team to celebrate.
     */
    public function celebrations(Unit $unit): View
    {
        $soon = fn (?Carbon $date) => $date ? $this->daysUntil($date) : null;
        $members = Member::active()->where(fn ($q) => $q->whereNotNull('birthday')->orWhereNotNull('anniversary'))->with('unit')->get();

        $events = collect();
        foreach ($members as $m) {
            foreach (['birthday' => 'Birthday', 'anniversary' => 'Wedding anniversary'] as $field => $label) {
                if (($days = $soon($m->{$field})) !== null && $days <= 30) {
                    $events->push(['member' => $m, 'label' => $label, 'days' => $days, 'date' => today()->addDays($days), 'years' => $field === 'anniversary' ? today()->addDays($days)->year - $m->anniversary->year : null]);
                }
            }
        }

        return view('unit.welfare.celebrations', ['unit' => $unit, 'events' => $events->sortBy('days')->values()]);
    }

    public function export(Unit $unit): StreamedResponse
    {
        return Csv::download('welfare-'.now()->format('Y-m-d').'.csv',
            ['reference', 'opened', 'category', 'urgency', 'status', 'requested_naira', 'approved_naira', 'paid_naira', 'closed'],
            function ($write) {
                // No names or details: the export is for reporting.
                WelfareCase::withSum('payments', 'amount')->orderBy('created_at')->each(fn (WelfareCase $c) => $write([
                    $c->reference, $c->created_at->toDateString(), $c->categoryLabel(), $c->urgency, $c->statusLabel(),
                    $c->amount_requested ? $c->amount_requested / 100 : '', $c->amount_approved ? $c->amount_approved / 100 : '', ($c->payments_sum_amount ?? 0) / 100, $c->closed_at?->toDateString(),
                ]));
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestRules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(config('welfare.categories')))],
            'summary' => ['required', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:5000'],
            'urgency' => ['required', Rule::in(['normal', 'urgent'])],
            'amount_requested' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function noteRules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(config('welfare.note_kinds')))],
            'body' => ['required', 'string', 'max:5000'],
            'happened_on' => ['required', 'date', 'before_or_equal:today'],
            'next_check_on' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    private function filter(string $tab)
    {
        return match ($tab) {
            'new' => WelfareCase::where('status', 'new'),
            'awaiting_pastor' => WelfareCase::where('status', 'awaiting_pastor'),
            'check' => WelfareCase::open()->whereNotNull('next_check_on')->where('next_check_on', '<=', today()->endOfDay()),
            'closed' => WelfareCase::whereIn('status', ['closed', 'declined']),
            default => WelfareCase::open(),
        };
    }

    private function daysUntil(Carbon $date): int
    {
        $next = $date->copy()->setYear(today()->year);
        if ($next->lt(today())) {
            $next->addYear();
        }

        return (int) today()->diffInDays($next);
    }
}
