@extends('layouts.app', [
    'title' => $topic !== '' ? $topic.' · Blog' : 'Blog',
    'active' => 'blog',
    'description' => 'Faith, relationships, money, campus and purpose, written by our pastors and members.',
])

@push('head')
    <link rel="alternate" type="application/rss+xml" title="{{ config('church.short_name') }} blog" href="{{ route('blog.feed') }}">
@endpush

@section('content')
    <section class="container blog-hero stack" style="gap: 36px">
        <div class="section-head">
            <div class="stack" style="gap: 14px; max-width: 720px">
                <span class="eyebrow">THE TRIBE BLOG</span>
                <h1 class="display blog-hero__title">Real talk for <span class="accent-bronze">real life.</span></h1>
            </div>
            <p class="lede" style="max-width: 400px">Faith, relationships, money, campus and purpose, written by our pastors and members.</p>
        </div>

        @if ($featured)
            <a href="{{ $featured->url() }}" class="featured bg-navy">
                @if ($featured->coverUrl())
                    <div class="featured__image featured__image--photo">
                        <img src="{{ $featured->coverUrl() }}" alt="{{ $featured->cover_alt }}" decoding="async">
                    </div>
                @else
                    <div role="img" aria-label="{{ $featured->title }}" class="featured__image bg-{{ $featured->swatch === 'navy' ? 'mid' : $featured->swatch }}">
                        <x-crown :width="380" :height="320" class="crown-bg" style="left: -60px; bottom: -80px; opacity: 0.12" />
                    </div>
                @endif
                <div class="featured__body">
                    <div class="featured__tags">
                        <span class="tag tag--gold">FEATURED</span>
                        <span class="tag tag--ghost">{{ $featured->topic }}</span>
                    </div>
                    <span class="featured__title">{{ $featured->title }}</span>
                    @if ($featured->dek)
                        <span class="featured__dek">{{ $featured->dek }}</span>
                    @endif
                    <span class="featured__by">
                        <span class="avatar featured__avatar">{{ $featured->authorInitials() }}</span>
                        <span class="stack">
                            <span class="featured__author">{{ $featured->authorName() }}</span>
                            <span class="featured__meta">{{ $featured->dateLabel() }} · {{ $featured->readingTime() }}</span>
                        </span>
                    </span>
                </div>
            </a>
        @endif
    </section>

    <section id="articles" class="container section stack" style="gap: 24px">
        <div class="blog-tools">
            <nav class="filters" aria-label="Blog topics">
                <a href="{{ route('blog.index') }}#articles" class="filter topic-link" @if ($topic === '' && $search === '') aria-current="page" @endif>All topics</a>
                @foreach ($topics as $t)
                    <a href="{{ route('blog.index', ['topic' => $t]) }}#articles" class="filter topic-link" @if ($topic === $t) aria-current="page" @endif>{{ $t }}</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('blog.index') }}#articles" class="blog-search" role="search">
                @if ($topic !== '')
                    <input type="hidden" name="topic" value="{{ $topic }}">
                @endif
                <label class="sr-only" for="blog-q">Search articles</label>
                <input id="blog-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search articles">
                <button type="submit" class="btn btn--navy btn--sm">Search</button>
            </form>
        </div>

        @if ($search !== '')
            <p class="lede" style="margin: 0">{{ $posts->total() }} {{ \Illuminate\Support\Str::plural('result', $posts->total()) }} for "{{ $search }}"@if ($topic !== '') in {{ $topic }}@endif · <a href="{{ route('blog.index', array_filter(['topic' => $topic])) }}#articles" class="accent-bronze">clear search</a></p>
        @endif

        @if ($posts->isEmpty())
            <p class="lede">{{ $search !== '' ? 'No articles match that search.' : 'No articles on this topic yet. Check back soon.' }}</p>
        @else
            <div class="grid-auto" style="--min: 320px; --gap: 28px 22px">
                @foreach ($posts as $post)
                    <x-post-card :post="$post" show-dek :image-height="230" />
                @endforeach
            </div>
        @endif

        @if ($posts->hasPages())
            <nav class="site-pager" aria-label="More articles">
                @if ($posts->onFirstPage())
                    <span class="btn btn--outline btn--sm" aria-disabled="true">Newer</span>
                @else
                    <a href="{{ $posts->previousPageUrl() }}#articles" class="btn btn--outline btn--sm" rel="prev">Newer</a>
                @endif
                <span class="site-pager__count">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                @if ($posts->hasMorePages())
                    <a href="{{ $posts->nextPageUrl() }}#articles" class="btn btn--outline btn--sm" rel="next">Older</a>
                @else
                    <span class="btn btn--outline btn--sm" aria-disabled="true">Older</span>
                @endif
            </nav>
        @endif
    </section>

    <section class="container section section--last">
        <div class="panel bg-gold pitch">
            <div class="stack" style="gap: 10px; max-width: 560px">
                <span class="pitch__title">Want to write for the blog?</span>
                <span class="pitch__text">We feature stories and lessons from members. Pitch your idea to the media unit.</span>
            </div>
            <a href="{{ route('contact') }}" class="btn btn--navy">Pitch a story</a>
        </div>
    </section>
@endsection
