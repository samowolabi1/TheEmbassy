@extends('layouts.admin', ['title' => 'Ticket '.$ticket->code, 'active' => 'events'])

@php($order = $ticket->order)

@section('content')
    <a href="{{ route('admin.events.show', $order->event) }}" class="box__link">← {{ $order->event->title }}</a>

    <section class="box" style="max-width: 560px; align-items: flex-start; gap: 16px">
        @if (! $order->isConfirmed())
            <p class="flash" style="background: var(--warn-bg); color: var(--warn)" role="alert">Not valid: this booking hasn't been paid.</p>
        @elseif (session('status'))
            {{-- The layout shows the "Checked in" message. --}}
        @elseif ($ticket->checked_in_at)
            <p class="flash" style="background: var(--warn-bg); color: var(--warn)" role="alert">Already checked in at {{ $ticket->checked_in_at->format('g:i A, D j M') }}.</p>
        @else
            <p class="flash" role="status">Valid ticket.</p>
        @endif

        <h1 style="margin: 0; font-family: var(--font-display); font-size: 30px; color: var(--navy)">{{ $order->name }}</h1>
        <dl style="margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 15px">
            <dt class="box__note">Ticket</dt><dd style="margin: 0; font-weight: 700">{{ $ticket->type->name }} · {{ $ticket->code }}</dd>
            <dt class="box__note">Event</dt><dd style="margin: 0; font-weight: 700">{{ $order->event->title }}, {{ $order->event->starts_at->format('D j M') }}</dd>
            <dt class="box__note">Booking</dt><dd style="margin: 0; font-weight: 700">{{ $order->code }} · {{ $order->tickets()->count() }} {{ \Illuminate\Support\Str::plural('ticket', $order->tickets()->count()) }}</dd>
            <dt class="box__note">Contact</dt><dd style="margin: 0; font-weight: 700; overflow-wrap: anywhere">{{ $order->email }}@if ($order->phone) · {{ $order->phone }}@endif</dd>
        </dl>

        @if ($order->isConfirmed() && ! $ticket->checked_in_at)
            <form method="POST" action="{{ route('admin.tickets.check-in', $ticket) }}">
                @csrf
                <button type="submit" class="btn btn--gold" style="height: 56px; font-size: 17px">Check in</button>
            </form>
        @endif
    </section>
@endsection
