<?php

namespace App\Models;

use App\Services\Care\FollowUps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone in the church family. They may have a login (user) or be on the register only.
 */
#[Fillable(['name', 'phone', 'email', 'gender', 'birthday', 'anniversary', 'address', 'unit_id', 'fellowship_id', 'token_plan_id', 'tokens_from', 'joined_on', 'baptised', 'believers_class', 'workers_training', 'notes', 'notify_sms'])]
class Member extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const MILESTONES = [
        'baptised' => 'Baptised',
        'believers_class' => 'Believers\' class',
        'workers_training' => 'Workers\' training',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'anniversary' => 'date',
            'joined_on' => 'date',
            'tokens_from' => 'date',
            'token_reminded_at' => 'datetime',
            'last_attended_on' => 'date',
            'baptised' => 'boolean',
            'believers_class' => 'boolean',
            'workers_training' => 'boolean',
            'notify_sms' => 'boolean',
        ];
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
     * @return BelongsTo<TokenPlan, $this>
     */
    public function tokenPlan(): BelongsTo
    {
        return $this->belongsTo(TokenPlan::class);
    }

    /**
     * @return HasMany<Gift, $this>
     */
    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }

    /**
     * @return HasMany<TokenPayment, $this>
     */
    public function tokenPayments(): HasMany
    {
        return $this->hasMany(TokenPayment::class);
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
     * Approve a member: they start owing tokens from this month on their plan. People who joined
     * themselves get a welcome follow-up; records the office adds or imports don't.
     */
    public function approve(bool $welcome = false): void
    {
        $wasPending = $this->status === self::PENDING;
        $this->forceFill([
            'status' => self::ACTIVE,
            'joined_on' => $this->joined_on ?? today(),
            'token_plan_id' => $this->token_plan_id ?? TokenPlan::default()?->id,
            'tokens_from' => $this->tokens_from ?? today()->startOfMonth(),
        ])->save();

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
     * The last month their tokens cover, or null if they've never paid.
     */
    public function tokensPaidThrough(): ?Carbon
    {
        $last = $this->tokenPayments->where('status', TokenPayment::PAID)
            ->map(fn (TokenPayment $p) => $p->lastMonth())
            ->sort()->last();

        return $last;
    }

    /**
     * Months owed up to and including this month. Exempt plans and inactive members owe nothing.
     */
    public function tokenMonthsOwed(): int
    {
        if ($this->status !== self::ACTIVE || ! $this->tokenPlan || $this->tokenPlan->isExempt() || ! $this->tokens_from) {
            return 0;
        }

        $from = $this->tokensPaidThrough()?->copy()->addMonthNoOverflow()->startOfMonth() ?? $this->tokens_from->copy()->startOfMonth();
        $now = today()->startOfMonth();

        return $from->gt($now) ? 0 : (int) $from->diffInMonths($now) + 1;
    }

    public function tokenAmountOwed(): int
    {
        return $this->tokenMonthsOwed() * (int) $this->tokenPlan?->amount;
    }

    /**
     * The next month to pay for.
     */
    public function nextTokenMonth(): Carbon
    {
        $through = $this->tokensPaidThrough();
        $start = $this->tokens_from?->copy()->startOfMonth() ?? today()->startOfMonth();

        return $through ? $through->copy()->addMonthNoOverflow()->startOfMonth() : $start;
    }

    /**
     * Up to date, behind, or exempt, for the members table.
     */
    public function tokenStatus(): string
    {
        return match (true) {
            $this->status !== self::ACTIVE || ! $this->tokenPlan => '—',
            $this->tokenPlan->isExempt() => 'Exempt',
            $this->tokenMonthsOwed() === 0 => 'Up to date',
            default => $this->tokenMonthsOwed().' '.str('month')->plural($this->tokenMonthsOwed()).' behind',
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
}
