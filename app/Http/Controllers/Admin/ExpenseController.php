<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Expense;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spending: logged with a receipt, approved by someone other than the person who logged it,
 * and measured against each category's monthly budget.
 */
class ExpenseController extends Controller
{
    private const TABS = ['pending' => 'Awaiting approval', 'approved' => 'Approved', 'declined' => 'Declined'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';
        $month = $this->month($request);
        $days = [$month->copy(), $month->copy()->endOfMonth()];

        $spent = Expense::where('status', Expense::APPROVED)->whereBetween('spent_on', $days)
            ->selectRaw('category, sum(amount) as total')->groupBy('category')->pluck('total', 'category');
        $budgets = Budget::whereDate('month', $month->toDateString())->pluck('amount', 'category');

        return view('admin.expenses', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => Expense::where('status', $key)->count()]),
            'expenses' => Expense::with('requester', 'decider')->where('status', $tab)->latest('spent_on')->latest('id')->paginate(20)->withQueryString(),
            'categories' => config('finance.expense_categories'),
            'month' => $month,
            'lines' => collect(config('finance.expense_categories'))->map(fn ($category) => [
                'category' => $category,
                'budget' => (int) ($budgets[$category] ?? 0),
                'spent' => (int) ($spent[$category] ?? 0),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(config('finance.expense_categories'))],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $expense = new Expense([...$data, 'amount' => (int) round($data['amount'] * 100)]);
        $expense->receipt_path = $request->file('receipt')?->store('receipts', 'local');
        $expense->requested_by = $request->user()->id;
        $expense->save();

        return redirect()->route('admin.expenses.index')->with('status', "\"{$expense->title}\" logged and waiting for approval.");
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->ensureCanDecide($request, $expense);
        $expense->forceFill(['status' => Expense::APPROVED, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decline_reason' => null])->save();

        return back()->with('status', "\"{$expense->title}\" approved.");
    }

    public function decline(Request $request, Expense $expense): RedirectResponse
    {
        $this->ensureCanDecide($request, $expense);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $expense->forceFill(['status' => Expense::DECLINED, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decline_reason' => $data['reason']])->save();

        return back()->with('status', "\"{$expense->title}\" declined.");
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->hasReceipt(), 404);

        return Storage::disk('local')->download($expense->receipt_path, 'receipt-'.$expense->id.'.'.pathinfo($expense->receipt_path, PATHINFO_EXTENSION));
    }

    public function budgets(Request $request): RedirectResponse
    {
        $month = $this->month($request);
        $data = $request->validate([
            'budgets' => ['array'],
            'budgets.*' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ]);

        foreach (config('finance.expense_categories') as $i => $category) {
            $amount = $data['budgets'][$i] ?? null;
            $budget = Budget::where('category', $category)->whereDate('month', $month->toDateString())->first();
            if ($amount === null || $amount === '') {
                $budget?->delete();
            } else {
                ($budget ?? new Budget(['category' => $category, 'month' => $month]))->fill(['amount' => (int) round($amount * 100)])->save();
            }
        }

        return back()->with('status', 'Budgets for '.$month->format('F Y').' saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $month = $this->month($request);
        $query = Expense::with('requester', 'decider')->whereBetween('spent_on', [$month->copy(), $month->copy()->endOfMonth()])->orderBy('spent_on');

        return Csv::download('expenses-'.$month->format('Y-m').'.csv',
            ['date', 'title', 'category', 'amount_naira', 'status', 'logged_by', 'decided_by', 'note'],
            function ($write) use ($query) {
                $query->each(fn (Expense $e) => $write([
                    $e->spent_on->toDateString(), $e->title, $e->category, number_format($e->amount / 100, 2, '.', ''), $e->status,
                    $e->requester?->name, $e->decider?->name, $e->decline_reason ?? $e->description,
                ]));
            });
    }

    /**
     * Nobody approves their own spending, except an administrator.
     */
    private function ensureCanDecide(Request $request, Expense $expense): void
    {
        abort_unless($expense->status === Expense::PENDING, 422, 'This expense has already been decided.');
        abort_if($expense->requested_by === $request->user()->id && ! $request->user()->isAdmin(), 403, 'Someone else needs to approve an expense you logged.');
    }

    private function month(Request $request): Carbon
    {
        $month = (string) $request->query('month', $request->input('month', ''));

        return preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay() : now()->startOfMonth();
    }
}
