<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MoneyCategory;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Income for finance and pastors: confirm what staff recorded, see each month's money in and
 * out by category, and keep the categories.
 */
class IncomeController extends Controller
{
    private const TABS = ['pending' => 'To confirm', 'confirmed' => 'Confirmed', 'declined' => 'Declined'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';
        $month = $this->month($request);
        $days = [$month->copy(), $month->copy()->endOfMonth()];

        $in = Income::where('status', Income::CONFIRMED)->whereBetween('received_on', $days)
            ->selectRaw('category, sum(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category')->map(fn ($t) => (int) $t);
        $out = Expense::where('status', Expense::APPROVED)->whereBetween('spent_on', $days)
            ->selectRaw('category, sum(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category')->map(fn ($t) => (int) $t);
        MoneyCategory::ensureDefaults();

        return view('admin.income', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => ['label' => $label, 'count' => Income::where('status', $key)->count()]),
            'incomes' => Income::with('recorder', 'decider')->where('status', $tab)->latest('received_on')->latest('id')->paginate(20)->withQueryString(),
            'month' => $month,
            'in' => $in,
            'out' => $out,
            'pendingIn' => (int) Income::where('status', Income::PENDING)->whereBetween('received_on', $days)->sum('amount'),
            'pendingOut' => (int) Expense::where('status', Expense::PENDING)->whereBetween('spent_on', $days)->sum('amount'),
            'categories' => MoneyCategory::orderBy('kind')->orderBy('sort')->orderBy('name')->get()->groupBy('kind'),
        ]);
    }

    public function confirm(Request $request, Income $income): RedirectResponse
    {
        $this->ensureCanDecide($request, $income);
        $income->forceFill(['status' => Income::CONFIRMED, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decline_reason' => null])->save();

        return back()->with('status', "\"{$income->title}\" confirmed.");
    }

    public function decline(Request $request, Income $income): RedirectResponse
    {
        $this->ensureCanDecide($request, $income);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $income->forceFill(['status' => Income::DECLINED, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decline_reason' => $data['reason']])->save();

        return back()->with('status', "\"{$income->title}\" declined.");
    }

    public function receipt(Income $income): StreamedResponse
    {
        abort_unless($income->hasReceipt(), 404);

        return Storage::disk('local')->download($income->receipt_path, 'income-'.$income->id.'.'.pathinfo($income->receipt_path, PATHINFO_EXTENSION));
    }

    /**
     * The month's money in and out, one row each, for a spreadsheet.
     */
    public function export(Request $request): StreamedResponse
    {
        $month = $this->month($request);
        $days = [$month->copy(), $month->copy()->endOfMonth()];

        return Csv::download('income-and-expenses-'.$month->format('Y-m').'.csv',
            ['date', 'in_or_out', 'title', 'category', 'amount_naira', 'method', 'status', 'recorded_by', 'decided_by', 'note'],
            function ($write) use ($days) {
                Income::with('recorder', 'decider')->whereBetween('received_on', $days)->orderBy('received_on')->each(fn (Income $i) => $write([
                    $i->received_on->toDateString(), 'in', $i->title, $i->category, number_format($i->amount / 100, 2, '.', ''), $i->methodLabel(), $i->status,
                    $i->recorder?->name, $i->decider?->name, $i->decline_reason ?? $i->description,
                ]));
                Expense::with('requester', 'decider')->whereBetween('spent_on', $days)->orderBy('spent_on')->each(fn (Expense $e) => $write([
                    $e->spent_on->toDateString(), 'out', $e->title, $e->category, number_format($e->amount / 100, 2, '.', ''), '', $e->status,
                    $e->requester?->name, $e->decider?->name, $e->decline_reason ?? $e->description,
                ]));
            });
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('categories', [
            'kind' => ['required', Rule::in([MoneyCategory::IN, MoneyCategory::OUT])],
            'name' => ['required', 'string', 'max:60', Rule::unique('money_categories')->where('kind', $request->input('kind'))],
        ], ['name.unique' => 'There is already a category with that name.']);
        MoneyCategory::ensureDefaults();
        MoneyCategory::create([...$data, 'sort' => (int) MoneyCategory::where('kind', $data['kind'])->max('sort') + 1]);

        return back()->with('status', "\"{$data['name']}\" added.");
    }

    /**
     * Rename (records and budgets follow the new name) or hide a category. Hidden categories
     * keep their history but can't be chosen for new entries.
     */
    public function updateCategory(Request $request, MoneyCategory $category): RedirectResponse
    {
        $data = $request->validateWithBag('categories', [
            'name' => ['required', 'string', 'max:60', Rule::unique('money_categories')->where('kind', $category->kind)->ignore($category->id)],
        ], ['name.unique' => 'There is already a category with that name.']);
        $old = $category->name;
        DB::transaction(function () use ($category, $data, $old, $request) {
            $category->update(['name' => $data['name'], 'is_active' => $request->boolean('is_active')]);
            if ($old !== $data['name']) {
                if ($category->kind === MoneyCategory::IN) {
                    Income::where('category', $old)->update(['category' => $data['name']]);
                } else {
                    Expense::where('category', $old)->update(['category' => $data['name']]);
                    Budget::where('category', $old)->update(['category' => $data['name']]);
                }
            }
        });

        return back()->with('status', "\"{$category->name}\" saved.");
    }

    /**
     * Nobody confirms money they recorded themselves, except an administrator.
     */
    private function ensureCanDecide(Request $request, Income $income): void
    {
        abort_unless($income->status === Income::PENDING, 422, 'This has already been decided.');
        abort_if($income->recorded_by === $request->user()->id && ! $request->user()->isAdmin(), 403, 'Someone else needs to confirm money you recorded.');
    }

    private function month(Request $request): Carbon
    {
        $month = (string) $request->query('month', '');

        return preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay() : now()->startOfMonth();
    }
}
