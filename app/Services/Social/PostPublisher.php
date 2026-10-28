<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Publishes approved posts when they're due, platform by platform, and records what happened.
 * Run every minute by the scheduler (`social:publish`); safe to run again, since platforms already
 * published are skipped.
 */
class PostPublisher
{
    public function __construct(private MetaPublisher $meta) {}

    /**
     * @return int how many posts were worked on
     */
    public function publishDue(): int
    {
        $posts = SocialPost::whereIn('status', ['scheduled', 'publishing'])
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->orderBy('publish_at')->take(20)->get();

        foreach ($posts as $post) {
            $this->publish($post);
        }

        return $posts->count();
    }

    public function publish(SocialPost $post): void
    {
        $results = $post->results ?? [];

        foreach ($post->automaticPlatforms() as $platform) {
            $previous = $results[$platform] ?? [];
            if (in_array($previous['status'] ?? null, ['published', 'failed'], true)) {
                continue;
            }
            if (! $this->meta->configured($platform)) {
                $results[$platform] = ['status' => 'failed', 'error' => 'Not connected. An administrator adds the Facebook Page details in Settings & roles.'];

                continue;
            }

            try {
                $results[$platform] = $platform === 'facebook' ? $this->meta->facebook($post) : $this->meta->instagram($post, $previous);
            } catch (RuntimeException $e) {
                Log::warning('Social post failed', ['post' => $post->id, 'platform' => $platform, 'error' => $e->getMessage()]);
                $results[$platform] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $states = collect($post->automaticPlatforms())->map(fn ($p) => $results[$p]['status'] ?? 'pending');
        $post->results = $results;
        $post->status = match (true) {
            $states->contains('processing') => 'publishing',
            $states->isEmpty() || $states->every(fn ($s) => $s === 'published') => 'published',
            $states->contains('published') => 'partly_published',
            default => 'failed',
        };
        if ($post->status !== 'publishing' && ! $post->published_at && $states->contains('published')) {
            $post->published_at = now();
        }
        $post->save();
    }
}
