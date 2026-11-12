<?php

namespace App\Services\Tokens;

use App\Models\TokenAccount;
use App\Models\TokenTransfer;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The token currency's books. Tokens only come into being when the pastor in charge mints them into
 * the treasury, and only leave when they're burned from it; everything else is a transfer from one
 * account to another. So the sum of every balance always equals tokens minted less tokens burned.
 */
class Ledger
{
    /** Kinds where a member is spending their own tokens (a frozen wallet can't do these). */
    private const SPENDING = ['purchase', 'send', 'subscription', 'ticket'];

    public function treasury(): TokenAccount
    {
        return $this->fresh(TokenAccount::firstOrCreate(['kind' => TokenAccount::TREASURY, 'user_id' => null]));
    }

    public function escrow(): TokenAccount
    {
        return $this->fresh(TokenAccount::firstOrCreate(['kind' => TokenAccount::ESCROW, 'user_id' => null]));
    }

    /**
     * A member's wallet. Only church members have one; anyone else is refused. With $settling, the
     * wallet of someone who has stopped being a member can still be used to settle what's already
     * under way (a refund owed to them, or the pastor taking tokens back).
     */
    public function account(User $user, string $field = 'tokens', bool $settling = false): TokenAccount
    {
        if (! $user->canUseTokens() && ! ($settling && TokenAccount::where('user_id', $user->id)->exists())) {
            throw ValidationException::withMessages([$field => 'Tokens are for church members only.']);
        }

        return $this->fresh(TokenAccount::firstOrCreate(['user_id' => $user->id], ['kind' => TokenAccount::MEMBER]));
    }

    /**
     * A new account comes back without its database defaults (balance 0); load them.
     */
    private function fresh(TokenAccount $account): TokenAccount
    {
        return $account->wasRecentlyCreated ? $account->refresh() : $account;
    }

    public function balance(User $user): int
    {
        return (int) TokenAccount::where('user_id', $user->id)->value('balance');
    }

    /**
     * Move tokens. Fails with a message if the sender doesn't have enough (checked at the instant of
     * the move, so two spends at once can't both succeed). With a key, the same move happens once:
     * a repeat returns false and changes nothing.
     */
    public function move(?TokenAccount $from, ?TokenAccount $to, int $amount, string $kind, string $description, ?string $key = null, ?User $by = null, string $field = 'tokens'): bool
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([$field => 'Enter a number of tokens above zero.']);
        }
        if ($from === null && $kind !== 'mint') {
            throw new \LogicException('Only minting creates tokens.');
        }

        try {
            DB::transaction(function () use ($from, $to, $amount, $kind, $description, $key, $by, $field) {
                TokenTransfer::create([
                    'from_account_id' => $from?->id, 'to_account_id' => $to?->id, 'amount' => $amount, 'kind' => $kind,
                    'description' => mb_substr($description, 0, 200), 'key' => $key, 'created_by' => $by?->id,
                ]);

                if ($from) {
                    $query = TokenAccount::whereKey($from->id)->where('balance', '>=', $amount);
                    if (in_array($kind, self::SPENDING, true)) {
                        $query->whereNull('frozen_at');
                    }
                    if (! $query->decrement('balance', $amount)) {
                        $fresh = $from->fresh();
                        throw ValidationException::withMessages([$field => match (true) {
                            $fresh->isFrozen() && in_array($kind, self::SPENDING, true) => 'This wallet is on hold. Please speak to the church office.',
                            $fresh->kind === TokenAccount::TREASURY => "The church treasury has {$fresh->balance} tokens; {$amount} are needed. The pastor in charge can create more.",
                            default => "You need {$amount} tokens and have {$fresh->balance}.",
                        }]);
                    }
                }
                if ($to) {
                    TokenAccount::whereKey($to->id)->increment('balance', $amount);
                }
            });
        } catch (UniqueConstraintViolationException) {
            return false; // already done
        }

        return true;
    }

    /**
     * Create new tokens in the treasury. Only the pastor in charge does this.
     */
    public function mint(int $amount, User $by, string $note): void
    {
        $this->move(null, $this->treasury(), $amount, 'mint', $note, null, $by, 'amount');
    }

    /**
     * Destroy tokens from the treasury, e.g. to shrink the supply.
     */
    public function burn(int $amount, User $by, string $note): void
    {
        $this->move($this->treasury(), null, $amount, 'burn', $note, null, $by, 'amount');
    }

    /**
     * Pay from the treasury to a member. Rewards quietly skip when the treasury is short
     * (returns false); grants by the pastor throw so they see why.
     */
    public function payOut(User $to, int $amount, string $kind, string $description, ?string $key = null, ?User $by = null, bool $quiet = false): bool
    {
        if ($quiet && ! $to->canUseTokens()) {
            return false; // rewards are for members only
        }
        try {
            return $this->move($this->treasury(), $this->account($to), $amount, $kind, $description, $key, $by, 'amount');
        } catch (ValidationException $e) {
            if ($quiet) {
                cache()->put('tokens.treasury_short', now()->toIso8601String(), now()->addDays(7));

                return false;
            }
            throw $e;
        }
    }

    /**
     * Tokens in existence, and whether the books add up: every balance matches its transfers,
     * and all balances together equal minted less burned.
     *
     * @return array{minted: int, burned: int, supply: int, held: int, treasury: int, escrow: int, members: int, ok: bool, problems: list<string>}
     */
    public function audit(): array
    {
        $minted = (int) TokenTransfer::where('kind', 'mint')->sum('amount');
        $burned = (int) TokenTransfer::where('kind', 'burn')->sum('amount');
        $problems = [];

        $in = TokenTransfer::whereNotNull('to_account_id')->selectRaw('to_account_id as id, sum(amount) as total')->groupBy('to_account_id')->pluck('total', 'id');
        $out = TokenTransfer::whereNotNull('from_account_id')->selectRaw('from_account_id as id, sum(amount) as total')->groupBy('from_account_id')->pluck('total', 'id');
        foreach (TokenAccount::all() as $account) {
            $expected = (int) ($in[$account->id] ?? 0) - (int) ($out[$account->id] ?? 0);
            if ($expected !== $account->balance) {
                $problems[] = "{$account->label()} shows {$account->balance} but its transfers add up to {$expected}.";
            }
        }
        $held = (int) TokenAccount::sum('balance');
        if ($held !== $minted - $burned) {
            $problems[] = "All balances add up to {$held}, but {$minted} were created and {$burned} destroyed.";
        }

        return [
            'minted' => $minted,
            'burned' => $burned,
            'supply' => $minted - $burned,
            'held' => $held,
            'treasury' => (int) TokenAccount::where('kind', TokenAccount::TREASURY)->sum('balance'),
            'escrow' => (int) TokenAccount::where('kind', TokenAccount::ESCROW)->sum('balance'),
            'members' => (int) TokenAccount::where('kind', TokenAccount::MEMBER)->sum('balance'),
            'ok' => $problems === [],
            'problems' => $problems,
        ];
    }
}
