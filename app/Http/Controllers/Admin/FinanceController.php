<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TokenReminder;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\Gift;
use App\Models\Member;
use App\Models\TokenPayment;
use App\Models\TokenPlan;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Giving into the church's funds, cash counted at services, and members' monthly tokens.
 */
class FinanceController extends Controller
{
    private const PERIODS = ['month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'];

    public function index(Request $request): View
    {
        Fund::ensureDefaults();
        TokenPlan::ensureDefaults();
        [$period, $from, $to] = $this->period($request);
        $days = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $method = in_array($request->query('method'), ['online', 'cash'], true) ? $request->query('method') : 'all';

        $gifts = Gift::paid()->whereBetween('given_on', $days);
        $tokens = TokenPayment::where('status', TokenPayment::PAID)->whereBetween('paid_at', [$from, $to->copy()->endOfDay()]); // a timestamp
        $giving = (int) (clone $gifts)->sum('amount');
        $tokenTotal = (int) (clone $tokens)->sum('amount');
        $online = (int) (clone $gifts)->where('method', 'online')->sum('amount') + (int) (clone $tokens)->where('method', 'online')->sum('amount');

        $arrears = $this->arrears();

        return view('admin.finance', [
            'period' => $period,
            'periods' => self::PERIODS,
            'label' => $from->isSameMonth($to) ? $from->format('F Y') : $from->format('j M').' – '.$to->format('j M Y'),
            'method' => $method,
            'stats' => [
                'total' => $giving + $tokenTotal,
                'giving' => $giving,
                'tokens' => $tokenTotal,
                'online' => $online,
                'offline' => $giving + $tokenTotal - $online,
                'services' => (clone $gifts)->where('method', '!=', 'online')->distinct()->count('given_on'),
                'expenses' => (int) Expense::where('status', Expense::APPROVED)->whereBetween('spent_on', $days)->sum('amount'),
                'pendingExpenses' => Expense::where('status', Expense::PENDING)->count(),
            ],
            'funds' => Fund::orderBy('sort')->orderBy('id')->get()->each(function (Fund $fund) use ($days) {
                $fund->period_total = (int) $fund->gifts()->paid()->whereBetween('given_on', $days)->sum('amount');
                $fund->balance = $fund->raised();
            }),
            'transactions' => Gift::with('fund')->paid()->whereBetween('given_on', $days)
                ->when($method === 'online', fn ($q) => $q->where('method', 'online'))
                ->when($method === 'cash', fn ($q) => $q->where('method', '!=', 'online'))
                ->latest('given_on')->latest('id')->paginate(15, ['*'], 'tx')->withQueryString(),
            'plans' => TokenPlan::withCount(['members' => fn ($q) => $q->active()])->orderByDesc('amount')->get(),
            'arrears' => $arrears->take(10),
            'arrearsCount' => $arrears->count(),
            'arrearsTotal' => $arrears->sum(fn (Member $m) => $m->tokenAmountOwed()),
            'members' => Member::active()->whereHas('tokenPlan', fn ($q) => $q->where('amount', '>', 0))->orderBy('name')->get(['id', 'name', 'phone']),
            'methods' => collect(Gift::METHODS)->except('online'),
        ]);
    }

    /**
     * Record an offering counted at a service, or a gift paid by transfer or cheque.
     */
    public function storeGift(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('gift', [
            'fund_id' => ['required', Rule::exists('funds', 'id')],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'cheque'])],
            'given_on' => ['required', 'date', 'before_or_equal:today'],
            'name' => ['nullable', 'string', 'max:100'],
            'counted_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $gift = new Gift([...$data, 'name' => ($data['name'] ?? null) ?: 'Sunday offering', 'amount' => (int) round($data['amount'] * 100)]);
        $gift->status = Gift::PAID;
        $gift->recorded_by = $request->user()->id;
        $gift->save();

        return back()->with('status', '₦'.number_format($gift->amount / 100).' recorded to '.$gift->fund->name.'.');
    }

    public function destroyGift(Gift $gift): RedirectResponse
    {
        abort_if($gift->method === 'online', 422, 'Online gifts come from Paystack and can\'t be deleted here.');
        $gift->delete();

        return back()->with('status', 'Entry '.$gift->code.' deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        [, $from, $to] = $this->period($request);
        $query = Gift::with('fund')->paid()->whereBetween('given_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('given_on');

        return Csv::download('giving-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv',
            ['date', 'reference', 'giver', 'email', 'fund', 'method', 'amount_naira', 'counted_by', 'notes'],
            function ($write) use ($query) {
                $query->each(fn (Gift $g) => $write([
                    $g->given_on->toDateString(), $g->code, $g->name, $g->email, $g->fund->name, $g->methodLabel(), number_format($g->amount / 100, 2, '.', ''), $g->counted_by, $g->notes,
                ]));
            });
    }

    public function storeFund(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('fund', [
            'name' => ['required', 'string', 'max:60', Rule::unique('funds')],
            'description' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'numeric', 'min:1', 'max:100000000000'],
        ]);
        Fund::create([...$data, 'target' => isset($data['target']) ? (int) round($data['target'] * 100) : null, 'sort' => Fund::max('sort') + 1]);

        return back()->with('status', "{$data['name']} added.");
    }

    public function updateFund(Request $request, Fund $fund): RedirectResponse
    {
        $data = $request->validateWithBag('fund', [
            'name' => ['required', 'string', 'max:60', Rule::unique('funds')->ignore($fund->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'numeric', 'min:1', 'max:100000000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $fund->update([...$data, 'target' => isset($data['target']) ? (int) round($data['target'] * 100) : null, 'is_active' => $request->boolean('is_active')]);

        return back()->with('status', "{$fund->name} saved.");
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $this->planData($request);
        $plan = TokenPlan::create($data);
        $this->onlyOneDefault($plan);

        return back()->with('status', "{$plan->name} plan added.");
    }

    public function updatePlan(Request $request, TokenPlan $plan): RedirectResponse
    {
        $plan->update($this->planData($request, $plan));
        $this->onlyOneDefault($plan);

        return back()->with('status', "{$plan->name} plan saved.");
    }

    /**
     * Record tokens paid at the office.
     */
    public function storeTokenPayment(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('tokens', [
            'member_id' => ['required', Rule::exists('members', 'id')->where('status', Member::ACTIVE)],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'cheque'])],
        ]);

        $member = Member::with('tokenPlan', 'tokenPayments')->findOrFail($data['member_id']);
        if (! $member->tokenPlan || $member->tokenPlan->isExempt()) {
            return back()->withErrors(['member_id' => "{$member->name} doesn't pay tokens on their plan."], 'tokens');
        }

        $payment = $member->tokenPayments()->create([
            'from_month' => $member->nextTokenMonth(),
            'months' => $data['months'],
            'amount' => $member->tokenPlan->amount * $data['months'],
            'method' => $data['method'],
        ]);
        $payment->forceFill(['recorded_by' => $request->user()->id])->markPaid();

        return back()->with('status', "Tokens for {$member->name} recorded: {$payment->periodLabel()}.");
    }

    /**
     * Email a reminder to one member, or to everyone behind who hasn't had one this week.
     */
    public function remind(Request $request): RedirectResponse
    {
        $members = $request->filled('member')
            ? $this->arrears()->where('id', $request->integer('member'))
            : $this->arrears()->filter(fn (Member $m) => ! $m->token_reminded_at || $m->token_reminded_at->lt(now()->subWeek()));

        $sent = 0;
        foreach ($members as $member) {
            $email = $member->email ?? $member->user?->email;
            if (! $email) {
                continue;
            }
            try {
                Mail::to($email)->send(new TokenReminder($member));
                $member->forceFill(['token_reminded_at' => now()])->save();
                $sent++;
            } catch (Throwable $e) {
                Log::error('Could not send token reminder', ['member' => $member->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('status', $sent ? "Sent {$sent} ".Str::plural('reminder', $sent).' by email.' : 'No reminders sent: nobody due a reminder has an email address.');
    }

    /**
     * Active members behind on tokens, most owed first.
     *
     * @return Collection<int, Member>
     */
    private function arrears(): Collection
    {
        return Member::active()->with('tokenPlan', 'tokenPayments', 'unit', 'user')
            ->whereHas('tokenPlan', fn ($q) => $q->where('amount', '>', 0))
            ->get()
            ->filter(fn (Member $m) => $m->tokenMonthsOwed() > 0)
            ->sortByDesc(fn (Member $m) => $m->tokenAmountOwed())
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function planData(Request $request, ?TokenPlan $plan = null): array
    {
        $data = $request->validateWithBag('plan', [
            'name' => ['required', 'string', 'max:60', Rule::unique('token_plans')->ignore($plan?->id)],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return [...$data, 'amount' => (int) round($data['amount'] * 100), 'is_default' => $request->boolean('is_default')];
    }

    private function onlyOneDefault(TokenPlan $plan): void
    {
        if ($plan->is_default) {
            TokenPlan::whereKeyNot($plan->id)->update(['is_default' => false]);
        }
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function period(Request $request): array
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';

        return match ($period) {
            'last_month' => [$period, now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$period, now()->startOfYear(), now()->endOfDay()],
            default => [$period, now()->startOfMonth(), now()->endOfDay()],
        };
    }
}
