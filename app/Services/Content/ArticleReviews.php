<?php

namespace App\Services\Content;

use App\Mail\ReminderMail;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WebPush;
use Illuminate\Support\Collection;

/**
 * Blog writers' articles: sent for approval, then approved (live) or sent back with a note.
 * Editors hear when one is waiting; the writer hears the answer.
 */
class ArticleReviews
{
    public function __construct(private WebPush $push, private Reminders $reminders) {}

    public function submit(Post $post, ?string $wantsLiveAt, bool $notify = true): void
    {
        $post->forceFill([
            'review' => 'pending',
            'review_note' => null,
            'submitted_at' => $notify ? now() : ($post->submitted_at ?? now()),
            'wants_live_at' => $wantsLiveAt,
            'published_at' => null,
        ])->save();

        if (! $notify) {
            return;
        }
        $when = $post->wants_live_at?->isFuture() ? ' They\'d like it live on '.$post->wants_live_at->format('j M, g:i A').'.' : '';
        foreach ($this->editors() as $editor) {
            $this->tell($editor, 'An article is waiting for approval', ($post->writer?->name ?? 'A blog writer').' sent "'.$post->title.'".'.$when, route('admin.posts.edit', $post), 'Read it');
        }
    }

    public function approve(Post $post, User $by): void
    {
        $at = $post->wants_live_at?->isFuture() ? $post->wants_live_at : now();
        $post->forceFill(['review' => null, 'review_note' => null, 'published_at' => $at, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        $this->tellApproved($post);
    }

    /**
     * Let the writer know their article is approved (approved here, or published from the editor).
     */
    public function tellApproved(Post $post): void
    {
        if (! $post->writer || ! $post->published_at) {
            return;
        }
        $at = $post->published_at;
        $body = '"'.$post->title.'" '.($at->isFuture() ? 'is approved and goes live on '.$at->format('j M, g:i A').'.' : 'is approved and live on the website.');
        $this->tell($post->writer, 'Your article is approved 🎉', $body, $at->isFuture() ? route('admin.posts.edit', $post) : $post->url(), 'See it');
    }

    public function sendBack(Post $post, User $by, string $note): void
    {
        $post->forceFill(['review' => 'changes', 'review_note' => $note, 'published_at' => null, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();

        if ($post->writer) {
            $this->tell($post->writer, 'Your article needs a few changes', '"'.$post->title.'": '.$note, route('admin.posts.edit', $post), 'Open it');
        }
    }

    /**
     * Who approves: the website managers and the media team, or the administrators if there are none.
     *
     * @return Collection<int, User>
     */
    public function editors(): Collection
    {
        $editors = User::whereIn('role', ['web', 'media'])->get();

        return $editors->isNotEmpty() ? $editors : User::whereIn('role', ['admin', 'super'])->get();
    }

    private function tell(User $user, string $title, string $body, string $url, string $button): void
    {
        if ($this->push->configured()) {
            $this->push->send(PushSubscription::where('user_id', $user->id)->get(), ['title' => $title, 'body' => mb_substr($body, 0, 180), 'url' => $url, 'tag' => 'article-review']);
        }
        if ($email = $user->email) {
            $this->reminders->mail($email, new ReminderMail($title, $body, $url, $button));
        }
    }
}
