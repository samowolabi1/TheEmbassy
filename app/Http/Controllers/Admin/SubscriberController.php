<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    /**
     * Everyone signed up for the weekly declaration email.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('admin.subscribers', [
            'subscribers' => NewsletterSubscriber::query()
                ->when($search !== '', fn ($q) => $q->where('email', 'like', '%'.$search.'%'))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
            'total' => NewsletterSubscriber::count(),
        ]);
    }

    /**
     * Download the list as CSV, e.g. to import into an email service.
     */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'subscribed_at']);
            NewsletterSubscriber::orderBy('id')->each(function (NewsletterSubscriber $s) use ($out) {
                // Spreadsheet apps run cells starting with these as formulas; prefix them so they stay text.
                $email = preg_match('/^[=+\-@]/', $s->email) ? "'".$s->email : $s->email;
                fputcsv($out, [$email, $s->created_at->toDateTimeString()]);
            });
            fclose($out);
        }, 'subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Remove someone from the list, e.g. when they ask to unsubscribe.
     */
    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('status', "{$subscriber->email} removed.");
    }
}
