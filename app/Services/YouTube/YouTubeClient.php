<?php

namespace App\Services\YouTube;

use App\Models\ServiceSetup;
use App\Models\StreamDestination;
use App\Models\YouTubeConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * The church's YouTube channel through Google sign-in (OAuth 2.0) and the YouTube Live Streaming API.
 *
 * Every broadcast is bound to one reusable stream, so the server and key in OBS or the encoder stay
 * the same week to week. Broadcasts start and stop themselves when the encoder starts and stops.
 */
class YouTubeClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/youtube';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/youtube/v3/';

    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('youtube.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline', // a refresh token, so it keeps working without signing in again
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Swap the code from Google's sign-in page for tokens, and find the channel they belong to.
     */
    public function connect(string $code): YouTubeConnection
    {
        $tokens = $this->check(Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => route('youtube.callback'),
            'grant_type' => 'authorization_code',
        ]));
        if (empty($tokens['refresh_token'])) {
            throw new YouTubeException('Google didn\'t give a lasting sign-in. Remove this site\'s access in your Google account settings, then connect again.');
        }

        $channel = $this->check(Http::withToken($tokens['access_token'])->get(self::API.'channels', ['part' => 'snippet', 'mine' => 'true']))['items'][0] ?? null;
        if (! $channel) {
            throw new YouTubeException('That Google account has no YouTube channel. Sign in with the account that owns the church\'s channel.');
        }

        YouTubeConnection::query()->delete(); // one channel per church

        return YouTubeConnection::create([
            'channel_id' => $channel['id'],
            'channel_title' => $channel['snippet']['title'] ?? 'YouTube channel',
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ]);
    }

    public function disconnect(YouTubeConnection $connection): void
    {
        // Tell Google to forget the sign-in too; if that fails, removing it here is still enough.
        rescue(fn () => Http::asForm()->post(self::REVOKE_URL, ['token' => $connection->refresh_token]), report: false);
        $connection->delete();
    }

    /**
     * The reusable stream all broadcasts go to. Its server and key are saved as a stream destination
     * so the media team can copy them into OBS once.
     *
     * @return array<string, mixed>
     */
    public function ensureStream(YouTubeConnection $connection): array
    {
        if ($connection->stream_id) {
            $stream = $this->api($connection)->get('liveStreams', ['part' => 'id,cdn', 'id' => $connection->stream_id]);
            $stream = $this->check($stream)['items'][0] ?? null;
        }

        if (empty($stream)) {
            $stream = $this->check($this->api($connection)->withQueryParameters(['part' => 'snippet,cdn,contentDetails'])->post('liveStreams', [
                'snippet' => ['title' => config('church.short_name').' main stream'],
                'cdn' => ['ingestionType' => 'rtmp', 'resolution' => 'variable', 'frameRate' => 'variable'],
                'contentDetails' => ['isReusable' => true],
            ]));
            $connection->update(['stream_id' => $stream['id']]);
        }

        $ingestion = $stream['cdn']['ingestionInfo'] ?? [];
        if (! empty($ingestion['ingestionAddress']) && ! empty($ingestion['streamName'])) {
            StreamDestination::updateOrCreate(
                ['platform' => 'youtube', 'label' => 'YouTube: '.$connection->channel_title],
                ['server_url' => $ingestion['ingestionAddress'], 'stream_key' => $ingestion['streamName'], 'is_active' => true],
            );
        }

        return $stream;
    }

    /**
     * Create the broadcast for a service and bind it to the reusable stream.
     */
    public function createBroadcast(YouTubeConnection $connection, ServiceSetup $setup): void
    {
        $stream = $this->ensureStream($connection);
        $service = $setup->service;
        $starts = $service->starts_at ?? $service->held_on->copy()->setTime(9, 0);

        $broadcast = $this->check($this->api($connection)->withQueryParameters(['part' => 'snippet,status,contentDetails'])->post('liveBroadcasts', [
            'snippet' => $this->snippet($setup, $starts),
            'status' => ['privacyStatus' => $this->privacy(), 'selfDeclaredMadeForKids' => false],
            'contentDetails' => ['enableAutoStart' => true, 'enableAutoStop' => true, 'enableDvr' => true, 'recordFromStart' => true],
        ]));

        $this->check($this->api($connection)->withQueryParameters(['part' => 'id,contentDetails', 'id' => $broadcast['id'], 'streamId' => $stream['id']])->post('liveBroadcasts/bind'));

        $setup->forceFill(['youtube_video_id' => $broadcast['id'], 'youtube_managed' => true, 'youtube_status' => $broadcast['status']['lifeCycleStatus'] ?? 'created', 'youtube_error' => null])->save();
    }

    /**
     * Send a changed title or description to YouTube.
     */
    public function updateBroadcast(YouTubeConnection $connection, ServiceSetup $setup): void
    {
        $service = $setup->service;
        $this->check($this->api($connection)->withQueryParameters(['part' => 'snippet'])->put('liveBroadcasts', [
            'id' => $setup->youtube_video_id,
            'snippet' => $this->snippet($setup, $service->starts_at ?? $service->held_on->copy()->setTime(9, 0)),
        ]));
    }

    public function deleteBroadcast(YouTubeConnection $connection, ServiceSetup $setup): void
    {
        $response = $this->api($connection)->delete('liveBroadcasts?'.http_build_query(['id' => $setup->youtube_video_id]));
        if ($response->status() !== 404) {
            $this->check($response);
        }
        $setup->forceFill(['youtube_video_id' => null, 'youtube_managed' => false, 'youtube_status' => null, 'youtube_error' => null])->save();
    }

    /**
     * Each broadcast's state: created, ready, testing, live, complete, revoked.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function statuses(YouTubeConnection $connection, array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $items = $this->check($this->api($connection)->get('liveBroadcasts', ['part' => 'id,status', 'id' => implode(',', $ids)]))['items'] ?? [];

        return collect($items)->mapWithKeys(fn ($b) => [$b['id'] => $b['status']['lifeCycleStatus'] ?? 'unknown'])->all();
    }

    /**
     * An API client with a fresh access token (refreshed when it's about to run out).
     */
    private function api(YouTubeConnection $connection): PendingRequest
    {
        if ($connection->expires_at->lt(now()->addMinute())) {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
            if ($response->json('error') === 'invalid_grant') {
                $connection->update(['problem' => 'Google ended the sign-in (it was removed, the password changed, or a "Testing" app\'s 7-day limit passed). Connect the channel again.']);

                throw new YouTubeException($connection->problem);
            }
            $tokens = $this->check($response);
            $connection->update(['access_token' => $tokens['access_token'], 'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)), 'problem' => null]);
        }

        return Http::baseUrl(self::API)->withToken($connection->access_token)->acceptJson()->asJson()->timeout(30);
    }

    /**
     * @return array<string, mixed>
     */
    private function snippet(ServiceSetup $setup, Carbon $starts): array
    {
        return [
            'title' => mb_substr($setup->stream_title ?: $setup->service->label().' · '.$starts->format('j M Y'), 0, 100),
            'description' => mb_substr((string) $setup->stream_description, 0, 5000),
            'scheduledStartTime' => $starts->copy()->utc()->toIso8601ZuluString(),
        ];
    }

    private function privacy(): string
    {
        return in_array(config('church.youtube.privacy'), ['public', 'unlisted'], true) ? config('church.youtube.privacy') : 'public';
    }

    /**
     * @return array<string, mixed>
     */
    private function check(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $reason = $response->json('error.errors.0.reason') ?? $response->json('error');
        $message = match (is_string($reason) ? $reason : null) {
            'liveStreamingNotEnabled' => 'Live streaming isn\'t turned on for this channel yet. Turn it on in YouTube Studio (it can take up to 24 hours), then try again.',
            'quotaExceeded', 'rateLimitExceeded' => 'YouTube\'s daily limit for this app has been reached. Try again tomorrow.',
            'insufficientPermissions', 'forbidden' => 'The connected account isn\'t allowed to do that on this channel. Connect with the channel owner\'s account.',
            'invalid_grant' => 'Google refused the sign-in. Connect the channel again.',
            'redirect_uri_mismatch' => 'Google doesn\'t recognise this site\'s address. Add '.route('youtube.callback').' as an authorised redirect URI in Google Cloud.',
            default => null,
        };

        throw new YouTubeException($message ?? ($response->json('error.message') ?? $response->json('error_description') ?? 'YouTube returned an error ('.$response->status().').'));
    }
}
