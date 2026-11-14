@extends('layouts.app', ['title' => 'Library', 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">THE CHURCH LIBRARY</span>
        <h1 class="display notices-hero__title">Take a good book home.</h1>
        <p class="lede" style="max-width: 680px">Reserve a book here and collect it from the library at church. Keep it for {{ \App\Services\Library\Lending::loanDays() }} days, renew it once if nobody's waiting, and tell the Tribe what you thought in <a href="{{ route('community.reviews') }}" class="accent-bronze">book reviews</a>. @if ($returnTokens) Bring books back on time to earn {{ $returnTokens }} tokens each.@endif</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 24px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="notice-alert" role="alert" style="margin: 0">{{ $errors->first() }}</p>
        @endif

        @if ($mine->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your books</h2>
                @foreach ($mine as $loan)
                    <div class="package-row">
                        <a href="{{ route('library.show', $loan->book) }}" class="my-book">
                            @include('library.cover', ['book' => $loan->book])
                            <span class="stack" style="gap: 2px">
                                <strong>{{ $loan->book->title }}</strong>
                                <span class="field-hint">
                                    @switch($loan->status)
                                        @case('on_loan') {{ $loan->isOverdue() ? 'Was due' : 'Due back' }} {{ $loan->due_on->format('l j M') }} @break
                                        @case('requested') Set aside for you: collect it from the library at church @break
                                        @case('waiting') Number {{ $loan->queuePosition() }} on the waiting list @break
                                    @endswitch
                                </span>
                            </span>
                        </a>
                        <span style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <span class="status-chip {{ $loan->status === 'on_loan' && ! $loan->isOverdue() ? 'status-chip--ok' : '' }}">{{ $loan->label() }}</span>
                            @if ($loan->status === 'on_loan' && ! $loan->isOverdue() && $loan->renewals < config('church.library.renewals', 1))
                                <form method="POST" action="{{ route('library.renew', $loan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn--outline btn--sm">Renew</button>
                                </form>
                            @endif
                            @if (in_array($loan->status, ['requested', 'waiting'], true))
                                <form method="POST" action="{{ route('library.cancel', $loan) }}" onsubmit="return confirm('Cancel this reservation?')">
                                    @csrf
                                    <button type="submit" class="btn btn--outline btn--sm">Cancel</button>
                                </form>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('library.index') }}" class="library-search" role="search">
            <label class="sr-only" for="library-q">Search books</label>
            <input id="library-q" type="search" name="q" value="{{ $q }}" placeholder="Search by title or author" maxlength="100">
            @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
            <button type="submit" class="btn btn--navy btn--sm">Search</button>
        </form>
        <nav class="filters" aria-label="Categories">
            <a href="{{ route('library.index', array_filter(['q' => $q])) }}" class="filter topic-link" @if (! $category) aria-current="page" @endif>All</a>
            @foreach (\App\Models\Book::CATEGORIES as $key => $label)
                <a href="{{ route('library.index', array_filter(['category' => $key, 'q' => $q])) }}" class="filter topic-link" @if ($category === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($books->isEmpty())
            <p class="lede">{{ $q || $category ? 'No books match that.' : 'The library is being stocked. Check back soon.' }}</p>
        @else
            <ul class="book-grid" role="list">
                @foreach ($books as $book)
                    <li>
                        <a href="{{ route('library.show', $book) }}" class="book-card">
                            @include('library.cover', ['book' => $book])
                            <span class="book-card__title">{{ $book->title }}</span>
                            <span class="book-card__author">{{ $book->author }}</span>
                            <span class="book-card__meta">
                                @if ($book->copies - $book->taken > 0)
                                    <span class="book-card__free">Available</span>
                                @else
                                    <span class="book-card__out">All out</span>
                                @endif
                                @if ($book->rating) · ★ {{ number_format($book->rating, 1) }}@endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($books->hasPages())
                <nav class="site-pager" aria-label="More books">
                    @if ($books->onFirstPage())
                        <span class="btn btn--outline btn--sm" aria-disabled="true">Previous</span>
                    @else
                        <a href="{{ $books->previousPageUrl() }}" class="btn btn--outline btn--sm" rel="prev">Previous</a>
                    @endif
                    <span class="site-pager__count">Page {{ $books->currentPage() }} of {{ $books->lastPage() }}</span>
                    @if ($books->hasMorePages())
                        <a href="{{ $books->nextPageUrl() }}" class="btn btn--outline btn--sm" rel="next">Next</a>
                    @else
                        <span class="btn btn--outline btn--sm" aria-disabled="true">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
