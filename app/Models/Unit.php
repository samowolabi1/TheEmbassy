<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service unit members join: choir, media, ushering and so on. Its head of department (HOD)
 * and assistant run the unit's own area; welfare and media units also get their own tools.
 */
#[Fillable(['name', 'kind', 'leader_name', 'description', 'hod_member_id', 'assistant_member_id', 'checklist'])]
class Unit extends Model
{
    use HasFactory;

    public const KINDS = [
        'general' => 'Service unit',
        'welfare' => 'Welfare',
        'media' => 'Media',
    ];

    public const DEFAULT_CHECKLIST = [
        'Cameras charged, mounted and framed',
        'Sound desk and microphones tested',
        'Projector and slides (songs, scripture, announcements) loaded',
        'Streaming PC and internet tested',
        'Stream set up on YouTube and Facebook, with title and thumbnail',
        'Recording started',
        'Check-in code screen showing',
    ];

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function hod(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'hod_member_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assistant_member_id');
    }

    /**
     * @return HasMany<UnitMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(UnitMeeting::class);
    }

    /**
     * @return HasMany<UnitMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(UnitMessage::class);
    }

    /**
     * @return HasMany<Duty, $this>
     */
    public function duties(): HasMany
    {
        return $this->hasMany(Duty::class);
    }

    public function isLedBy(?Member $member): bool
    {
        return $member !== null && in_array($member->id, [$this->hod_member_id, $this->assistant_member_id], true);
    }

    public function isWelfare(): bool
    {
        return $this->kind === 'welfare';
    }

    public function isMedia(): bool
    {
        return $this->kind === 'media';
    }

    /**
     * The media unit's setup checklist.
     *
     * @return list<string>
     */
    public function checklistItems(): array
    {
        $items = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->checklist))));

        return $items ?: self::DEFAULT_CHECKLIST;
    }

    /**
     * The unit of a kind (the church's welfare or media unit), if one is set up.
     */
    public static function ofKind(string $kind): ?self
    {
        return static::where('kind', $kind)->orderBy('id')->first();
    }
}
