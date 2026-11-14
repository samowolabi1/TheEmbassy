<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MoneyCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Where every staff member records money that came in or went out. Each entry waits for finance
 * or a pastor to confirm it; staff see what they've recorded and where it's up to.
 */
class MoneyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $mine = Income::where('recorded_by', $user->id)->latest()->take(15)->get()->map(fn (Income $i) => ['kind' => 'in', 'item' => $i, 'date' => $i->received_on])
            ->concat(Expense::where('requested_by', $user->id)->latest()->take(15)->get()->map(fn (Expense $e) => ['kind' => 'out', 'item' => $e, 'date' => $e->spent_on]))
            ->sortByDesc(fn ($row) => $row['item']->created_at)->take(20)->values();

        return view('admin.money', [
            'type' => $request->query('type') === 'out' ? 'out' : 'in',
            'incomeCategories' => MoneyCategory::names(MoneyCategory::IN),
            'expenseCategories' => MoneyCategory::names(MoneyCategory::OUT),
            'mine' => $mine,
        ]);
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(MoneyCategory::names(MoneyCategory::IN))],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Income::METHODS))],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $income = new Income([...collect($data)->except('receipt')->all(), 'amount' => (int) round($data['amount'] * 100)]);
        $income->receipt_path = $request->file('receipt')?->store('receipts', 'local');
        $income->recorded_by = $request->user()->id;
        $income->save();

        return redirect()->route('admin.money.index', ['type' => 'in'])->with('status', "\"{$income->title}\" recorded. Finance will confirm it.");
    }
}
