<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Someone's application to an empowerment programme, with their choices in order.
 */
#[Fillable(['name', 'phone', 'email', 'gender', 'date_of_birth', 'location', 'answers'])]
class EmpowermentApplication extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'shortlisted' => 'Shortlisted',
        'accepted' => 'Accepted',
        'waitlisted' => 'Waiting list',
        'declined' => 'Not selected',
        'completed' => 'Completed the programme',
    ];

    protected function casts(): array
    {
        return ['answers' => 'array', 'date_of_birth' => 'date', 'decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            do {
                $a->reference = 'EMP-'.Str::upper(Str::random(6));
            } while (static::where('reference', $a->reference)->exists());
        });
    }

    /**
     * @return BelongsTo<EmpowermentProgramme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(EmpowermentProgramme::class, 'empowerment_programme_id');
    }

    /**
     * What they chose, first choice first.
     *
     * @return BelongsToMany<EmpowermentTrack, $this>
     */
    public function choices(): BelongsToMany
    {
        return $this->belongsToMany(EmpowermentTrack::class, 'empowerment_application_track')->withPivot('rank')->orderByPivot('rank');
    }

    /**
     * The choice they were accepted for.
     *
     * @return BelongsTo<EmpowermentTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(EmpowermentTrack::class, 'empowerment_track_id');
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
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function whatsappUrl(): ?string
    {
        $digits = Member::internationalPhone($this->phone);

        return $digits ? 'https://wa.me/'.$digits : null;
    }
}
