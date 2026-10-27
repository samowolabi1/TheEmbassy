@extends('layouts.admin', ['title' => 'Events & tickets', 'active' => 'events'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Events &amp; tickets</h1>
            <span class="page-head__sub">Free RSVPs and paid tickets, with QR check-in at the door.</span>
        </div>
        <div class="actions">
            <form method="GET" action="{{ route('admin.tickets.lookup') }}" role="search" style="display: flex; gap: 8px">
                <label class="sr-only" for="ticket-code">Ticket code</label>
                <input id="ticket-code" type="text" name="code" placeholder="Ticket code" required
                    style="height: 46px; width: 160px; border-radius: 999px; border: 1px solid var(--admin-line); padding: 0 16px; font-size: 14px; text-transform: uppercase">
                <button type="submit" class="btn btn--ghost">Find</button>
            </form>
            <a href="{{ route('admin.events.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />New event</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Ticket income this month</span>
            <span class="stat-tile__num">₦{{ number_format($incomeThisMonth / 100) }}</span>
            <span class="stat-tile__foot">Paid through Paystack</span>
        </div>
    </div>

    <nav class="tabs" aria-label="Filter events">
        <a href="{{ route('admin.events.index') }}" class="tab" @unless ($past) aria-current="page" @endunless>Upcoming</a>
        <a href="{{ route('admin.events.index', ['show' => 'past']) }}" class="tab" @if ($past) aria-current="page" @endif>Past</a>
    </nav>

    <section class="box box--flush">
        @forelse ($events as $event)
            <div class="list-item">
                <span class="list-item__main">
                    <a href="{{ route('admin.events.show', $event) }}" class="list-item__title">{{ $event->title }}</a>
                    <span class="list-item__meta">{{ $event->starts_at->format('D j M Y, g:i A') }} · {{ $event->venue }} · {{ $event->booked_count }} booked</span>
                </span>
                @if ($event->is_featured)
                    <span class="pill pill--gold">Featured</span>
                @endif
                @unless ($event->is_published)
                    <span class="pill pill--draft">Hidden</span>
                @endunless
                <span class="list-item__actions">
                    <a href="{{ route('admin.events.show', $event) }}" class="btn btn--ghost btn--xs">Bookings<span class="sr-only"> for {{ $event->title }}</span></a>
                    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn--ghost btn--xs">Edit<span class="sr-only"> {{ $event->title }}</span></a>
                </span>
            </div>
        @empty
            <p class="empty-state">{{ $past ? 'No past events.' : 'No upcoming events.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $events])
    </section>
@endsection
