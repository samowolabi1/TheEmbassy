@extends('layouts.app', ['title' => 'Your tickets'])

@php($event = $order->event)

@section('content')
    <section class="container notices-hero section--last" style="max-width: 860px">
        <div class="stack" style="gap: 16px">
            <span class="eyebrow">YOUR BOOKING · {{ $order->code }}</span>
            <h1 class="h2">{{ $event->title }}</h1>
            <p class="lede" style="margin: 0">{{ $event->starts_at->format('l j F, g:i A') }} · {{ $event->venue }}</p>

            @if (session('payment_error'))
                <p class="notice-alert" role="alert" style="margin: 0">{{ session('payment_error') }}</p>
            @endif

            @if ($order->isConfirmed())
                <p class="notice-alert notice-alert--ok" role="status" style="margin: 0">
                    You're booked. Show the QR code on each ticket at the door. We've also emailed this page to {{ $order->email }}.
                </p>
                <div class="grid-auto" style="--min: 240px; --gap: 16px">
                    @foreach ($order->tickets as $ticket)
                        <article class="card ticket">
                            <span class="ticket__type">{{ $ticket->type->name }}</span>
                            <span class="ticket__qr" role="img" aria-label="QR code for ticket {{ $ticket->code }}">{!! $ticket->qrSvg() !!}</span>
                            <span class="ticket__code">{{ $ticket->code }}</span>
                            <span class="field-hint">{{ $order->name }} · {{ $loop->iteration }} of {{ $order->tickets->count() }}</span>
                        </article>
                    @endforeach
                </div>
                <div class="slide__ctas" style="gap: 10px">
                    <a href="{{ route('events.calendar', $event) }}" class="btn btn--outline btn--md"><x-icon name="calendar" :size="18" />Add to calendar</a>
                    <button type="button" class="btn btn--outline btn--md" onclick="window.print()">Print tickets</button>
                </div>
            @elseif ($holdExpired)
                <p class="notice-alert" role="status" style="margin: 0">This booking wasn't paid in time, so the places were released.</p>
                <a href="{{ route('events.show', $event) }}#book" class="btn btn--gold btn--md" style="align-self: flex-start">Book again</a>
            @else
                <p class="notice-alert" role="status" style="margin: 0">
                    @if ($payment?->status === \App\Models\Payment::FAILED)
                        Your payment didn't go through. Your places are held for a few more minutes.
                    @else
                        We haven't received your payment yet. If you've just paid, give it a minute and refresh this page.
                    @endif
                </p>
                @if ($paymentsEnabled)
                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('tickets.pay', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn--gold btn--md">Pay ₦{{ number_format($order->total / 100) }}</button>
                    </form>
                @endif
            @endif
            <p class="field-hint" style="margin: 0">Keep this page's link: it's your way back to your tickets.</p>
        </div>
    </section>
@endsection
