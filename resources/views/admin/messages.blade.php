@extends('layouts.admin', ['title' => 'Contact messages', 'active' => 'messages'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Contact messages</h1>
            <span class="page-head__sub">Sent from the Contact page. Reply by email or phone.@unless (config('church.email')) Add a church email in Settings to get a copy of each by email.@endunless</span>
        </div>
    </header>

    <nav class="tabs" aria-label="Filter messages">
        @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
            <a href="{{ route('admin.messages.index', $key === 'all' ? [] : ['show' => $key]) }}" class="tab" @if ($filter === $key) aria-current="page" @endif>
                {{ $label }} <span>{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($messages as $m)
            <article @class(['prayer-card', 'prayer-card--unread' => ! $m->read_at])>
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $m->name }}</span>
                    <span class="pill pill--info">{{ $m->subject }}</span>
                    @unless ($m->read_at)
                        <span class="pill pill--gold">New</span>
                    @endunless
                    <time class="prayer-card__when" datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->diffForHumans() }}</time>
                </div>
                <p class="prayer-card__text" style="white-space: pre-line">{{ $m->message }}</p>
                <span class="prayer-card__contact">{{ $m->email }}@if ($m->phone) · {{ $m->phone }}@endif</span>
                <div class="list-item__actions" style="flex-wrap: wrap">
                    <a href="mailto:{{ $m->email }}?subject={{ rawurlencode('Re: '.$m->subject) }}" class="btn btn--navy btn--xs">Reply by email</a>
                    <form method="POST" action="{{ route('admin.messages.update', $m) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="read" value="{{ $m->read_at ? 0 : 1 }}">
                        <button type="submit" class="btn btn--ghost btn--xs">{{ $m->read_at ? 'Mark as unread' : 'Mark as handled' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.messages.destroy', $m) }}" data-confirm="Delete this message for good?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--xs">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">{{ $filter === 'all' ? 'No messages yet.' : 'Nothing unread.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $messages])
    </section>
@endsection
