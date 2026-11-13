@extends('layouts.app', [
    'title' => 'Testimonies',
    'active' => null,
    'description' => 'What God is doing in the lives of our members: healing, provision, breakthrough and more.',
])

@section('content')
    <section class="container blog-hero stack" style="gap: 28px">
        <div class="section-head">
            <div class="stack" style="gap: 14px; max-width: 720px">
                <span class="eyebrow">TESTIMONIES</span>
                <h1 class="display blog-hero__title">God is <span class="accent-bronze">still moving.</span></h1>
            </div>
            <p class="lede" style="max-width: 420px">Stories of healing, provision and breakthrough from our members. Has God done something for you? @auth<a href="{{ route('account.testimonies') }}" class="accent-bronze">Share your testimony</a>.@else<a href="{{ route('login') }}" class="accent-bronze">Sign in to share yours</a>.@endauth</p>
        </div>

        <nav class="filters" aria-label="Kinds of testimony">
            <a href="{{ route('testimonies.index') }}" class="filter topic-link" @if (! $category) aria-current="page" @endif>All</a>
            @foreach (\App\Models\Testimony::CATEGORIES as $key => $label)
                <a href="{{ route('testimonies.index', ['category' => $key]) }}" class="filter topic-link" @if ($category === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <section class="container section--tight section--last stack" style="gap: 24px">
        @if ($testimonies->isEmpty())
            <p class="lede">No testimonies here yet. Yours could be the first.</p>
        @else
            <div class="testimony-grid">
                @foreach ($testimonies as $t)
                    <a href="{{ route('testimonies.show', $t) }}" class="testimony-card">
                        <span class="testimony-card__tag">{{ $t->categoryLabel() }}</span>
                        <span class="testimony-card__title">{{ $t->title }}</span>
                        <span class="testimony-card__text">{{ $t->excerpt(200) }}</span>
                        <span class="testimony-card__by">{{ $t->byline() }} · {{ $t->published_at->format('j M Y') }}@if ($t->praises) · 🙌 {{ $t->praises }}@endif</span>
                    </a>
                @endforeach
            </div>
            @if ($testimonies->hasPages())
                <nav class="site-pager" aria-label="More testimonies">
                    @if ($testimonies->onFirstPage())
                        <span class="btn btn--outline btn--sm" aria-disabled="true">Newer</span>
                    @else
                        <a href="{{ $testimonies->previousPageUrl() }}" class="btn btn--outline btn--sm" rel="prev">Newer</a>
                    @endif
                    <span class="site-pager__count">Page {{ $testimonies->currentPage() }} of {{ $testimonies->lastPage() }}</span>
                    @if ($testimonies->hasMorePages())
                        <a href="{{ $testimonies->nextPageUrl() }}" class="btn btn--outline btn--sm" rel="next">Older</a>
                    @else
                        <span class="btn btn--outline btn--sm" aria-disabled="true">Older</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
