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
            'youtube_managed' => 'boolean',
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

    /**
     * The setup for a service, created the first time with the media unit's checklist.
     */
    public static function forService(Service $service): self
    {
        $setup = static::firstOrCreate(['service_id' => $service->id], ['stream_title' => $service->label().' · '.($service->starts_at ?? $service->held_on)->format('j M Y')]);
        if ($setup->wasRecentlyCreated) {
            $items = Unit::ofKind('media')?->checklistItems() ?? Unit::DEFAULT_CHECKLIST;
            foreach ($items as $i => $item) {
                $setup->tasks()->create(['title' => $item, 'sort' => $i]);
            }
        }

        return $setup->setRelation('service', $service);
    }

    /**
     * Show this stream in the Watch page player, marked as live. Only one service is live at a time.
     */
    public function goLive(): void
    {
        static::whereNotNull('went_live_at')->whereNull('ended_at')->whereKeyNot($this->id)->update(['ended_at' => now()]);
        $this->forceFill(['went_live_at' => $this->went_live_at ?? now(), 'ended_at' => null])->save();
        Setting::putMany(['church.live.video_id' => $this->youtube_video_id, 'church.live.is_live' => '1']);
    }

    /**
     * Drop the live badge; the video stays on the Watch page as the replay.
     */
    public function endLive(): void
    {
        $this->forceFill(['ended_at' => now()])->save();
        Setting::putMany(['church.live.is_live' => '0']);
    }
}
