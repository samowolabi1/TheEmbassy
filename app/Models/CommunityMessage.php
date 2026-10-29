<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * A message in a community room: text, a photo, or a poll, optionally replying to another.
 * Removed messages stay as "message removed" so replies still make sense.
 */
#[Fillable(['community_room_id', 'user_id', 'reply_to_id', 'body', 'image_path', 'image_thumb_path', 'poll', 'edited_at', 'removed_at', 'removed_by'])]
class CommunityMessage extends Model
{
    /** Reactions people can add. */
    public const REACTIONS = ['🙏', '❤️', '😂', '🔥', '👏', '🙌'];

    /** Writers can edit their message for this long. */
    public const EDIT_MINUTES = 15;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'poll' => 'array',
            'edited_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommunityRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(CommunityRoom::class, 'community_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CommunityMessage, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<CommunityReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityReaction::class);
    }

    /**
     * @return HasMany<CommunityVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(CommunityVote::class);
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function canEdit(User $user): bool
    {
        return ! $this->isRemoved() && $this->user_id === $user->id && $this->poll === null
            && $this->created_at->gt(now()->subMinutes(self::EDIT_MINUTES)) && ! $user->isMutedInCommunity();
    }

    public function canRemove(User $user): bool
    {
        return ! $this->isRemoved() && ($this->user_id === $user->id || $user->moderatesCommunity());
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->image_thumb_path ? Storage::disk('public')->url($this->image_thumb_path) : $this->imageUrl();
    }

    /**
     * The text, escaped, with links made clickable and line breaks kept.
     */
    public function bodyHtml(): HtmlString
    {
        $text = e((string) $this->body);
        $text = preg_replace_callback('~\bhttps?://[^\s<>"\']+~i', function ($m) {
            $url = rtrim($m[0], '.,!?;:)');
            $tail = substr($m[0], strlen($url));

            return '<a href="'.$url.'" target="_blank" rel="noopener nofollow ugc">'.$url.'</a>'.$tail;
        }, $text);

        return new HtmlString(nl2br($text, false));
    }

    public function pollClosed(): bool
    {
        return $this->poll !== null && isset($this->poll['closes_at']) && now()->gte($this->poll['closes_at']);
    }

    /**
     * Votes per option, in option order.
     *
     * @return list<int>
     */
    public function pollCounts(): array
    {
        $counts = array_fill(0, count($this->poll['options'] ?? []), 0);
        foreach ($this->votes as $vote) {
            if (isset($counts[$vote->option])) {
                $counts[$vote->option]++;
            }
        }

        return $counts;
    }

    /**
     * Reactions grouped by emoji: count and whether this person added one.
     *
     * @return array<string, array{count: int, mine: bool, names: string}>
     */
    public function reactionSummary(User $viewer): array
    {
        return $this->reactions->groupBy('emoji')->map(fn ($group) => [
            'count' => $group->count(),
            'mine' => $group->contains('user_id', $viewer->id),
            'names' => $group->take(8)->map(fn ($r) => $r->user?->communityName())->filter()->join(', '),
        ])->sortBy(fn ($v, $emoji) => array_search($emoji, self::REACTIONS, true))->all();
    }
}
