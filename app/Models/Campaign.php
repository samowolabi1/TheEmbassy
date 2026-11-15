<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message staff send to a group (members, sellers, staff or everyone) from the Message centre.
 */
class Campaign extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'sending' => 'Sending', 'sent' => 'Sent', 'cancelled' => 'Cancelled'];

    protected $fillable = ['subject', 'body', 'audience', 'channels', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'channels' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uses(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /**
     * How far through it is, 0–100.
     */
    public function progress(): int
    {
        if (! $this->recipients_count) {
            return $this->status === 'sent' ? 100 : 0;
        }

        return (int) min(100, round(100 * $this->recipients()->where('status', '!=', 'pending')->count() / $this->recipients_count));
    }
}
