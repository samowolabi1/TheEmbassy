<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What the live studio is showing over the stream. There's one, made on first use; OBS shows it
 * through the overlay link, which only works with its secret token.
 */
class StudioScreen extends Model
{
    public const SCENES = [
        'starting' => 'Starting soon',
        'brb' => 'Be right back',
        'ending' => 'Thanks for watching',
        'message' => 'Message',
    ];

    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lower_third' => 'array',
            'scripture' => 'array',
            'show_logo' => 'boolean',
            'countdown_to' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create(['token' => Str::random(48)]);
    }

    /**
     * Change what's showing; the overlay notices the new version within a second.
     *
     * @param  array<string, mixed>  $changes
     */
    public function change(array $changes): void
    {
        $this->forceFill([...$changes, 'version' => $this->version + 1])->save();
    }

    /**
     * What the overlay needs, and nothing else.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return [
            'version' => $this->version,
            'scene' => $this->scene,
            'sceneTitle' => $this->scene ? self::SCENES[$this->scene] ?? null : null,
            'sceneText' => $this->scene_text,
            'countdownTo' => $this->countdown_to?->toIso8601String(),
            'lowerThird' => $this->lower_third,
            'scripture' => $this->scripture,
            'ticker' => $this->ticker,
            'logo' => $this->show_logo,
        ];
    }
}
