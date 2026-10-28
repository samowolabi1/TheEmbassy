<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo (resized, with a thumbnail) or a YouTube video in an album.
 */
#[Fillable(['gallery_album_id', 'kind', 'path', 'thumb_path', 'width', 'height', 'youtube_id', 'caption', 'position'])]
class GalleryItem extends Model
{
    /**
     * @return BelongsTo<GalleryAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function url(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }

    public function thumbUrl(): string
    {
        if ($this->isVideo()) {
            return "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg";
        }

        return Storage::disk('public')->url($this->thumb_path ?? $this->path);
    }

    /**
     * Pull the video ID out of a YouTube link (watch, youtu.be, shorts, live or embed), or accept a bare ID.
     */
    public static function youtubeId(string $link): ?string
    {
        $link = trim($link);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $link)) {
            return $link;
        }
        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|live/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $link, $m)) {
            return $m[1];
        }

        return null;
    }
}
