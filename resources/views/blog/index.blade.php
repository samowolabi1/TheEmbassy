@extends('layouts.app', [
    'title' => 'Blog',
    'active' => 'blog',
    'description' => 'Faith, relationships, money, campus and purpose, written by our pastors and members.',
])

@section('content')
    <section class="container blog-hero stack" style="gap: 36px">
        <div class="section-head">
            <div class="stack" style="gap: 14px; max-width: 720px">
                <span class="eyebrow">THE TRIBE BLOG</span>
                <h1 class="display blog-hero__title">Real talk for <span class="accent-bronze">real life.</span></h1>
            </div>
            <p class="lede" style="max-width: 400px">Faith, relationships, money, campus and purpose, written by our pastors and members.</p>
        </div>

        <a href="{{ route('blog.show', $featured['slug']) }}" class="featured bg-navy">
            <div role="img" aria-label="Featured article image placeholder" class="featured__image bg-mid">
                <x-crown :width="380" :height="320" class="crown-bg" style="left: -60px; bottom: -80px; opacity: 0.12" />
                <span class="placeholder__note" style="color: var(--mist)">[Article image]</span>
            </div>
            <div class="featured__body">
                <div class="featured__tags">
                    <span class="tag tag--gold">FEATURED</span>
                    <span class="tag tag--ghost">{{ $featured['topic'] }}</span>
                </div>
                <span class="featured__title">{{ $featured['title'] }}</span>
                <span class="featured__dek">{{ $featured['dek'] }}</span>
                <span class="featured__by">
                    <span class="avatar featured__avatar">{{ $featured['author_initials'] }}</span>
                    <span class="stack">
                        <span class="featured__author">{{ $featured['author'] }}</span>
                        <span class="featured__meta">{{ $featured['date'] }} · {{ $featured['read'] }}</span>
                    </span>
                </span>
            </div>
        </a>
    </section>

    <section class="container section stack" style="gap: 24px" data-filter-scope data-filter-param="topic">
        <div role="tablist" aria-label="Blog topics" class="filters">
            <button type="button" role="tab" class="filter" aria-selected="true" data-filter="all">All topics</button>
            @foreach ($topics as $topic)
                <button type="button" role="tab" class="filter" aria-selected="false" data-filter="{{ $topic }}">{{ $topic }}</button>
            @endforeach
        </div>
        <div class="grid-auto" style="--min: 320px; --gap: 28px 22px">
            @foreach ($posts as $post)
                <x-post-card :post="$post" show-dek :image-height="230" data-filter-item data-tag="{{ $post['topic'] }}" />
            @endforeach
        </div>
        <p class="lede" data-filter-empty hidden>No articles on this topic yet. Check back soon.</p>
    </section>

    <section class="container section section--last">
        <div class="panel bg-gold pitch">
            <div class="stack" style="gap: 10px; max-width: 560px">
                <span class="pitch__title">Want to write for the blog?</span>
                <span class="pitch__text">We feature stories and lessons from members. Pitch your idea to the media unit.</span>
            </div>
            <a href="#" class="btn btn--navy">Pitch a story</a>
        </div>
    </section>
@endsection
