<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Setting;
use App\Models\TokenAccount;
use App\Models\TokenOrder;
use App\Models\TokenTransfer;
use App\Services\Tokens\Ledger;
use App\Services\Tokens\Rewards;
use App\Services\Tokens\TokenValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The token treasury. Staff with the tokens area can look; only the pastor in charge (super admin)
 * creates or destroys tokens, gives or takes them, freezes a wallet, and sets what activities earn.
 */
class TokenController extends Controller
{
    public function index(Request $request, Ledger $ledger, Rewards $rewards): View
    {
        $request->validate(['member' => ['nullable', 'integer'], 'kind' => ['nullable', 'string', 'max:30']]);
        $member = $request->filled('member') ? Member::with('user.tokenAccount')->find($request->integer('member')) : null;
        $account = $member?->user?->tokenAccount;
        $kind = array_key_exists((string) $request->query('kind'), TokenTransfer::KINDS) ? $request->query('kind') : null;
        $monthStart = now()->startOfMonth();
        $month = fn (array $kinds) => (int) TokenTransfer::whereIn('kind', $kinds)->where('created_at', '>=', $monthStart)->sum('amount');

        return view('admin.tokens', [
            'books' => $ledger->audit(),
            'flows' => [
                'out' => $month(['reward', 'grant']),
                'back' => $month(['subscription', 'ticket', 'reclaim']),
                'market' => $month(['sale']),
                'sent' => $month(['send']),
            ],
            'orders' => TokenOrder::where('status', TokenOrder::PENDING)->count(),
            'rules' => collect(Rewards::RULES)->map(fn ($r, $key) => ['label' => $r[0], 'text' => $r[1], 'amount' => $rewards->amount($key)]),
            'top' => TokenAccount::members()->with('user.member')->where('balance', '>', 0)->orderByDesc('balance')->take(10)->get(),
            'recent' => TokenTransfer::with('from.user.member', 'to.user.member', 'creator')
                ->when($account, fn ($q) => $q->where(fn ($w) => $w->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id)))
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->latest('id')->paginate(30)->withQueryString(),
            'member' => $member,
            'account' => $account,
            'kind' => $kind,
            'members' => Member::active()->whereNotNull('user_id')->orderBy('name')->get(['id', 'name', 'user_id']),
            'frozen' => TokenAccount::members()->whereNotNull('frozen_at')->with('user.member')->get(),
            'super' => $request->user()->can('manage-treasury'),
            'treasuryShort' => cache('tokens.treasury_short'),
            'rate' => TokenValue::rateLabel(),
        ]);
    }

    public function mint(Request $request, Ledger $ledger): RedirectResponse
    {
        $data = $request->validateWithBag('mint', [
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'note' => ['required', 'string', 'max:150'],
        ]);
        $ledger->mint((int) $data['amount'], $request->user(), $data['note']);
        cache()->forget('tokens.treasury_short');

        return back()->with('status', number_format($data['amount']).' tokens created in the treasury.');
    }

    public function burn(Request $request, Ledger $ledger): RedirectResponse
    {
        $data = $request->validateWithBag('burn', [
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'note' => ['required', 'string', 'max:150'],
        ]);
        try {
            $ledger->burn((int) $data['amount'], $request->user(), $data['note']);
        } catch (ValidationException $e) {
            throw $e->errorBag('burn');
        }

        return back()->with('status', number_format($data['amount']).' tokens destroyed from the treasury.');
    }

    /**
     * Give tokens from the treasury to a member, or take them back into it, with a reason they see.
     */
    public function award(Request $request, Ledger $ledger): RedirectResponse
    {
        $data = $request->validateWithBag('award', [
            'member_id' => ['required', Rule::exists('members', 'id')->whereNotNull('user_id')],
            'direction' => ['required', Rule::in(['give', 'take'])],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'max:150'],
        ], ['member_id.exists' => 'That member has no login yet, so they have no wallet.']);

        $member = Member::with('user')->findOrFail($data['member_id']);
        $amount = (int) $data['amount'];
        try {
            $data['direction'] === 'give'
                ? $ledger->payOut($member->user, $amount, 'grant', $data['reason'], null, $request->user())
                : $ledger->move($ledger->account($member->user, 'amount', settling: true), $ledger->treasury(), $amount, 'reclaim', $data['reason'], null, $request->user(), 'amount');
        } catch (ValidationException $e) {
            throw $e->errorBag('award'); // shown beside the form
        }

        return back()->with('status', ($data['direction'] === 'give' ? "Gave {$amount} tokens to " : "Took {$amount} tokens back from ").$member->name.'.');
    }

    /**
     * Put a wallet on hold (it can still receive, but can't spend or send), or release it.
     */
    public function freeze(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        abort_unless($member->user, 404);
        $account = app(Ledger::class)->account($member->user, 'reason', settling: true);
        $frozen = ! $account->isFrozen();
        $account->update(['frozen_at' => $frozen ? now() : null, 'frozen_reason' => $frozen ? ($data['reason'] ?? null) : null]);

        return back()->with('status', $frozen ? "{$member->name}'s wallet is on hold." : "{$member->name}'s wallet is released.");
    }

    /**
     * What each activity earns, the token's value, and member-to-member sending.
     */
    public function settings(Request $request): RedirectResponse
    {
        $rules = [
            'naira_per_token' => ['required', 'numeric', 'between:0.01,100000'],
            'daily_limit' => ['required', 'integer', 'between:1,10000000'],
        ];
        foreach (array_keys(Rewards::RULES) as $key) {
            $rules["rules.{$key}"] = ['required', 'integer', 'between:0,100000'];
        }
        $data = $request->validateWithBag('settings', $rules);

        $values = [
            'tokens.enabled' => $request->boolean('enabled') ? '1' : '0',
            'tokens.naira_per_token' => (string) $data['naira_per_token'],
            'tokens.transfers.enabled' => $request->boolean('transfers_enabled') ? '1' : '0',
            'tokens.transfers.daily_limit' => (string) $data['daily_limit'],
        ];
        foreach ($data['rules'] as $key => $amount) {
            $values["tokens.rules.{$key}"] = (string) $amount;
        }
        DB::transaction(fn () => Setting::putMany($values));

        return back()->with('status', 'Token settings saved.');
    }
}
