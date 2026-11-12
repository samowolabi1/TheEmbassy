<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of tokens from one account to another. A mint has no sender; a burn has no receiver.
 */
#[Fillable(['from_account_id', 'to_account_id', 'amount', 'kind', 'description', 'key', 'created_by'])]
class TokenTransfer extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'mint' => 'Created',
        'burn' => 'Destroyed',
        'reward' => 'Reward',
        'grant' => 'Given by the pastor',
        'reclaim' => 'Taken back',
        'purchase' => 'Market purchase',
        'sale' => 'Market sale',
        'refund' => 'Refund',
        'subscription' => 'Shop subscription',
        'ticket' => 'Event ticket',
        'send' => 'Sent between members',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /**
     * @return BelongsTo<TokenAccount, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(TokenAccount::class, 'from_account_id');
    }

    /**
     * @return BelongsTo<TokenAccount, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(TokenAccount::class, 'to_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    /**
     * Seen from one account: tokens in are positive, tokens out negative.
     */
    public function amountFor(TokenAccount $account): int
    {
        return $this->to_account_id === $account->id ? $this->amount : -$this->amount;
    }
}
