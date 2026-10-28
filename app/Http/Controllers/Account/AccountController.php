<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\TokenPayment;
use App\Models\Unit;
use App\Models\UnitMeeting;
use App\Models\UnitMessage;
use App\Services\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * A member's own page: their details, giving and tokens.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $member = $request->user()->member?->load('unit', 'fellowship', 'tokenPlan', 'tokenPayments');

        return view('account.show', [
            'user' => $request->user(),
            'member' => $member,
            'gifts' => $member ? $member->gifts()->with('fund')->where('status', 'paid')->latest('given_on')->take(10)->get() : collect(),
            'units' => Unit::orderBy('name')->get(),
            'fellowships' => Fellowship::orderBy('name')->get(),
            'ledUnits' => $member?->ledUnits() ?? collect(),
            'meetings' => $member?->unit_id ? UnitMeeting::where('unit_id', $member->unit_id)->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at')->take(3)->get() : collect(),
            'checkedInMeetings' => $member ? $member->meetings()->pluck('unit_meetings.id')->all() : [],
            'duties' => $member ? $member->duties()->with('service', 'unit')->whereHas('service', fn ($q) => $q->where('held_on', '>=', today()->startOfDay()))->get() : collect(),
            'unitMessages' => $member?->unit_id ? UnitMessage::where('unit_id', $member->unit_id)->latest()->take(3)->get() : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'anniversary' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'fellowship_id' => ['nullable', Rule::exists('fellowships', 'id')],
        ]);

        $member->update($data);

        return back()->with('status', 'Your details are saved.');
    }

    public function tokens(Request $request, Payments $payments): View
    {
        $member = $this->member($request)->load('tokenPlan', 'tokenPayments');
        $returned = $request->filled('paid') ? $member->tokenPayments->firstWhere('id', $request->integer('paid')) : null;

        return view('account.tokens', [
            'member' => $member,
            'history' => $member->tokenPayments->where('status', TokenPayment::PAID)->sortByDesc('from_month'),
            'returned' => $returned,
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    /**
     * Pay tokens for the next one or more months.
     */
    public function payTokens(Request $request, Payments $payments): RedirectResponse
    {
        $member = $this->member($request)->load('tokenPlan', 'tokenPayments');
        $data = $request->validate(['months' => ['required', 'integer', 'min:1', 'max:12']]);

        if ($member->status !== Member::ACTIVE || ! $member->tokenPlan || $member->tokenPlan->isExempt()) {
            throw ValidationException::withMessages(['months' => 'There are no tokens to pay on your plan.']);
        }
        if (! $payments->enabled()) {
            throw ValidationException::withMessages(['months' => 'Online payments aren\'t switched on yet. Please pay at the church office.']);
        }

        $payment = $member->tokenPayments()->create([
            'from_month' => $member->nextTokenMonth(),
            'months' => $data['months'],
            'amount' => $member->tokenPlan->amount * $data['months'],
            'method' => 'online',
        ]);

        try {
            return redirect()->away($payments->checkout($payment, $payment->amount, $request->user()->email));
        } catch (RuntimeException $e) {
            Log::error('Could not start token checkout', ['token_payment' => $payment->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['months' => 'We couldn\'t reach the payment page. Please try again in a moment.']);
        }
    }

    private function member(Request $request): Member
    {
        return $request->user()->member ?? abort(404);
    }
}
