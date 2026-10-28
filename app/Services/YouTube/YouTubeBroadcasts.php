<?php

namespace App\Services\YouTube;

use App\Models\Service;
use App\Models\ServiceSetup;
use App\Models\YouTubeConnection;
use Illuminate\Support\Facades\Log;

/**
 * Keeping YouTube and the website in step without anyone pressing buttons:
 * broadcasts are created a few days before each streamed service, and while a service is on,
 * the Watch page goes live when YouTube does and back to a replay when it ends.
 * Runs every minute (`youtube:sync`). A broadcast that failed to create isn't retried until
 * someone tries again by hand, so a problem doesn't use up YouTube's daily limit.
 */
class YouTubeBroadcasts
{
    public function __construct(private YouTubeClient $youtube) {}

    /**
     * @return array{created: int, synced: int, errors: int}
     */
    public function run(): array
    {
        $result = ['created' => 0, 'synced' => 0, 'errors' => 0];
        $connection = YouTubeConnection::current();
        if (! $connection || ! $connection->isWorking() || ! $this->youtube->configured()) {
            return $result;
        }

        try {
            $result['created'] = $this->createUpcoming($connection, $result['errors']);
            $result['synced'] = $this->syncLive($connection);
        } catch (YouTubeException $e) {
            Log::warning('YouTube sync stopped', ['error' => $e->getMessage()]);
            $result['errors']++;
        }

        return $result;
    }

    private function createUpcoming(YouTubeConnection $connection, int &$errors): int
    {
        $days = (int) config('church.youtube.days_ahead');
        if ($days <= 0) {
            return 0;
        }

        $created = 0;
        $services = Service::where('online_checkin', true)->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addDays($days)])->orderBy('starts_at')->get();

        foreach ($services as $service) {
            $setup = ServiceSetup::forService($service);
            if ($setup->youtube_video_id || $setup->youtube_error) {
                continue;
            }
            try {
                $this->youtube->createBroadcast($connection, $setup);
                $created++;
            } catch (YouTubeException $e) {
                $setup->forceFill(['youtube_error' => $e->getMessage()])->save();
                $errors++;
                if ($connection->fresh()?->problem) {
                    break; // the sign-in itself has stopped working
                }
            }
        }

        return $created;
    }

    /**
     * Follow YouTube's state for broadcasts around now, and switch the Watch page to match.
     */
    private function syncLive(YouTubeConnection $connection): int
    {
        $setups = ServiceSetup::with('service')->where('youtube_managed', true)
            ->whereNotIn('youtube_status', ['complete', 'revoked'])
            ->whereHas('service', fn ($q) => $q->whereBetween('starts_at', [now()->subHours(6), now()->addHour()]))
            ->get();
        if ($setups->isEmpty()) {
            return 0;
        }

        $statuses = $this->youtube->statuses($connection, $setups->pluck('youtube_video_id')->all());
        foreach ($setups as $setup) {
            $status = $statuses[$setup->youtube_video_id] ?? null;
            if (! $status) {
                continue;
            }
            $setup->forceFill(['youtube_status' => $status])->save();

            if ($status === 'live' && ! $setup->isLive()) {
                $setup->goLive();
            } elseif (in_array($status, ['complete', 'revoked'], true) && $setup->isLive()) {
                $setup->endLive();
            }
        }

        return $setups->count();
    }
}
