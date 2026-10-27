<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    /** Ticket type rows shown on the event form. */
    private const TYPE_ROWS = 4;

    /**
     * Upcoming and past events with how many places are booked.
     */
    public function index(Request $request): View
    {
        $past = $request->query('show') === 'past';

        $events = Event::query()
            ->withCount(['tickets as booked_count' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', TicketOrder::CONFIRMED))])
            ->when($past, fn ($q) => $q->where('starts_at', '<', now()->startOfDay())->latest('starts_at'),
                fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', [
            'events' => $events,
            'past' => $past,
            'incomeThisMonth' => Payment::where('payable_type', (new TicketOrder)->getMorphClass())
                ->where('status', Payment::SUCCESS)
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount'),
        ]);
    }

    public function create(): View
    {
        return view('admin.events.form', ['event' => new Event(['is_published' => true]), 'rows' => self::TYPE_ROWS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $event = DB::transaction(function () use ($request) {
            [$data, $types] = $this->validated($request);
            $event = Event::create([...$data, 'slug' => $this->uniqueSlug($data['title'])]);
            $this->syncTypes($event, $types);

            return $event;
        });

        return redirect()->route('admin.events.show', $event)->with('status', "\"{$event->title}\" created.");
    }

    /**
     * Bookings, takings, check-ins and the attendee list for one event.
     */
    public function show(Request $request, Event $event): View
    {
        $event->load('ticketTypes');
        $confirmed = fn ($q) => $q->where('status', TicketOrder::CONFIRMED);
        $search = trim((string) $request->query('q'));

        return view('admin.events.show', [
            'event' => $event,
            'byType' => $event->ticketTypes->map(fn ($type) => [
                'type' => $type,
                'booked' => $type->tickets()->whereHas('order', $confirmed)->count(),
            ]),
            'checkedIn' => $event->tickets()->whereHas('order', $confirmed)->whereNotNull('checked_in_at')->count(),
            'takings' => $event->orders()->where('status', TicketOrder::CONFIRMED)->sum('total'),
            'tickets' => Ticket::query()
                ->with(['order', 'type'])
                ->whereHas('order', fn ($q) => $q->where('event_id', $event->id)->where('status', TicketOrder::CONFIRMED)
                    ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function edit(Event $event): View
    {
        return view('admin.events.form', ['event' => $event->load('ticketTypes'), 'rows' => max(self::TYPE_ROWS, $event->ticketTypes->count() + 1)]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        DB::transaction(function () use ($request, $event) {
            [$data, $types] = $this->validated($request, $event);
            $event->update($data);
            $this->syncTypes($event, $types);
        });

        return redirect()->route('admin.events.show', $event)->with('status', 'Event saved.');
    }

    /**
     * Delete an event nobody has booked. Booked events can be unpublished instead.
     */
    public function destroy(Event $event): RedirectResponse
    {
        if ($event->hasSales()) {
            return back()->with('status', 'This event has bookings, so it can\'t be deleted. Untick "Show on the website" to hide it.');
        }

        $event->delete();

        return redirect()->route('admin.events.index')->with('status', "\"{$event->title}\" deleted.");
    }

    /**
     * The attendee list as CSV, e.g. for printing a door list.
     */
    public function export(Event $event): StreamedResponse
    {
        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ticket', 'type', 'name', 'email', 'phone', 'booking', 'checked_in']);
            $safe = fn (?string $v) => $v !== null && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v;
            Ticket::with(['order', 'type'])
                ->whereHas('order', fn ($q) => $q->where('event_id', $event->id)->where('status', TicketOrder::CONFIRMED))
                ->orderBy('id')
                ->each(function (Ticket $t) use ($out, $safe) {
                    fputcsv($out, [$t->code, $t->type->name, $safe($t->order->name), $safe($t->order->email), $safe($t->order->phone),
                        $t->order->code, $t->checked_in_at?->toDateTimeString()]);
                });
            fclose($out);
        }, Str::slug($event->title).'-attendees.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'tag' => ['required', 'string', 'max:40'],
            'summary' => ['required', 'string', 'max:1000'],
            'venue' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'types' => ['array', 'max:20'],
            'types.*.id' => ['nullable', 'integer'],
            'types.*.name' => ['nullable', 'string', 'max:60'],
            'types.*.price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'types.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'types.*.max_per_order' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $types = collect($data['types'] ?? [])->values()->map(fn ($t, $i) => [
            'id' => isset($t['id']) ? (int) $t['id'] : null,
            'name' => trim((string) ($t['name'] ?? '')),
            'price' => (int) round(((float) ($t['price'] ?? 0)) * 100),
            'quantity' => isset($t['quantity']) && $t['quantity'] !== '' ? (int) $t['quantity'] : null,
            'max_per_order' => (int) ($t['max_per_order'] ?? 10) ?: 10,
            'sort' => $i,
        ]);

        // A cleared-out row for a type people have already booked can't be removed.
        if ($event) {
            $kept = $types->where('name', '!==', '')->pluck('id')->filter();
            $removedWithSales = $event->ticketTypes()->whereNotIn('id', $kept)->whereHas('tickets')->pluck('name');
            if ($removedWithSales->isNotEmpty()) {
                throw ValidationException::withMessages(['types' => 'People have booked '.$removedWithSales->join(', ').', so it can\'t be removed. Set its number of places to stop sales instead.']);
            }
        }

        $tz = config('app.timezone');

        return [[
            'title' => $data['title'],
            'tag' => $data['tag'],
            'summary' => $data['summary'],
            'venue' => $data['venue'],
            'starts_at' => Carbon::parse($data['starts_at'], $tz),
            'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at'], $tz) : null,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
        ], $types->where('name', '!==', '')->values()->all()];
    }

    /**
     * @param  list<array<string, mixed>>  $types
     */
    private function syncTypes(Event $event, array $types): void
    {
        $keep = [];
        foreach ($types as $t) {
            $attributes = collect($t)->except('id')->all();
            $type = $t['id'] ? $event->ticketTypes()->whereKey($t['id'])->first() : null;
            if ($type) {
                $type->update($attributes);
            } else {
                $type = $event->ticketTypes()->create($attributes);
            }
            $keep[] = $type->id;
        }
        $event->ticketTypes()->whereNotIn('id', $keep)->whereDoesntHave('tickets')->delete();

        // Only one event is featured at a time.
        if ($event->is_featured) {
            Event::whereKeyNot($event->id)->update(['is_featured' => false]);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        for ($n = 2; Event::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
