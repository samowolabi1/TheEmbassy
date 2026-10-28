<?php

namespace App\Services\Messaging;

use App\Mail\WeeklyNewsletter;
use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\Event;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\Sermon;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The weekly email: what goes in it, queuing it once a week, and sending it in batches.
 */
class Newsletter
{
    /** Emails sent per scheduler run (every minute), to stay under mail providers' limits. */
    public const BATCH = 120;

    /**
     * The week's content, from what is already on the website.
     *
     * @return array<string, mixed>
     */
    public function content(?Carbon $weekOf = null): array
    {
        $weekOf ??= today()->startOfWeek();
        $since = $weekOf->copy()->subWeek();

        return [
            'subject' => config('church.short_name').': your week, '.$weekOf->format('j M'),
            'week_of' => $weekOf,
            'declaration' => Declaration::current(),
            'announcements' => Announcement::live()->where('published_at', '>=', $since->copy()->subWeek())->take(4)->get(),
            'sermons' => Sermon::where('is_published', true)->where('preached_on', '>=', $since)->orderByDesc('preached_on')->take(3)->get(),
            'posts' => Post::published()->where('published_at', '>=', $since)->take(3)->get(),
            'events' => Event::upcoming()->where('starts_at', '<=', $weekOf->copy()->addWeeks(3))->take(3)->get(),
        ];
    }

    /**
     * Queue this week's email unless it has already gone out. Returns null if it has.
     */
    public function queue(?User $by = null): ?NewsletterIssue
    {
        $weekOf = today()->startOfWeek();
        $issue = NewsletterIssue::firstWhere('week_of', $weekOf);
        if ($issue) {
            return null;
        }

        return NewsletterIssue::create([
            'week_of' => $weekOf,
            'subject' => $this->content($weekOf)['subject'],
            'status' => 'queued',
            'sent_by' => $by?->id,
        ]);
    }

    /**
     * Send the next batch of a queued issue. Subscribers already sent this issue are skipped,
     * so a crash or restart carries on where it stopped.
     */
    public function sendBatch(int $limit = self::BATCH): int
    {
        $issue = NewsletterIssue::whereIn('status', ['queued', 'sending'])->orderBy('id')->first();
        if (! $issue) {
            return 0;
        }
        if ($issue->status === 'queued') {
            $issue->update(['status' => 'sending', 'started_at' => now()]);
        }

        $content = $this->content($issue->week_of->copy());
        $content['subject'] = $issue->subject;
        $recipients = NewsletterSubscriber::receiving()
            ->where(fn ($q) => $q->whereNull('last_sent_at')->orWhere('last_sent_at', '<', $issue->started_at))
            ->orderBy('id')
            ->take($limit)
            ->get();

        $sent = $failed = 0;
        foreach ($recipients as $subscriber) {
            try {
                Mail::to($subscriber->email)->send(new WeeklyNewsletter($content, $subscriber));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Newsletter not sent', ['subscriber' => $subscriber->id, 'error' => $e->getMessage()]);
                $failed++;
            }
            // Marked either way, so one bad address can't stall the whole list.
            $subscriber->forceFill(['last_sent_at' => now()])->save();
        }

        $issue->increment('sent_count', $sent);
        $issue->increment('failed_count', $failed);
        if ($recipients->count() < $limit) {
            $issue->update(['status' => 'sent', 'finished_at' => now()]);
        }

        return $sent;
    }
}
