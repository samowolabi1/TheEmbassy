@extends('layouts.app', [
    'title' => $post->title,
    'active' => 'blog',
    'description' => $post->summary(),
    'image' => $post->coverUrl(),
    'ogType' => 'article',
])

@push('head')
    <link rel="canonical" href="{{ $post->url() }}">
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
                <span class="share">
                    <a href="https://wa.me/" data-share-href="https://wa.me/?text=" class="icon-circle share__btn" aria-label="Share on WhatsApp" target="_blank" rel="noopener"><x-icon name="whatsapp" /></a>
                    <a href="https://x.com/intent/post" data-share-href="https://x.com/intent/post?text=" class="icon-circle share__btn" aria-label="Share on X" target="_blank" rel="noopener"><x-icon name="x" :size="18" /></a>
                    <button type="button" class="icon-circle share__btn" aria-label="Copy link" data-copy="{{ $post->url() }}" data-copy-label="Copy link"><x-icon name="link" /></button>
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
