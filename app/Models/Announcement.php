<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tag', 'tone', 'title', 'body', 'url', 'published_at'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /** Tag colours an announcement can use. */
    public const TONES = ['wash' => 'Gold', 'ice' => 'Blue'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Announcements that are live on the website now.
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at');
    }

    /**
     * Draft, Scheduled or Live.
     */
    public function status(): string
    {
        return match (true) {
            $this->published_at === null => 'draft',
            $this->published_at->isFuture() => 'scheduled',
            default => 'live',
        };
    }
}
