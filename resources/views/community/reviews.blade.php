@extends('layouts.app', ['title' => 'Book reviews', 'active' => 'community'])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('community.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Community</a>
        <span class="eyebrow">📚 BOOK REVIEWS</span>
        <h1 class="display notices-hero__title">What the Tribe is reading.</h1>
        <p class="lede" style="max-width: 680px">Reviews of books from the <a href="{{ route('library.index') }}" class="accent-bronze">church library</a>. Borrowed one? Open it in the library and tell us what you thought.</p>
    </section>

    <section class="container section--tight section--last reviews-layout">
        <div class="stack" style="gap: 14px">
            @if (session('status'))
                <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
            @endif
            @forelse ($reviews as $r)
                <article class="card review-card">
                    <a href="{{ route('library.show', $r->book) }}" class="review-card__book">
                        @include('library.cover', ['book' => $r->book])
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $r->book->title }}</strong>
                            <span class="field-hint">{{ $r->book->author }}</span>
                        </span>
                    </a>
                    <div class="review-card__head">
                        <strong>{{ $r->member->name }}</strong>
                        <span class="stars" aria-label="{{ $r->rating }} out of 5">{{ $r->stars() }}</span>
                        <span class="field-hint">{{ $r->created_at->diffForHumans() }}</span>
                    </div>
                    <p style="margin: 0; white-space: pre-line">{{ $r->body }}</p>
                    @if (auth()->user()->moderatesCommunity() && $r->member_id !== auth()->user()->member?->id)
                        <form method="POST" action="{{ route('community.reviews.destroy', $r) }}" onsubmit="return confirm('Take this review down?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--outline btn--sm">Take down</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="lede">No reviews yet. Be the first: open a book in the <a href="{{ route('library.index') }}" class="accent-bronze">library</a>.</p>
            @endforelse
            @if ($reviews->hasPages())
                <nav class="site-pager" aria-label="More reviews">
                    @if (! $reviews->onFirstPage())<a href="{{ $reviews->previousPageUrl() }}" class="btn btn--outline btn--sm" rel="prev">Newer</a>@endif
                    <span class="site-pager__count">Page {{ $reviews->currentPage() }} of {{ $reviews->lastPage() }}</span>
                    @if ($reviews->hasMorePages())<a href="{{ $reviews->nextPageUrl() }}" class="btn btn--outline btn--sm" rel="next">Older</a>@endif
                </nav>
            @endif
        </div>
        @if ($topRated->isNotEmpty())
            <aside class="card community-card" style="align-self: start">
                <span class="eyebrow">TOP RATED</span>
                @foreach ($topRated as $b)
                    <a href="{{ route('library.show', $b) }}" class="review-card__book">
                        @include('library.cover', ['book' => $b])
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $b->title }}</strong>
                            <span class="field-hint">★ {{ number_format($b->rating, 1) }} · {{ $b->review_count }} {{ \Illuminate\Support\Str::plural('review', $b->review_count) }}</span>
                        </span>
                    </a>
                @endforeach
            </aside>
        @endif
    </section>
@endsection
