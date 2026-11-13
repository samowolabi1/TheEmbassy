<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Declaration;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\Product;
use App\Models\Service;
use App\Models\TokenOrder;
use App\Models\TokenTransfer;
use App\Models\Unit;
use App\Models\UnitMeeting;
use App\Models\UnitMessage;
use App\Models\WelfarePackage;
use App\Services\Celebrations\BirthdayCard;
use App\Services\Celebrations\Birthdays;
use App\Services\Content\Images;
use App\Services\Messaging\Inbox;
use App\Services\Tokens\Ledger;
use App\Services\Tokens\Rewards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'birthday' => $active ? app(Birthdays::class)->recent($member) : null,
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
     * Their birthday card: a photo to put on it, and whether to be celebrated publicly.
     */
    public function birthday(Request $request, Images $images): RedirectResponse
    {
        $member = $this->member($request);
        $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'remove_photo' => ['nullable', 'boolean'],
        ], ['photo.max' => 'Choose a photo under 15 MB.']);

        if ($request->boolean('remove_photo') || $request->hasFile('photo')) {
            $images->delete($member->photo_path);
            $member->photo_path = null;
        }
        if ($file = $request->file('photo')) {
            try {
                $saved = $images->store($file, 'members/photos', 1200, 160);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['photo' => $e->getMessage()]);
            }
            $images->delete($saved['thumb_path']);
            $member->photo_path = $saved['path'];
        }
        $member->celebrate_birthday = $request->boolean('celebrate_birthday');
        $member->save();

        return back()->with('status', 'Birthday card settings saved.');
    }

    /**
     * Their own card, to preview before the day or download on it.
     */
    public function birthdayCard(Request $request, BirthdayCard $cards): Response
    {
        $member = $this->member($request);
        abort_unless($member->birthday, 404);

        return response($cards->forMember($member), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'Content-Disposition' => 'inline; filename="birthday-card.png"']);
    }

    /**
     * The token wallet: balance, how to earn, where to spend, sending to others, history and orders.
     */
    public function tokens(Request $request, Rewards $rewards, Ledger $ledger): View
    {
        $user = $request->user();
        if (! $user->canUseTokens()) {
            return view('account.tokens-members-only');
        }
        $account = $ledger->account($user);
        $declaration = Declaration::current();

        return view('account.tokens', [
            'user' => $user,
            'account' => $account,
            'balance' => $account->balance,
            'history' => TokenTransfer::with('from.user', 'to.user')
                ->where(fn ($q) => $q->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
                ->latest('id')->paginate(20),
            'orders' => TokenOrder::with('shop')->where('user_id', $user->id)->latest()->take(20)->get(),
            'rules' => collect(Rewards::RULES)->map(fn ($r, $key) => ['label' => $r[0], 'text' => $r[1], 'amount' => $rewards->amount($key)])->filter(fn ($r) => $r['amount'] > 0),
            'earnedThisMonth' => (int) TokenTransfer::where('to_account_id', $account->id)->whereIn('kind', ['reward', 'grant'])->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'forTokens' => Product::listed()->whereNotNull('token_price')->with('shop.user.member')->latest()->take(12)->get()->filter->acceptsTokens()->take(4),
            'declaration' => $declaration,
            'declared' => $declaration ? TokenTransfer::where('key', 'declaration:week:'.$declaration->week_of->toDateString().':'.$user->id)->exists() : false,
            'canSend' => (bool) config('tokens.transfers.enabled') && $user->member?->status === Member::ACTIVE,
            'sentToday' => $this->sentToday($account->id),
            'dailyLimit' => (int) config('tokens.transfers.daily_limit', 1000),
        ]);
    }

    /**
     * Send tokens to another member, found by the phone number or email on their account.
     */
    public function sendTokens(Request $request, Ledger $ledger): RedirectResponse
    {
        $user = $request->user();
        abort_unless(config('tokens.transfers.enabled') && $user->member?->status === Member::ACTIVE, 403, 'Sending tokens isn\'t switched on.');
        $data = $request->validate([
            'to' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:100'],
        ], ['to.required' => 'Enter their phone number or email.']);

        $recipient = $this->findMember(trim($data['to']));
        if (! $recipient || ! $recipient->user) {
            throw ValidationException::withMessages(['to' => 'No member with that phone number or email has an account.']);
        }
        if ($recipient->user_id === $user->id) {
            throw ValidationException::withMessages(['to' => 'That\'s you.']);
        }

        $account = $ledger->account($user);
        $limit = (int) config('tokens.transfers.daily_limit', 1000);
        if ($this->sentToday($account->id) + $data['amount'] > $limit) {
            throw ValidationException::withMessages(['amount' => "You can send up to {$limit} tokens a day; you've sent ".$this->sentToday($account->id).' today.']);
        }

        $note = filled($data['note'] ?? null) ? ': '.$data['note'] : '';
        $ledger->move($account, $ledger->account($recipient->user), (int) $data['amount'], 'send',
            $user->communityName().' → '.$recipient->name.$note, null, $user, 'amount');

        return back()->with('status', "Sent {$data['amount']} tokens to {$recipient->name}.");
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

    private function sentToday(int $accountId): int
    {
        return (int) TokenTransfer::where('from_account_id', $accountId)->where('kind', 'send')->where('created_at', '>=', today())->sum('amount');
    }

    /**
     * An active member by phone (any common format) or email.
     */
    private function findMember(string $to): ?Member
    {
        if (str_contains($to, '@')) {
            $member = Member::active()->where('email', strtolower($to))->first();

            return $member ?? Member::active()->whereHas('user', fn ($q) => $q->where('email', strtolower($to)))->first();
        }
        $wanted = Member::internationalPhone($to);

        return $wanted ? Member::active()->whereNotNull('phone')->get()->first(fn (Member $m) => Member::internationalPhone($m->phone) === $wanted) : null;
    }

    private function member(Request $request): Member
    {
        return $request->user()->member ?? abort(404);
    }
}
