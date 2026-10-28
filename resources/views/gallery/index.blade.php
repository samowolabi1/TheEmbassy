@extends('layouts.app', [
    'title' => $year ? "Gallery · {$year}" : 'Gallery',
    'active' => 'gallery',
    'description' => 'Photos and videos from services, events and life together at '.config('church.short_name').'.',
])

@section('content')
    <section class="container blog-hero stack" style="gap: 28px">
        <div class="section-head">
            <div class="stack" style="gap: 14px; max-width: 720px">
                <span class="eyebrow">GALLERY</span>
                <h1 class="display blog-hero__title">Moments from <span class="accent-bronze">the Tribe.</span></h1>
            </div>
            <p class="lede" style="max-width: 400px">Photos and videos from Sundays, conventions, outreaches and everything in between.</p>
        </div>
        @if ($years->count() > 1)
            <nav class="filters" aria-label="Years">
                <a href="{{ route('gallery.index') }}" class="filter topic-link" @if (! $year) aria-current="page" @endif>All years</a>
                @foreach ($years as $y)
                    <a href="{{ route('gallery.index', ['year' => $y]) }}" class="filter topic-link" @if ($year === $y) aria-current="page" @endif>{{ $y }}</a>
                @endforeach
            </nav>
        @endif
    </section>

    <section class="container section section--last stack" style="gap: 28px">
        @if ($albums->isEmpty())
            <div class="card gallery-empty">
                <x-icon name="image" :size="28" />
                <p class="lede" style="margin: 0">Photos are on the way. Check back after Sunday.</p>
            </div>
        @else
            <div class="grid-auto" style="--min: 300px; --gap: 28px 22px">
                @foreach ($albums as $album)
                    @php $cover = $album->coverItem(); @endphp
                    <a href="{{ route('gallery.show', $album) }}" class="album-card">
                        <span class="album-card__image bg-ice">
                            @if ($cover)
                                <img src="{{ $cover->thumbUrl() }}" alt="" loading="lazy" decoding="async">
                            @endif
                            <span class="label-chip">{{ $album->items_count }} {{ \Illuminate\Support\Str::plural('item', $album->items_count) }}</span>
                        </span>
                        <span class="post-card__title">{{ $album->title }}</span>
                        @if ($album->taken_on)
                            <span class="post-card__meta">{{ $album->taken_on->format('j F Y') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            @if ($albums->hasPages())
                <nav class="site-pager" aria-label="More albums">
                    @if ($albums->onFirstPage())
                        <span class="btn btn--outline btn--sm" aria-disabled="true">Newer</span>
                    @else
                        <a href="{{ $albums->previousPageUrl() }}" class="btn btn--outline btn--sm" rel="prev">Newer</a>
                    @endif
                    <span class="site-pager__count">Page {{ $albums->currentPage() }} of {{ $albums->lastPage() }}</span>
                    @if ($albums->hasMorePages())
                        <a href="{{ $albums->nextPageUrl() }}" class="btn btn--outline btn--sm" rel="next">Older</a>
                    @else
                        <span class="btn btn--outline btn--sm" aria-disabled="true">Older</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
