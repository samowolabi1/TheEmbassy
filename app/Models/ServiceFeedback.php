<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's feedback on a service: a rating, what went well, what could be better, a comment.
 * Sent without a name, the church sees the feedback but not who gave it.
 */
#[Fillable(['rating', 'went_well', 'could_improve', 'comment', 'anonymous'])]
class ServiceFeedback extends Model
{
    protected $table = 'service_feedback';

    public const ASPECTS = [
        'worship' => 'Praise and worship',
        'word' => 'The sermon',
        'prayer' => 'Prayer time',
        'welcome' => 'Welcome and ushering',
        'sound' => 'Sound and screens',
        'stream' => 'The live stream',
        'timing' => 'Starting and ending on time',
        'venue' => 'The venue (seating, cooling, cleanliness)',
        'children' => 'Children\'s church',
    ];

    /** How long after a service members can give feedback on it. */
    public const DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['went_well' => 'array', 'could_improve' => 'array', 'anonymous' => 'boolean'];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function from(): string
    {
        return $this->anonymous ? 'A member (no name)' : (string) $this->member?->name;
    }
}
