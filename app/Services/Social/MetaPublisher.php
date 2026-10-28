<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posting to the church's Facebook Page and Instagram account through Meta's Graph API.
 *
 * Facebook: photos go to /{page}/photos, videos to /{page}/videos, text to /{page}/feed.
 * Instagram is two steps: create a media container, then publish it. Videos (Reels) need time
 * to process, so the container is checked again on later runs until it's ready.
 * Meta fetches the photo or video from its public URL, so the site must be on a public HTTPS address.
 */
class MetaPublisher
{
    public function configured(string $platform): bool
    {
        return filled(config('services.meta.page_token'))
            && filled(config($platform === 'instagram' ? 'services.meta.instagram_id' : 'services.meta.page_id'));
    }

    /**
     * @return array{status: string, id?: string, url?: string, error?: string}
     */
    public function facebook(SocialPost $post): array
    {
        $page = config('services.meta.page_id');
        $response = match ($post->media_type) {
            'image' => $this->graph()->post("{$page}/photos", ['url' => $post->mediaUrl(), 'caption' => $post->caption]),
            'video' => $this->graph()->post("{$page}/videos", ['file_url' => $post->mediaUrl(), 'description' => $post->caption]),
            default => $this->graph()->post("{$page}/feed", ['message' => $post->caption]),
        };
        $data = $this->json($response);
        $id = $data['post_id'] ?? $data['id'];

        return ['status' => 'published', 'id' => (string) $id, 'url' => 'https://www.facebook.com/'.$id];
    }

    /**
     * Start (or continue) publishing to Instagram. Returns `processing` while a video is being prepared.
     *
     * @param  array<string, mixed>  $previous  what happened on an earlier run
     * @return array{status: string, container?: string, id?: string, error?: string}
     */
    public function instagram(SocialPost $post, array $previous = []): array
    {
        $account = config('services.meta.instagram_id');

        if (! $post->media_path) {
            return ['status' => 'failed', 'error' => 'Instagram posts need a photo or video.'];
        }

        $container = $previous['container'] ?? null;
        if (! $container) {
            $params = $post->media_type === 'video'
                ? ['media_type' => 'REELS', 'video_url' => $post->mediaUrl(), 'caption' => $post->caption]
                : ['image_url' => $post->mediaUrl(), 'caption' => $post->caption];
            $container = (string) $this->json($this->graph()->post("{$account}/media", $params))['id'];
        }

        if ($post->media_type === 'video') {
            $state = $this->json($this->graph()->get($container, ['fields' => 'status_code']))['status_code'] ?? 'IN_PROGRESS';
            if (in_array($state, ['ERROR', 'EXPIRED'], true)) {
                return ['status' => 'failed', 'container' => $container, 'error' => "Instagram couldn't process the video ({$state})."];
            }
            if ($state !== 'FINISHED') {
                return ['status' => 'processing', 'container' => $container];
            }
        }

        $id = (string) $this->json($this->graph()->post("{$account}/media_publish", ['creation_id' => $container]))['id'];

        return ['status' => 'published', 'container' => $container, 'id' => $id];
    }

    private function graph(): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.config('services.meta.graph_version').'/')
            ->withToken((string) config('services.meta.page_token'))
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'Meta returned HTTP '.$response->status());
        }

        return $response->json();
    }
}
