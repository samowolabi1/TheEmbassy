@extends('layouts.app', [
    'title' => $post->title,
    'active' => 'blog',
    'description' => $post->summary(),
    // Shared links show the title, the author and a picture with both on it.
    'ogTitle' => $post->title.' · by '.$post->authorName(),
    'ogDescription' => 'By '.$post->authorName().' · '.$post->summary(),
    'image' => $post->isLive() ? route('blog.share-image', [$post->slug, 'wide']) : $post->coverUrl(),
    'ogType' => 'article',
])

@push('head')
    <link rel="canonical" href="{{ $post->url() }}">
    <meta name="author" content="{{ $post->authorName() }}">
    <meta property="article:author" content="{{ $post->authorName() }}">
    <meta property="article:section" content="{{ $post->topic }}">
    @if ($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    @if ($post->isLive())
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $post->title }}, by {{ $post->authorName() }}">
    @endif
    <link rel="alternate" type="application/rss+xml" title="{{ config('church.short_name') }} blog" href="{{ route('blog.feed') }}">
    @if ($preview)
        <meta name="robots" content="noindex">
    @endif
    @if ($post->isLive())
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->summary(),
            'datePublished' => $post->published_at->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'author' => ['@type' => 'Person', 'name' => $post->authorName()],
            'publisher' => ['@type' => 'Organization', 'name' => config('church.name')],
            'image' => $post->coverUrl(),
            'mainEntityOfPage' => $post->url(),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
@endpush

@section('content')
    @if ($preview)
        <div class="preview-bar" role="status">
            <span><strong>Preview.</strong> @switch($post->status())
                @case('draft') This draft isn't on the website yet. @break
                @case('scheduled') This article goes live on {{ $post->published_at->format('j M Y, g:i A') }}. @break
                @default This article is live.
            @endswitch</span>
            <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn--gold btn--sm">Back to editing</a>
        </div>
    @endif
    <article>
        <header class="article-head">
            <a href="{{ route('blog.index') }}" class="back"><x-icon name="chevron-left" :size="16" />Back to the blog</a>
            <a href="{{ route('blog.index', ['topic' => $post->topic]) }}" class="tag tag--wash" style="align-self: flex-start">{{ $post->topic }}</a>
            <h1 class="display article-head__title">{{ $post->title }}</h1>
            @if ($post->dek)
                <p class="article-head__dek">{{ $post->dek }}</p>
            @endif
            <div class="byline">
                <span class="byline__who">
                    <span class="avatar byline__avatar">{{ $post->authorInitials() }}</span>
                    <span class="stack">
                        <span class="byline__name">{{ $post->authorName() }}</span>
                        <span class="byline__meta"><time datetime="{{ ($post->published_at ?? $post->updated_at)?->toDateString() }}">{{ $post->dateLabel() }}</time> · {{ $post->readingTime() }}</span>
                    </span>
                </span>
                @php
                    $shareLine = '"'.$post->title.'" by '.$post->authorName();
                    $shareText = rawurlencode($shareLine.' '.$post->url());
                    $shareUrl = rawurlencode($post->url());
                @endphp
                <span class="share">
                    @if ($post->isLive())
                        <button type="button" class="icon-circle share__btn" aria-label="Share" data-share-native data-share-title="{{ $post->title }}" data-share-text="{{ $shareLine }} {{ $post->url() }}" data-share-image="{{ route('blog.share-image', [$post->slug, 'square']) }}" hidden><x-icon name="share" :size="18" /></button>
                    @endif
                    <a href="https://wa.me/?text={{ $shareText }}" class="icon-circle share__btn" aria-label="Share on WhatsApp" target="_blank" rel="noopener"><x-icon name="whatsapp" /></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}&amp;quote={{ rawurlencode($shareLine) }}" class="icon-circle share__btn" aria-label="Share on Facebook" target="_blank" rel="noopener"><x-icon name="facebook" :size="18" /></a>
                    <a href="https://x.com/intent/post?text={{ rawurlencode($shareLine) }}&amp;url={{ $shareUrl }}" class="icon-circle share__btn" aria-label="Share on X" target="_blank" rel="noopener"><x-icon name="x" :size="18" /></a>
                    <a href="https://t.me/share/url?url={{ $shareUrl }}&amp;text={{ rawurlencode($shareLine) }}" class="icon-circle share__btn" aria-label="Share on Telegram" target="_blank" rel="noopener"><x-icon name="telegram" :size="18" /></a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" class="icon-circle share__btn" aria-label="Share on LinkedIn" target="_blank" rel="noopener"><x-icon name="linkedin" :size="18" /></a>
                    <button type="button" class="icon-circle share__btn" aria-label="Copy link" data-copy="{{ $shareLine }} {{ $post->url() }}" data-copy-label="Copy link"><x-icon name="link" /></button>
                </span>
            </div>
        </header>

        <div class="article-image-wrap">
            @if ($post->coverUrl())
                <figure class="article-image article-image--photo">
                    <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt }}" decoding="async">
                </figure>
            @else
                <div class="placeholder bg-{{ $post->swatch === 'navy' ? 'mid' : $post->swatch }} article-image article-image--plain">
                    <x-crown :width="420" :height="350" jewels class="crown-bg" style="right: -40px; bottom: -80px; opacity: 0.14" />
                </div>
            @endif
        </div>

        <div class="prose">
            {!! $post->body_html ?: '<p>[Article body]</p>' !!}
        </div>

        <div class="author-wrap">
            <div class="card author">
                <span class="avatar author__avatar">{{ $post->authorInitials() }}</span>
                <span class="author__text">
                    <span class="author__name">{{ $post->authorName() }}</span>
                    @if ($post->author_bio)
                        <span class="author__bio">{{ $post->author_bio }}</span>
                    @endif
                </span>
            </div>
        </div>

        @if ($post->isLive())
            <section class="share-article" aria-labelledby="share-article-title">
                <div class="share-article__card">
                    <img src="{{ route('blog.share-image', [$post->slug, 'square']) }}" alt="Share picture: {{ $post->title }}, by {{ $post->authorName() }}" loading="lazy" width="220" height="220">
                    <div class="stack" style="gap: 10px">
                        <h2 id="share-article-title" class="h2 h2--sm" style="margin: 0">Share this article</h2>
                        <p class="field-hint" style="margin: 0">The picture shows the title and {{ $post->authorName() }}'s name. Post it to WhatsApp status, Instagram or Facebook with the link.</p>
                        <div class="share-article__actions">
                            <button type="button" class="btn btn--gold btn--sm" data-share-native data-share-title="{{ $post->title }}" data-share-text="{{ \App\Services\Market\ShareCards::postCaption($post) }}" data-share-image="{{ route('blog.share-image', [$post->slug, 'square']) }}" hidden>Share</button>
                            <a href="{{ route('blog.share-image', [$post->slug, 'square']) }}?download=1" class="btn btn--outline btn--sm" download>Square picture</a>
                            <a href="{{ route('blog.share-image', [$post->slug, 'story']) }}?download=1" class="btn btn--outline btn--sm" download>Status picture</a>
                            <button type="button" class="btn btn--outline btn--sm" data-copy="{{ \App\Services\Market\ShareCards::postCaption($post) }}" data-copy-label="Copy caption">Copy caption</button>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="container section--tight section--last stack" style="gap: 24px">
            <h2 class="h2 h2--sm">Keep reading</h2>
            <div class="grid-auto" style="--min: 320px; --gap: 22px">
                @foreach ($related as $p)
                    <a href="{{ $p->url() }}" class="related">
                        <div class="related__image bg-{{ $p->swatch }}">
                            @if ($p->thumbUrl())
                                <img src="{{ $p->thumbUrl() }}" alt="{{ $p->cover_alt }}" loading="lazy" decoding="async">
                            @endif
                        </div>
                        <span class="related__topic">{{ $p->topic }}</span>
                        <span class="related__title">{{ $p->title }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
