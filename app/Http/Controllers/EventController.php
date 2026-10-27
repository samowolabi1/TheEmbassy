<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Payments\Payments;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * The Events page: the featured event, upcoming events by month, and regular gatherings.
     */
    public function index(): View
    {
        $events = Event::upcoming()->with('ticketTypes')->get();
        $featured = $events->firstWhere('is_featured', true) ?? $events->first();

        return view('pages.events', [
            'featured' => $featured,
            'daysUntil' => $featured ? max(0, (int) ceil(now()->diffInSeconds($featured->starts_at, false) / 86400)) : null,
            // Month tabs from the events themselves, e.g. ['2026-11' => 'November'].
            'months' => $events->mapWithKeys(fn (Event $e) => [$e->starts_at->format('Y-m') => $e->starts_at->format('F')]),
            'events' => $events,
        ]);
    }

    /**
     * One event, with its ticket types and the booking form.
     */
    public function show(Event $event, Payments $payments): View
    {
        abort_unless($event->is_published, 404);
        $event->load('ticketTypes');

        return view('events.show', [
            'event' => $event,
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    /**
     * An .ics file so people can add the event to their phone's calendar.
     */
    public function calendar(Event $event): Response
    {
        abort_unless($event->is_published, 404);

        $format = fn (Carbon $t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $escape = fn (string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);
        $ends = $event->ends_at ?? $event->starts_at->copy()->addHours(2);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$escape(config('church.short_name')).'//Events//EN',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$format(now()),
            'DTSTART:'.$format($event->starts_at),
            'DTEND:'.$format($ends),
            'SUMMARY:'.$escape($event->title),
            'LOCATION:'.$escape($event->venue),
            'DESCRIPTION:'.$escape($event->summary.' '.route('events.show', $event)),
            'URL:'.route('events.show', $event),
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($event->title).'.ics"',
        ]);
    }
}
