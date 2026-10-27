@extends('layouts.admin', ['title' => $event->exists ? 'Edit event' : 'New event', 'active' => 'events'])

@php
    $types = $event->ticketTypes ?? collect();
    $dt = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ $event->exists ? route('admin.events.show', $event) : route('admin.events.index') }}" class="box__link">← {{ $event->exists ? $event->title : 'Events & tickets' }}</a>
            <h1>{{ $event->exists ? 'Edit event' : 'New event' }}</h1>
        </div>
    </header>

    <form method="POST" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}" class="row">
        @csrf
        @if ($event->exists) @method('PUT') @endif

        <section class="box grow form">
            <h2>Details</h2>
            <label class="field">Title
                <input type="text" name="title" value="{{ old('title', $event->title) }}" required maxlength="120" @error('title') aria-invalid="true" @enderror>
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="form-row">
                <label class="field">Label
                    <input type="text" name="tag" value="{{ old('tag', $event->tag) }}" required maxlength="40" placeholder="e.g. Worship, Convention">
                    @error('tag') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Venue
                    <input type="text" name="venue" value="{{ old('venue', $event->venue) }}" required maxlength="160">
                    @error('venue') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <div class="form-row">
                <label class="field">Starts
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $dt($event->starts_at)) }}" required>
                    @error('starts_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Ends (optional)
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $dt($event->ends_at)) }}">
                    @error('ends_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <label class="field">Description
                <textarea name="summary" required maxlength="1000">{{ old('summary', $event->summary) }}</textarea>
                @error('summary') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="login__remember"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $event->is_published))>Show on the website</label>
            <label class="login__remember"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $event->is_featured))>Feature it at the top of the Events page (replaces the current featured event)</label>
        </section>

        <section class="box side-col form">
            <h2>Tickets</h2>
            <span class="box__note">Leave the price at 0 for a free RSVP. Leave places empty for no limit. Clear a name to remove a ticket type nobody has booked.</span>
            @error('types') <span class="field-error">{{ $message }}</span> @enderror
            @foreach (range(0, $rows - 1) as $i)
                @php($type = $types[$i] ?? null)
                <fieldset class="choice-group" style="padding: 14px; border: 1px solid var(--admin-line); border-radius: 16px; gap: 10px">
                    <legend style="margin: 0; padding: 0 6px">Ticket type {{ $i + 1 }}</legend>
                    @if ($type) <input type="hidden" name="types[{{ $i }}][id]" value="{{ $type->id }}"> @endif
                    <label class="field">Name
                        <input type="text" name="types[{{ $i }}][name]" value="{{ old("types.$i.name", $type?->name ?? ($i === 0 && ! $event->exists ? 'Free entry' : '')) }}" maxlength="60" placeholder="e.g. Regular, VIP">
                    </label>
                    <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 90px), 1fr))">
                        <label class="field">Price (₦)
                            <input type="number" name="types[{{ $i }}][price]" value="{{ old("types.$i.price", $type ? $type->price / 100 : 0) }}" min="0" step="1">
                        </label>
                        <label class="field">Places
                            <input type="number" name="types[{{ $i }}][quantity]" value="{{ old("types.$i.quantity", $type?->quantity) }}" min="1" placeholder="No limit">
                        </label>
                        <label class="field">Per order
                            <input type="number" name="types[{{ $i }}][max_per_order]" value="{{ old("types.$i.max_per_order", $type?->max_per_order ?? 10) }}" min="1" max="50">
                        </label>
                    </div>
                </fieldset>
            @endforeach
            <button type="submit" class="btn btn--gold">{{ $event->exists ? 'Save event' : 'Create event' }}</button>
        </section>
    </form>

    @if ($event->exists && ! $event->hasSales())
        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" data-confirm="Delete this event?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--danger btn--xs">Delete event</button>
        </form>
    @endif
@endsection
