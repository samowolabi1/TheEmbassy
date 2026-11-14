<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member borrowing a book: reserved (a copy waits at church for them), waiting (in the queue
 * for a copy), on loan, then returned. Or cancelled.
 */
class BookLoan extends Model
{
    public const REQUESTED = 'requested';

    public const WAITING = 'waiting';

    public const ON_LOAN = 'on_loan';

    public const RETURNED = 'returned';

    public const CANCELLED = 'cancelled';

    /** Still open: holding or wanting a copy. */
    public const OPEN = [self::REQUESTED, self::WAITING, self::ON_LOAN];

    public const LABELS = [
        self::REQUESTED => 'Ready to collect',
        self::WAITING => 'On the waiting list',
        self::ON_LOAN => 'Borrowed',
        self::RETURNED => 'Returned',
        self::CANCELLED => 'Cancelled',
    ];

    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lent_at' => 'datetime',
            'due_on' => 'date',
            'returned_at' => 'datetime',
            'reminded_at' => 'datetime',
            'overdue_reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::ON_LOAN && $this->due_on?->lt(today());
    }

    public function label(): string
    {
        return $this->isOverdue() ? 'Overdue' : (self::LABELS[$this->status] ?? $this->status);
    }

    /**
     * Their place in the queue, for someone waiting (1 = next).
     */
    public function queuePosition(): ?int
    {
        if ($this->status !== self::WAITING) {
            return null;
        }

        return static::where('book_id', $this->book_id)->where('status', self::WAITING)->where('id', '<=', $this->id)->count();
    }
}
