@extends('layouts.unit', ['title' => 'Messages', 'active' => 'messages'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Messages</h1>
            <span class="page-head__sub">Emailed to every member of {{ $unit->name }} and shown on their account page</span>
        </div>
    </header>

    <section class="box">
        <h2>New message</h2>
        <form method="POST" action="{{ route('unit.messages.send', $unit) }}" class="form">
            @csrf
            <label class="field">Subject <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="120">@error('subject') <span class="field-error">{{ $message }}</span> @enderror</label>
            <label class="field">Message <textarea name="body" required maxlength="5000" style="height: 140px">{{ old('body') }}</textarea>@error('body') <span class="field-error">{{ $message }}</span> @enderror</label>
            <div><button type="submit" class="btn btn--navy">Send to the unit</button></div>
        </form>
    </section>

    <section class="box box--flush">
        <div class="box__head"><h2>Sent</h2></div>
        @forelse ($messages as $m)
            <article class="prayer-card">
                <div class="prayer-card__head"><span class="prayer-card__who">{{ $m->subject }}</span><span class="prayer-card__when">{{ $m->created_at->format('j M, g:i A') }}</span></div>
                <p class="prayer-card__text" style="white-space: pre-line">{{ $m->body }}</p>
                <span class="prayer-card__contact">From {{ $m->sender?->name ?? 'a leader' }} · emailed to {{ $m->emailed }}</span>
            </article>
        @empty
            <p class="empty-state">No messages yet.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $messages])
    </section>
@endsection
