<?php

namespace App\Services\Tokens;

use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves tokens in and out of someone's balance. Every change is recorded, and a balance can
 * never go below zero, even with two purchases at the same moment.
 */
class Wallet
{
    /**
     * Add tokens. With a key, the same reward is only ever given once (returns false if it was).
     */
    public function credit(User $user, int $amount, string $rule, string $description, ?string $key = null, ?User $by = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $amount, $rule, $description, $key, $by) {
                TokenTransaction::create([
                    'user_id' => $user->id, 'amount' => $amount, 'rule' => $rule,
                    'description' => mb_substr($description, 0, 200), 'key' => $key, 'created_by' => $by?->id,
                ]);
                User::whereKey($user->id)->increment('token_balance', $amount);
            });
        } catch (UniqueConstraintViolationException) {
            return false; // already given
        }
        $user->token_balance = (int) User::whereKey($user->id)->value('token_balance');

        return true;
    }

    /**
     * Take tokens, or fail with a message if there aren't enough. Call inside the caller's transaction
     * when something else must happen with it.
     */
    public function debit(User $user, int $amount, string $rule, string $description, ?string $key = null, ?User $by = null, string $field = 'tokens'): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $rule, $description, $key, $by, $field) {
            // Only succeeds if the balance covers it at this instant.
            $taken = User::whereKey($user->id)->where('token_balance', '>=', $amount)->decrement('token_balance', $amount);
            if (! $taken) {
                $balance = (int) User::whereKey($user->id)->value('token_balance');
                throw ValidationException::withMessages([$field => "You need {$amount} tokens and have {$balance}."]);
            }
            TokenTransaction::create([
                'user_id' => $user->id, 'amount' => -$amount, 'rule' => $rule,
                'description' => mb_substr($description, 0, 200), 'key' => $key, 'created_by' => $by?->id,
            ]);
        });
        $user->token_balance = (int) User::whereKey($user->id)->value('token_balance');
    }
}
