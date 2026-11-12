<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\TokenOrder;
use App\Models\TokenTransaction;
use App\Models\User;
use App\Services\Tokens\Rewards;
use App\Services\Tokens\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tokens across the church: what's been earned and spent, the top earners, and staff giving
 * or taking tokens for things the app can't see (volunteering, competitions, corrections).
 */
class TokenController extends Controller
{
    public function index(Request $request, Rewards $rewards): View
    {
        $request->validate(['member' => ['nullable', 'integer']]);
        $member = $request->filled('member') ? Member::with('user')->find($request->integer('member')) : null;
        $monthStart = now()->startOfMonth();

        return view('admin.tokens', [
            'stats' => [
                'held' => (int) User::sum('token_balance'),
                'earned' => (int) TokenTransaction::where('amount', '>', 0)->whereNotIn('rule', ['refund', 'market_sale'])->where('created_at', '>=', $monthStart)->sum('amount'),
                'spent' => (int) -TokenTransaction::whereIn('rule', ['market_purchase', 'subscription'])->where('created_at', '>=', $monthStart)->sum('amount'),
                'orders' => TokenOrder::where('status', TokenOrder::PENDING)->count(),
            ],
            'rules' => collect(Rewards::RULES)->map(fn ($r, $key) => ['label' => $r[0], 'amount' => $rewards->amount($key)]),
            'top' => TokenTransaction::with('user.member')->selectRaw('user_id, sum(amount) as total')
                ->where('amount', '>', 0)->whereNotIn('rule', ['refund', 'market_sale', 'admin'])
                ->where('created_at', '>=', $monthStart)->groupBy('user_id')->orderByDesc('total')->take(10)->get(),
            'recent' => TokenTransaction::with('user.member', 'creator')
                ->when($member?->user_id, fn ($q) => $q->where('user_id', $member->user_id))
                ->latest('id')->paginate(30)->withQueryString(),
            'member' => $member,
            'members' => Member::active()->whereNotNull('user_id')->orderBy('name')->get(['id', 'name', 'user_id']),
            'enabled' => $rewards->enabled(),
        ]);
    }

    /**
     * Give tokens (a positive number) or take them away (a negative one), with a reason the member sees.
     */
    public function award(Request $request, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', Rule::exists('members', 'id')->whereNotNull('user_id')],
            'amount' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:150'],
        ], ['member_id.exists' => 'That member has no login yet, so they have no wallet.']);

        $member = Member::with('user')->findOrFail($data['member_id']);
        $amount = (int) $data['amount'];
        $amount > 0
            ? $wallet->credit($member->user, $amount, 'admin', $data['reason'], null, $request->user())
            : $wallet->debit($member->user, -$amount, 'admin', $data['reason'], null, $request->user(), 'amount');

        return back()->with('status', ($amount > 0 ? "Gave {$amount} tokens to " : 'Took '.(-$amount).' tokens from ').$member->name.'.');
    }
}
