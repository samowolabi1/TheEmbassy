<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A post the media unit writes once and publishes to the church's social accounts.
 * Facebook and Instagram are published by the app; other platforms get a share kit.
 */
#[Fillable(['caption', 'media_path', 'media_type', 'platforms', 'publish_at'])]
class SocialPost extends Model
{
    public const PLATFORMS = [
        'facebook' => 'Facebook Page',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'x' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp channel',
    ];

    // The app posts to these itself; the rest are shared by hand from the share kit.
    public const AUTOMATIC = ['facebook', 'instagram'];

    public const STATUSES = [
        'draft' => 'Draft',
        'awaiting_approval' => 'Waiting for the HOD',
        'scheduled' => 'Scheduled',
        'publishing' => 'Publishing',
        'published' => 'Published',
        'partly_published' => 'Partly published',
        'failed' => 'Failed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'results' => 'array',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk('public')->url($this->media_path) : null;
    }

    /**
     * @return list<string>
     */
    public function automaticPlatforms(): array
    {
        return array_values(array_intersect($this->platforms ?? [], self::AUTOMATIC));
    }

    /**
     * @return list<string>
     */
    public function manualPlatforms(): array
    {
        return array_values(array_diff($this->platforms ?? [], self::AUTOMATIC));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
