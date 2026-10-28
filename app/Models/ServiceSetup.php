<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The media unit's plan for a service: the setup checklist, stream title and description, and
 * the YouTube video shown on the Watch page.
 */
#[Fillable(['service_id', 'stream_title', 'stream_description', 'youtube_video_id', 'notes'])]
class ServiceSetup extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'went_live_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<SetupTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(SetupTask::class)->orderBy('sort')->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->went_live_at !== null && $this->ended_at === null;
    }
}
