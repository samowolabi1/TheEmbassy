<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\Gift;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Giving into the church's funds, the bank accounts people give to, and cash counted at services.
 */
class FinanceController extends Controller
{
    private const PERIODS = ['month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'];

    public function index(Request $request): View
    {
        Fund::ensureDefaults();
        [$period, $from, $to] = $this->period($request);
        $days = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $method = in_array($request->query('method'), ['online', 'cash'], true) ? $request->query('method') : 'all';
        // Only the administrator sees giving; other finance staff see expenses, funds and bank accounts.
        $seeGiving = $request->user()->can('see-giving');

        $gifts = Gift::paid()->whereBetween('given_on', $days)->when(! $seeGiving, fn ($q) => $q->whereRaw('1 = 0'));
        $giving = (int) (clone $gifts)->sum('amount');
        $online = (int) (clone $gifts)->where('method', 'online')->sum('amount');

        return view('admin.finance', [
            'period' => $period,
            'periods' => self::PERIODS,
            'label' => $from->isSameMonth($to) ? $from->format('F Y') : $from->format('j M').' – '.$to->format('j M Y'),
            'method' => $method,
            'seeGiving' => $seeGiving,
            'bankAccounts' => BankAccount::orderByRaw("case currency when 'NGN' then 0 else 1 end")->orderBy('currency')->orderBy('sort')->get(),
            'stats' => [
                'total' => $giving,
                'giving' => $giving,
                'online' => $online,
                'offline' => $giving - $online,
                'services' => (clone $gifts)->where('method', '!=', 'online')->distinct()->count('given_on'),
                'expenses' => (int) Expense::where('status', Expense::APPROVED)->whereBetween('spent_on', $days)->sum('amount'),
                'pendingExpenses' => Expense::where('status', Expense::PENDING)->count(),
            ],
            'funds' => Fund::orderBy('sort')->orderBy('id')->get()->each(function (Fund $fund) use ($days, $seeGiving) {
                $fund->period_total = $seeGiving ? (int) $fund->gifts()->paid()->whereBetween('given_on', $days)->sum('amount') : 0;
                $fund->balance = $seeGiving ? $fund->raised() : 0;
            }),
            'transactions' => Gift::with('fund')->paid()->whereBetween('given_on', $days)->when(! $seeGiving, fn ($q) => $q->whereRaw('1 = 0'))
                ->when($method === 'online', fn ($q) => $q->where('method', 'online'))
                ->when($method === 'cash', fn ($q) => $q->where('method', '!=', 'online'))
                ->latest('given_on')->latest('id')->paginate(15, ['*'], 'tx')->withQueryString(),
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

    public function destroyGift(Request $request, Gift $gift): RedirectResponse
    {
        abort_unless($request->user()->can('see-giving'), 403);
        abort_if($gift->method === 'online', 422, 'Online gifts come from Paystack and can\'t be deleted here.');
        $gift->delete();

        return back()->with('status', 'Entry '.$gift->code.' deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('see-giving'), 403);
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
        Fund::create([...$data, 'target' => isset($data['target']) ? (int) round($data['target'] * 100) : null, 'sort' => Fund::max('sort') + 1, 'online' => $request->boolean('online')]);

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
        $fund->update([...$data, 'target' => isset($data['target']) ? (int) round($data['target'] * 100) : null, 'is_active' => $request->boolean('is_active'), 'online' => $request->boolean('online')]);

        return back()->with('status', "{$fund->name} saved.");
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $account = BankAccount::create([...$this->accountData($request, 'account'), 'sort' => (int) BankAccount::max('sort') + 1]);

        return back()->with('status', "{$account->currency} account at {$account->bank_name} added to the Give page.");
    }

    public function updateAccount(Request $request, BankAccount $account): RedirectResponse
    {
        $account->update($this->accountData($request, 'account'.$account->id));

        return back()->with('status', "{$account->currency} account at {$account->bank_name} saved.");
    }

    public function destroyAccount(BankAccount $account): RedirectResponse
    {
        $account->delete();

        return back()->with('status', "{$account->currency} account at {$account->bank_name} removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function accountData(Request $request, string $bag): array
    {
        $data = $request->validateWithBag($bag, [
            'currency' => ['required', Rule::in(array_keys(BankAccount::CURRENCIES))],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:40'],
            'sort_code' => ['nullable', 'string', 'max:20'],
            'routing_number' => ['nullable', 'string', 'max:20'],
            'swift' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]{8,11}$/'],
            'iban' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:200'],
            'use_for' => ['required', Rule::in(array_keys(BankAccount::USES))],
        ], ['swift.regex' => 'A SWIFT/BIC code is 8 or 11 letters and numbers.']);

        return [...$data, 'swift' => isset($data['swift']) ? strtoupper($data['swift']) : null, 'is_active' => $request->boolean('is_active')];
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
