@extends('layouts.admin', ['title' => $event->title, 'active' => 'events'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.events.index') }}" class="box__link">← Events &amp; tickets</a>
            <h1>{{ $event->title }}</h1>
            <span class="page-head__sub">{{ $event->starts_at->format('l j F Y, g:i A') }} · {{ $event->venue }}</span>
        </div>
        <div class="actions">
            <a href="{{ route('events.show', $event) }}" class="btn btn--outline">View page</a>
            <a href="{{ route('admin.events.export', $event) }}" class="btn btn--outline">Download attendees</a>
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn--gold">Edit event</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Booked</span>
            <span class="stat-tile__num">{{ number_format($byType->sum('booked')) }}</span>
            <span class="stat-tile__foot">{{ $checkedIn }} checked in</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Ticket takings</span>
            <span class="stat-tile__num">₦{{ number_format($takings / 100) }}</span>
            <span class="stat-tile__foot">Confirmed bookings</span>
        </div>
        @foreach ($byType as $row)
            <div class="stat-tile">
                <span class="stat-tile__label">{{ $row['type']->name }}</span>
                <span class="stat-tile__num">{{ $row['booked'] }}@if ($row['type']->quantity)<span style="font-size: 18px; color: var(--slate)"> / {{ $row['type']->quantity }}</span>@endif</span>
                <span class="stat-tile__foot">{{ $row['type']->isFree() ? 'Free' : '₦'.number_format($row['type']->price / 100).' each' }}</span>
            </div>
        @endforeach
    </div>

    <section class="box box--flush">
        <form method="GET" class="box__head" role="search">
            <h2>Attendees</h2>
            <span style="display: flex; gap: 8px">
                <label class="sr-only" for="attendee-search">Search attendees</label>
                <input id="attendee-search" type="search" name="q" value="{{ $search }}" placeholder="Name or email"
                    style="height: 40px; border-radius: 999px; border: 1px solid var(--admin-line); padding: 0 14px; font-size: 14px">
                <button type="submit" class="btn btn--ghost btn--xs" style="height: 40px">Search</button>
            </span>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>NAME</th><th>TICKET</th><th>CODE</th><th>STATUS</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><strong>{{ $ticket->order->name }}</strong><br><span class="box__note">{{ $ticket->order->email }}</span></td>
                            <td>{{ $ticket->type->name }}</td>
                            <td style="font-family: ui-monospace, monospace">{{ $ticket->code }}</td>
                            <td>
                                @if ($ticket->checked_in_at)
                                    <span class="pill pill--ok">In · {{ $ticket->checked_in_at->format('g:i A') }}</span>
                                @else
                                    <span class="pill pill--info">Booked</span>
                                @endif
                            </td>
                            <td class="num">
                                @unless ($ticket->checked_in_at)
                                    <form method="POST" action="{{ route('admin.tickets.check-in', $ticket) }}">
                                        @csrf
                                        <button type="submit" class="btn btn--navy btn--xs">Check in<span class="sr-only"> {{ $ticket->order->name }}</span></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="empty-state">{{ $search ? 'Nobody matches that search.' : 'No bookings yet.' }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $tickets])
    </section>
@endsection
