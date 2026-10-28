<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WeeklyNewsletter;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Services\Messaging\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The weekly email: who gets it, this week's preview, sending, and past issues.
 */
class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $show = in_array($request->query('show'), ['pending', 'unsubscribed'], true) ? $request->query('show') : 'receiving';

        return view('admin.subscribers', [
            'subscribers' => NewsletterSubscriber::query()
                ->when($show === 'receiving', fn ($q) => $q->receiving())
                ->when($show === 'pending', fn ($q) => $q->whereNull('confirmed_at')->whereNull('unsubscribed_at'))
                ->when($show === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
                ->when($search !== '', fn ($q) => $q->where('email', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
            'show' => $show,
            'counts' => [
                'receiving' => NewsletterSubscriber::receiving()->count(),
                'pending' => NewsletterSubscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(),
                'unsubscribed' => NewsletterSubscriber::whereNotNull('unsubscribed_at')->count(),
            ],
            'issues' => NewsletterIssue::with('sender')->latest('week_of')->take(8)->get(),
            'thisWeek' => NewsletterIssue::firstWhere('week_of', today()->startOfWeek()),
            'enabled' => (bool) config('church.newsletter.enabled'),
        ]);
    }

    /**
     * This week's email as subscribers will see it.
     */
    public function preview(Newsletter $newsletter): string
    {
        return (new WeeklyNewsletter($newsletter->content()))->render();
    }

    /**
     * Send this week's email now instead of waiting for Monday morning.
     */
    public function send(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $issue = $newsletter->queue($request->user());

        return back()->with('status', $issue
            ? 'Sending this week\'s email now. It goes out in batches over the next few minutes.'
            : 'This week\'s email has already been sent.');
    }

    /**
     * Download the people receiving the email as CSV.
     */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'subscribed_at', 'confirmed_at']);
            NewsletterSubscriber::receiving()->orderBy('id')->each(function (NewsletterSubscriber $s) use ($out) {
                // Spreadsheet apps run cells starting with these as formulas; prefix them so they stay text.
                $email = preg_match('/^[=+\-@]/', $s->email) ? "'".$s->email : $s->email;
                fputcsv($out, [$email, $s->created_at->toDateTimeString(), $s->confirmed_at->toDateTimeString()]);
            });
            fclose($out);
        }, 'subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Remove someone from the list entirely.
     */
    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('status', "{$subscriber->email} removed.");
    }
}
