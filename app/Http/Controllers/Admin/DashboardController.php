<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\Event;
use App\Models\NewsletterSubscriber;
use App\Models\Notice;
use App\Models\PrayerRequest;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Overview of what's come in and what needs attention.
     */
    public function __invoke(Request $request): View
    {
        $hour = now()->hour;

        return view('admin.dashboard', [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'name' => $request->user()->name,
            'stats' => [
                'unreadPrayer' => PrayerRequest::unread()->count(),
                'prayerThisMonth' => PrayerRequest::where('created_at', '>=', now()->startOfMonth())->count(),
                'subscribers' => NewsletterSubscriber::count(),
                'subscribersThisMonth' => NewsletterSubscriber::where('created_at', '>=', now()->startOfMonth())->count(),
                'liveAnnouncements' => Announcement::live()->count(),
                'scheduledAnnouncements' => Announcement::where('published_at', '>', now())->count(),
                'noticesToReview' => Notice::where('status', Notice::IN_REVIEW)->count(),
            ],
            'hasDeclarationThisWeek' => Declaration::where('week_of', Declaration::weekStarting(today()))->exists(),
            'hasDeclarationNextWeek' => Declaration::where('week_of', Declaration::weekStarting(today()->addWeek()))->exists(),
            'latestPrayer' => PrayerRequest::latest()->take(3)->get(),
            'latestSubscribers' => NewsletterSubscriber::latest()->take(5)->get(),
            'events' => Event::upcoming()->withCount(['tickets as booked_count' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', TicketOrder::CONFIRMED))])->take(3)->get(),
        ]);
    }
}
