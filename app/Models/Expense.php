<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Money the church spends. Staff log it with a receipt; finance or a pastor approves it.
 */
#[Fillable(['title', 'category', 'amount', 'spent_on', 'description', 'receipt_path'])]
class Expense extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'spent_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function hasReceipt(): bool
    {
        return $this->receipt_path !== null && Storage::disk('local')->exists($this->receipt_path);
    }
}
