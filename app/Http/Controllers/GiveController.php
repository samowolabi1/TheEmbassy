<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Fund;
use App\Models\Gift;
use App\Services\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * The Give page: tithes and offerings by bank transfer only (in any of the church's currencies),
 * and donations by transfer or online through Paystack.
 */
class GiveController extends Controller
{
    public function create(Request $request, Payments $payments): View
    {
        Fund::ensureDefaults();
        $funds = Fund::active()->get();
        $donationFunds = $funds->where('online', true)->values();
        $appeal = $donationFunds->first(fn (Fund $f) => $f->target);
        $accounts = BankAccount::active()->get();

        return view('pages.give', [
            'presets' => [5000, 10000, 20000, 50000],
            'defaultAmount' => 10000,
            'funds' => $donationFunds,
            'transferFunds' => $funds->where('online', false)->values(),
            'titheAccounts' => $accounts->whereIn('use_for', ['all', 'tithe'])->values(),
            'donationAccounts' => $accounts->where('use_for', 'donations')->values(),
            'allAccounts' => $accounts,
            'appeal' => $appeal,
            'appealRaised' => $appeal?->raised() ?? 0,
            'appealGivers' => $appeal ? $appeal->gifts()->paid()->distinct()->count('email') : 0,
            'paymentsEnabled' => $payments->enabled(),
            'member' => $request->user()?->member,
        ]);
    }

    public function store(Request $request, Payments $payments): RedirectResponse
    {
        $request->merge(['amount' => preg_replace('/[^\d]/', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.config('finance.min_gift'), 'max:'.config('finance.max_gift')],
            // Only donation funds: tithes and offerings are never taken through a payment company.
            'fund' => ['required', Rule::exists('funds', 'id')->where('is_active', true)->where('online', true)],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
        ], [
            'amount.min' => 'Please enter an amount of at least ₦'.number_format(config('finance.min_gift')).'.',
            'fund.*' => 'Choose a donation to give towards. Tithes and offerings are by bank transfer.',
        ]);

        if (! $payments->enabled()) {
            throw ValidationException::withMessages(['amount' => 'Online giving is not switched on yet. Please use the bank transfer details below for now.']);
        }

        $member = $request->user()?->member;
        $gift = Gift::create([
            'fund_id' => $data['fund'],
            'member_id' => $member?->id,
            'name' => ($data['name'] ?? null) ?: ($member?->name ?? 'Guest giver'),
            'email' => $data['email'],
            'amount' => $data['amount'] * 100,
            'method' => 'online',
            'given_on' => today(),
        ]);

        try {
            return redirect()->away($payments->checkout($gift, $gift->amount, $gift->email));
        } catch (RuntimeException $e) {
            Log::error('Could not start giving checkout', ['gift' => $gift->code, 'error' => $e->getMessage()]);

            return back()->withInput()->withErrors(['amount' => 'We couldn\'t reach the payment page. Please try again in a moment.']);
        }
    }

    /**
     * The giver's thank-you page (signed link).
     */
    public function thanks(Gift $gift): View
    {
        return view('pages.give-thanks', ['gift' => $gift->load('fund')]);
    }
}
