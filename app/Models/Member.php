<?php

namespace App\Models;

use App\Services\Care\FollowUps;
use App\Services\Tokens\Rewards;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Someone in the church family. They may have a login (user) or be on the register only.
 */
#[Fillable(['name', 'phone', 'email', 'gender', 'birthday', 'anniversary', 'address', 'unit_id', 'fellowship_id', 'joined_on', 'baptised', 'believers_class', 'workers_training', 'sod', 'bible_college', 'notes', 'notify_sms', 'celebrate_birthday'])]
class Member extends Model
{
    use HasFactory;

    /**
     * Ranks, in order: every member; workers (everyone serving in a unit); ministers, who work
     * closely with the pastors and are made by a pastor. Pastors are staff accounts above these.
     */
    public const RANKS = ['member' => 'Member', 'worker' => 'Worker', 'minister' => 'Minister'];

    /** The classes each rank needs. */
    public const REQUIREMENTS = [
        'worker' => ['baptised', 'believers_class', 'workers_training'],
        'minister' => ['baptised', 'believers_class', 'workers_training', 'sod', 'bible_college'],
    ];

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const MILESTONES = [
        'baptised' => 'Baptismal class',
        'believers_class' => 'Believers\' class',
        'workers_training' => 'Workers\' in training class',
        'sod' => 'School of Disciples (SOD)',
        'bible_college' => 'Bible college',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'celebrate_birthday' => 'boolean',
            'birthday' => 'date',
            'anniversary' => 'date',
            'joined_on' => 'date',
            'last_attended_on' => 'date',
            'baptised' => 'boolean',
            'believers_class' => 'boolean',
            'workers_training' => 'boolean',
            'sod' => 'boolean',
            'bible_college' => 'boolean',
            'training_answered_at' => 'datetime',
            'notify_sms' => 'boolean',
            'rank_changed_at' => 'datetime',
        ];
    }

    /**
     * Everyone serving in a unit is a worker: joining a unit makes an approved member a worker,
     * and leaving their last unit makes a worker a member again (ministers keep their rank). A
     * new worker or minister is asked about their training. unit_id is their main unit, always
     * one of their units (see joinUnit, leaveUnit and syncUnits).
     */
    protected static function booted(): void
    {
        static::saving(function (Member $m) {
            if ($m->status === self::ACTIVE && ($m->isDirty('unit_id') || $m->isDirty('status'))) {
                if ($m->unit_id && ($m->rank ?? 'member') === 'member') {
                    $m->rank = 'worker';
                } elseif (! $m->unit_id && $m->rank === 'worker') {
                    $m->rank = 'member';
                }
            }
            if ($m->isDirty('rank')) {
                $m->rank_changed_at = now();
                $m->rank_changed_by = auth()->id() ?? $m->rank_changed_by;
                if ($m->rank !== 'member' && ! $m->isDirty('training_answered_at')) {
                    $m->training_answered_at = null;
                }
            }
        });
        // Setting a main unit directly (joining, importing) puts them in that unit too.
        static::saved(function (Member $m) {
            if ($m->unit_id && $m->wasChanged('unit_id') || ($m->wasRecentlyCreated && $m->unit_id)) {
                $m->units()->syncWithoutDetaching([$m->unit_id]);
            }
        });
    }

    /**
     * @return HasMany<UnitRequest, $this>
     */
    public function unitRequests(): HasMany
    {
        return $this->hasMany(UnitRequest::class);
    }

    /**
     * Every unit they serve in.
     *
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'member_unit')->withTimestamps()->orderBy('units.name');
    }

    public function inUnit(Unit|int $unit): bool
    {
        $id = $unit instanceof Unit ? $unit->id : $unit;

        return $this->relationLoaded('units') ? $this->units->contains('id', $id) : $this->units()->whereKey($id)->exists();
    }

    /**
     * @return array<int, int>
     */
    public function unitIds(): array
    {
        return $this->relationLoaded('units') ? $this->units->pluck('id')->all() : $this->units()->pluck('units.id')->all();
    }

    /**
     * "Choir, Ushering", or null when they're in no unit.
     */
    public function unitNames(): ?string
    {
        $names = $this->relationLoaded('units') ? $this->units->pluck('name') : $this->units()->pluck('name');

        return $names->isEmpty() ? null : $names->join(', ');
    }

    /**
     * Join a unit (and become a worker, if they were a member).
     */
    public function joinUnit(Unit $unit): void
    {
        $this->units()->syncWithoutDetaching([$unit->id]);
        $this->unit_id ??= $unit->id;
        $this->save();
        $this->unsetRelation('units');
        // Anything they'd asked for is answered: they're in.
        $this->unitRequests()->where('unit_id', $unit->id)->where('status', UnitRequest::PENDING)
            ->update(['status' => 'approved', 'decided_by' => auth()->id(), 'decided_at' => now()]);
    }

    /**
     * Leave a unit; leaving the last one makes a worker a member again.
     */
    public function leaveUnit(Unit $unit): void
    {
        $this->units()->detach($unit->id);
        $this->unsetRelation('units');
        if ($this->unit_id === $unit->id) {
            $this->unit_id = $this->units()->value('units.id');
        }
        $this->save();
    }

    /**
     * Set exactly which units they're in, keeping their main unit if it's still one of them.
     *
     * @param  array<int, int|string>  $ids
     */
    public function syncUnits(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        $this->units()->sync($ids);
        $this->unsetRelation('units');
        $this->unit_id = in_array($this->unit_id, $ids, true) ? $this->unit_id : ($ids[0] ?? null);
        $this->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Fellowship, $this>
     */
    public function fellowship(): BelongsTo
    {
        return $this->belongsTo(Fellowship::class);
    }

    /**
     * @return HasMany<Gift, $this>
     */
    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', self::ACTIVE);
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
        }
    }

    /**
     * Approve a member, with welcome tokens on their account. People who joined themselves
     * get a welcome follow-up; records the office adds or imports don't.
     */
    public function approve(bool $welcome = false): void
    {
        $wasPending = $this->status === self::PENDING;
        $this->forceFill([
            'status' => self::ACTIVE,
            'joined_on' => $this->joined_on ?? today(),
        ])->save();

        if ($this->user) {
            app(Rewards::class)->welcome($this->user);
        }
        if ($welcome && $wasPending) {
            app(FollowUps::class)->forNewMember($this);
        }
    }

    /**
     * Unit meetings they were at.
     *
     * @return BelongsToMany<UnitMeeting, $this>
     */
    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(UnitMeeting::class)->withPivot('mode', 'checked_in_at');
    }

    /**
     * @return HasMany<Duty, $this>
     */
    public function duties(): HasMany
    {
        return $this->hasMany(Duty::class);
    }

    /**
     * Units this member heads or assists.
     *
     * @return Collection<int, Unit>
     */
    public function ledUnits(): Collection
    {
        return Unit::where('hod_member_id', $this->id)->orWhere('assistant_member_id', $this->id)->orderBy('name')->get();
    }

    /**
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Of the last `$count` Sundays where attendance was taken, how many they were at.
     *
     * @return array{attended: int, of: int}
     */
    public function recentSundays(int $count = 8): array
    {
        $ids = Service::where('kind', 'sunday')->where('held_on', '<=', today()->endOfDay())
            ->whereHas('members')->orderByDesc('held_on')->take($count)->pluck('id');

        return ['attended' => $this->services()->whereIn('services.id', $ids)->count(), 'of' => $ids->count()];
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Waiting for approval',
            self::INACTIVE => 'Inactive',
            default => 'Member',
        };
    }

    /**
     * A wa.me link for their phone, in international format.
     */
    public function whatsappUrl(): ?string
    {
        $digits = static::internationalPhone($this->phone);

        return $digits ? 'https://wa.me/'.$digits : null;
    }

    /**
     * A phone number as digits with the country code (Nigerian 0… numbers become 234…), or null.
     */
    public static function internationalPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '234'.substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * Whether today is their birthday (29 February birthdays are kept on the 28th in other years).
     */
    public function hasBirthdayToday(): bool
    {
        if (! $this->birthday) {
            return false;
        }
        $day = $this->birthday->format('m-d');

        return $day === today()->format('m-d') || ($day === '02-29' && ! today()->isLeapYear() && today()->format('m-d') === '02-28');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Their first name, without a title such as Bro., Sis. or Pastor.
     */
    public function firstName(): string
    {
        return self::firstNameOf($this->name);
    }

    public static function firstNameOf(?string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim((string) $name)) ?: []));
        $titles = ['bro', 'brother', 'sis', 'sister', 'pst', 'pastor', 'dcn', 'dcns', 'deacon', 'deaconess', 'mr', 'mrs', 'ms', 'miss', 'dr', 'evang', 'elder', 'rev', 'prof', 'engr', 'barr', 'chief'];
        foreach ($words as $word) {
            if (! in_array(trim(mb_strtolower($word), '.()'), $titles, true)) {
                return $word;
            }
        }

        return $words[0] ?? (string) $name;
    }

    public function rankLabel(): string
    {
        return self::RANKS[$this->rank ?? 'member'] ?? 'Member';
    }

    /**
     * "Worker · Choir", "Minister", or "Member".
     */
    public function rankLine(): string
    {
        return match ($this->rank) {
            'worker' => 'Worker'.(($names = $this->unitNames()) ? ' · '.$names : ''),
            'minister' => 'Minister',
            default => 'Member',
        };
    }

    /**
     * The classes their rank needs, as field => label.
     *
     * @return array<string, string>
     */
    public function requirements(?string $rank = null): array
    {
        return array_intersect_key(self::MILESTONES, array_flip(self::REQUIREMENTS[$rank ?? $this->rank] ?? []));
    }

    /**
     * Required classes they haven't done, as field => label.
     *
     * @return array<string, string>
     */
    public function missingRequirements(?string $rank = null): array
    {
        return array_filter($this->requirements($rank), fn ($label, $field) => ! $this->{$field}, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * A worker or minister whose training hasn't been answered yet.
     */
    public function needsTrainingAnswers(): bool
    {
        return in_array($this->rank, ['worker', 'minister'], true) && ! $this->training_answered_at;
    }

    /**
     * Record which of their rank's classes are done.
     *
     * @param  array<string, mixed>  $answers  field => '1' (done) or '0' (not yet)
     */
    public function answerTraining(array $answers, ?string $rank = null): void
    {
        foreach (array_keys($this->requirements($rank)) as $field) {
            if (array_key_exists($field, $answers)) {
                $this->{$field} = (bool) $answers[$field];
            }
        }
        $this->training_answered_at = now();
    }
}
