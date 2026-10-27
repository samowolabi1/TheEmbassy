@extends('layouts.app', [
    'title' => $event->title,
    'active' => 'events',
    'description' => $event->summary,
])

@php
    $types = $event->ticketTypes;
    $anyOnSale = $types->contains(fn ($t) => $t->onSale());
    $hasPaid = $types->contains(fn ($t) => ! $t->isFree());
@endphp

@section('content')
    <section class="featured-event bg-navy on-dark">
        <x-crown :width="560" :height="470" jewels class="crown-bg" style="right: -120px; bottom: -150px; opacity: 0.08" />
        <div class="container event-hero">
            <a href="{{ route('events') }}" class="back-link" style="color: var(--gold)"><x-icon name="chevron-left" :size="16" />All events</a>
            <span class="tag tag--gold">{{ $event->tag }}</span>
            <h1 class="display featured-event__title">{{ $event->title }}</h1>
            <p class="featured-event__text" style="color: var(--mist); font-weight: 500">{{ $event->summary }}</p>
            <dl class="event-facts">
                <div><dt>When</dt><dd>{{ $event->starts_at->format('l j F Y') }}<br>{{ $event->starts_at->format('g:i A') }}@if ($event->ends_at) to {{ $event->ends_at->format($event->ends_at->isSameDay($event->starts_at) ? 'g:i A' : 'D j M, g:i A') }}@endif</dd></div>
                <div><dt>Where</dt><dd>{{ $event->venue }}</dd></div>
                @if ($event->priceLabel())
                    <div><dt>Entry</dt><dd>{{ $event->priceLabel() }}</dd></div>
                @endif
            </dl>
            <a href="{{ route('events.calendar', $event) }}" class="btn btn--outline-light" style="align-self: flex-start"><x-icon name="calendar" :size="18" />Add to calendar</a>
        </div>
    </section>

    <section class="container section--tight section--last" id="book">
        @if (session('booking_error'))
            <p class="notice-alert" role="alert">{{ session('booking_error') }}</p>
        @endif

        @if ($types->isEmpty())
            <div class="card notices-empty"><p class="lede" style="margin: 0">No booking needed. Just come along.</p></div>
        @elseif ($event->hasStarted())
            <div class="card notices-empty"><p class="lede" style="margin: 0">This event has started, so booking is closed.</p></div>
        @else
            <form method="POST" action="{{ route('events.book', $event) }}" class="notice-form" data-booking>
                @csrf
                <div class="card notice-form__main">
                    <fieldset class="notice-form__group">
                        <legend>{{ $hasPaid ? 'Choose your tickets' : 'Book your place' }}</legend>
                        @error('quantities')
                            <p class="notice-alert" role="alert" style="margin: 0">{{ $message }}</p>
                        @enderror
                        @foreach ($types as $type)
                            @php($remaining = $type->remaining())
                            <div @class(['ticket-row', 'ticket-row--off' => ! $type->onSale()])>
                                <span class="ticket-row__info">
                                    <span class="package__label">{{ $type->name }}</span>
                                    <span class="field-hint">
                                        {{ $type->isFree() ? 'Free' : '₦'.number_format($type->price / 100) }}
                                        @if (! $type->onSale())
                                            · {{ $remaining === 0 ? 'Sold out' : 'Sales closed' }}
                                        @elseif ($remaining !== null && $remaining <= 20)
                                            · Only {{ $remaining }} left
                                        @endif
                                    </span>
                                </span>
                                @if ($type->onSale())
                                    <label class="ticket-row__qty">
                                        <span class="sr-only">How many {{ $type->name }}</span>
                                        <select name="quantities[{{ $type->id }}]" data-price="{{ $type->price }}">
                                            @foreach (range(0, min($type->max_per_order, $remaining ?? $type->max_per_order)) as $n)
                                                <option value="{{ $n }}" @selected((int) old("quantities.{$type->id}", $types->count() === 1 ? 1 : 0) === $n)>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </div>
                        @endforeach
                    </fieldset>
                </div>

                <aside class="card notice-form__side">
                    <fieldset class="notice-form__group">
                        <legend>Your details</legend>
                        <label class="field">Full name
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                            @error('name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Email (we send your tickets here)
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" @error('email') aria-invalid="true" @enderror>
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Phone (optional)
                            <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel">
                        </label>
                    </fieldset>
                    @if ($hasPaid && ! $paymentsEnabled)
                        <p class="field-hint" role="status">Paid tickets can't be bought online yet. Free tickets still work.</p>
                    @endif
                    <button type="submit" class="btn btn--gold" @disabled(! $anyOnSale)>
                        <span data-booking-label>{{ $hasPaid ? 'Continue' : 'Book my place' }}</span>
                    </button>
                    @if ($hasPaid)
                        <span class="field-hint" style="text-align: center">Paid tickets: secure payment by Paystack.</span>
                    @endif
                </aside>
            </form>
        @endif
    </section>
@endsection
