<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Declaration;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\Product;
use App\Models\Service;
use App\Models\TokenOrder;
use App\Models\TokenTransaction;
use App\Models\Unit;
use App\Models\UnitMeeting;
use App\Models\UnitMessage;
use App\Models\WelfarePackage;
use App\Services\Messaging\Inbox;
use App\Services\Tokens\Rewards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member's own page: their details, quick links, and their token wallet.
 */
class AccountController extends Controller
{
    public function show(Request $request, Inbox $inbox): View
    {
        $member = $request->user()->member?->load('unit', 'fellowship');
        $active = $member?->status === Member::ACTIVE;

        return view('account.show', [
            'user' => $request->user(),
            'member' => $member,
            'units' => Unit::orderBy('name')->get(),
            'fellowships' => Fellowship::orderBy('name')->get(),
            'ledUnits' => $member?->ledUnits() ?? collect(),
            'meetings' => $member?->unit_id ? UnitMeeting::where('unit_id', $member->unit_id)->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at')->take(3)->get() : collect(),
            'checkedInMeetings' => $member ? $member->meetings()->pluck('unit_meetings.id')->all() : [],
            'duties' => $member ? $member->duties()->with('service', 'unit')->whereHas('service', fn ($q) => $q->where('held_on', '>=', today()->startOfDay()))->get() : collect(),
            'unitMessages' => $member?->unit_id ? UnitMessage::where('unit_id', $member->unit_id)->latest()->take(3)->get() : collect(),
            'unread' => $active ? $inbox->unread($request->user()) : 0,
            'joinable' => $active ? Service::joinableNow() : collect(),
            'openPackages' => $active ? WelfarePackage::open()->whereDoesntHave('applications', fn ($q) => $q->where('member_id', $member->id))->count() : 0,
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

        $member->update([...$data, 'notify_sms' => $request->boolean('notify_sms')]);
        $tokens = app(Rewards::class)->profile($member->fresh('user'));

        return back()->with('status', 'Your details are saved.'.($tokens ? " Your profile is complete: +{$tokens} tokens!" : ''));
    }

    /**
     * The token wallet: balance, how to earn, where to spend, history and market orders.
     */
    public function tokens(Request $request, Rewards $rewards): View
    {
        $user = $request->user();

        return view('account.tokens', [
            'user' => $user,
            'balance' => (int) $user->token_balance,
            'history' => TokenTransaction::where('user_id', $user->id)->latest('id')->paginate(20),
            'orders' => TokenOrder::with('shop')->where('user_id', $user->id)->latest()->take(20)->get(),
            'rules' => collect(Rewards::RULES)->map(fn ($r, $key) => ['label' => $r[0], 'text' => $r[1], 'amount' => $rewards->amount($key)])->filter(fn ($r) => $r['amount'] > 0),
            'earnedThisMonth' => (int) TokenTransaction::where('user_id', $user->id)->where('amount', '>', 0)->where('rule', '!=', 'refund')->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'forTokens' => Product::listed()->whereNotNull('token_price')->with('shop')->latest()->take(4)->get(),
            'declaration' => Declaration::current(),
            'declared' => ($d = Declaration::current()) ? TokenTransaction::where('key', 'declaration:week:'.$d->week_of->toDateString().':'.$user->id)->exists() : false,
        ]);
    }

    /**
     * "I declare it": saying this week's declaration earns a few tokens once a week.
     */
    public function declare(Request $request, Rewards $rewards): RedirectResponse
    {
        $member = $this->member($request);
        abort_unless($member->status === Member::ACTIVE, 403);
        $declaration = Declaration::current() ?? abort(404);
        $tokens = $rewards->declared($request->user(), $declaration->week_of);

        return back()->with('status', $tokens ? "Amen! +{$tokens} tokens." : 'You\'ve declared it this week. Amen!');
    }

    private function member(Request $request): Member
    {
        return $request->user()->member ?? abort(404);
    }
}
