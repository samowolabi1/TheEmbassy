@extends('layouts.admin', ['title' => 'Prayer requests', 'active' => 'prayer'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Prayer requests</h1>
            <span class="page-head__sub">Sent from the form on the home page. Private requests are for the pastoral team only.</span>
        </div>
    </header>

    <nav class="tabs" aria-label="Filter prayer requests">
        @foreach (['all' => 'All', 'unread' => 'Unread', 'private' => 'Private'] as $key => $label)
            <a href="{{ route('admin.prayer-requests.index', $key === 'all' ? [] : ['show' => $key]) }}" class="tab" @if ($filter === $key) aria-current="page" @endif>
                {{ $label }} <span>{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($requests as $p)
            <article @class(['prayer-card', 'prayer-card--unread' => ! $p->read_at])>
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $p->name ?: 'Anonymous' }}</span>
                    @if ($p->is_private)
                        <span class="pill pill--warn">Private</span>
                    @endif
                    @unless ($p->read_at)
                        <span class="pill pill--gold">New</span>
                    @endunless
                    <time class="prayer-card__when" datetime="{{ $p->created_at->toIso8601String() }}" title="{{ $p->created_at->format('D j M Y, g:i A') }}">{{ $p->created_at->diffForHumans() }}</time>
                </div>
                <p class="prayer-card__text">{{ $p->request }}</p>
                @if ($p->contact)
                    <span class="prayer-card__contact"><strong>Follow up:</strong> {{ $p->contact }}</span>
                @endif
                <div class="list-item__actions">
                    <form method="POST" action="{{ route('admin.prayer-requests.update', $p) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="read" value="{{ $p->read_at ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $p->read_at ? 'btn--ghost' : 'btn--navy' }} btn--xs">{{ $p->read_at ? 'Mark as unread' : 'Mark as prayed for' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.prayer-requests.destroy', $p) }}" data-confirm="Delete this prayer request for good?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--xs">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">{{ $filter === 'all' ? 'No prayer requests yet.' : 'Nothing here.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $requests])
    </section>
@endsection
