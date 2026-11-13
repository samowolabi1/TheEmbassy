<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A church service and how many came, in person and online.
 */
#[Fillable(['held_on', 'starts_at', 'ends_at', 'kind', 'title', 'in_person', 'online', 'notes', 'in_person_checkin', 'online_checkin', 'join_url'])]
class Service extends Model
{
    use HasFactory;

    public const KINDS = [
        'sunday' => 'Sunday service',
        'midweek' => 'Midweek service',
        'special' => 'Special service',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'in_person_checkin' => 'boolean',
            'online_checkin' => 'boolean',
            'in_person' => 'integer',
            'online' => 'integer',
        ];
    }

    /**
     * Members marked present.
     *
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)->withPivot('mode', 'checked_in_at', 'distance_m');
    }

    /**
     * Members' feedback on this service.
     *
     * @return HasMany<ServiceFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(ServiceFeedback::class);
    }

    /**
     * The media unit's setup for this service, including its YouTube broadcast.
     *
     * @return HasOne<ServiceSetup, $this>
     */
    public function setup(): HasOne
    {
        return $this->hasOne(ServiceSetup::class);
    }

    /**
     * The YouTube video to play for this service: from its join link, its broadcast,
     * or the Watch page's live stream.
     */
    public function youtubeId(): ?string
    {
        if ($this->join_url && ($id = GalleryItem::youtubeId($this->join_url))) {
            return $id;
        }

        $id = $this->setup?->youtube_video_id ?: config('church.live.video_id');

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : null;
    }

    /**
     * A meeting link that isn't YouTube (Zoom, Google Meet, Teams…), opened in its own app.
     */
    public function meetingUrl(): ?string
    {
        return $this->join_url && ! GalleryItem::youtubeId($this->join_url) ? $this->join_url : null;
    }

    public function meetingName(): string
    {
        $host = strtolower((string) parse_url((string) $this->join_url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'zoom') => 'Zoom',
            str_contains($host, 'meet.google') => 'Google Meet',
            str_contains($host, 'teams') => 'Microsoft Teams',
            default => 'the meeting',
        };
    }

    /**
     * Services members can join online right now: online check-in is on and it's open.
     *
     * @return Collection<int, Service>
     */
    public static function joinableNow(): Collection
    {
        return static::openForCheckIn()->filter(fn (Service $s) => $s->online_checkin)->values();
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            $service->secret ??= Str::random(40);
        });
    }

    /**
     * When members can start checking themselves in.
     */
    public function checkInOpensAt(): ?Carbon
    {
        return $this->starts_at?->copy()->subMinutes((int) config('church.checkin.opens_before'));
    }

    public function checkInClosesAt(): ?Carbon
    {
        $end = $this->ends_at ?? $this->starts_at?->copy()->addHours(2);

        return $end?->copy()->addMinutes((int) config('church.checkin.closes_after'));
    }

    public function checkInIsOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        return ($this->in_person_checkin || $this->online_checkin)
            && $this->starts_at !== null
            && $at->between($this->checkInOpensAt(), $this->checkInClosesAt());
    }

    /**
     * The six-digit code on the check-in screen. It changes every minute, so a code passed on
     * by message is soon useless.
     */
    public function code(?Carbon $at = null): string
    {
        $step = intdiv(($at ?? now())->getTimestamp(), 60);

        return str_pad((string) (hexdec(substr(hash_hmac('sha256', (string) $step, (string) $this->secret), 0, 8)) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts this minute's code or one from the last `$minutes` minutes (a live stream runs behind).
     */
    public function acceptsCode(?string $code, int $minutes = 1): bool
    {
        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== 6) {
            return false;
        }
        for ($i = 0; $i <= $minutes; $i++) {
            if (hash_equals($this->code(now()->subMinutes($i)), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Services members can check in to right now.
     *
     * @return Collection<int, Service>
     */
    public static function openForCheckIn(): Collection
    {
        return static::query()
            ->where(fn ($q) => $q->where('in_person_checkin', true)->orWhere('online_checkin', true))
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now()->subHours(8), now()->addHours(4)])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Service $s) => $s->checkInIsOpen())
            ->values();
    }

    public function checkedInCount(string $mode): int
    {
        return $this->members()->wherePivot('mode', $mode)->count();
    }

    public function total(): int
    {
        return $this->in_person + $this->online;
    }

    public function label(): string
    {
        return $this->title ?: (self::KINDS[$this->kind] ?? 'Service');
    }

    /**
     * "In person", "Online" or "In person & online".
     */
    public function formatLabel(): string
    {
        return match (true) {
            $this->in_person_checkin && $this->online_checkin => 'In person & online',
            $this->online_checkin => 'Online',
            default => 'In person',
        };
    }
}
