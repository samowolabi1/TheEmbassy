<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A member's idea, request or concern. The church answers it, and the member sees the answer
 * and where it's up to. Sent without a name, staff don't see who sent it.
 */
#[Fillable(['category', 'subject', 'body', 'anonymous'])]
class Suggestion extends Model
{
    public const CATEGORIES = [
        'services' => 'Services and worship',
        'events' => 'Events and programmes',
        'units' => 'Units and serving',
        'welfare' => 'Welfare and care',
        'youth' => 'Youth, campus and young adults',
        'facilities' => 'The building and facilities',
        'app' => 'This app and website',
        'communication' => 'Announcements and communication',
        'other' => 'Something else',
    ];

    public const STATUSES = [
        'new' => 'Sent',
        'reviewing' => 'Being looked at',
        'planned' => 'We\'re doing it',
        'done' => 'Done',
        'declined' => 'Not for now',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['anonymous' => 'boolean', 'responded_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function from(): string
    {
        return $this->anonymous ? 'A member (no name)' : (string) $this->member?->name;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? Str::headline($this->category);
    }
}
