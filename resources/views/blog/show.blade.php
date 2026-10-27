@extends('layouts.app', [
    'title' => $post['title'],
    'active' => 'blog',
    'description' => $post['dek'],
])

@section('content')
    <article>
        <header class="article-head">
            <a href="{{ route('blog.index') }}" class="back"><x-icon name="chevron-left" :size="16" />Back to the blog</a>
            <span class="tag tag--wash">{{ $post['topic'] }}</span>
            <h1 class="display article-head__title">{{ $post['title'] }}</h1>
            <p class="article-head__dek">{{ $post['dek'] }}</p>
            <div class="byline">
                <span class="byline__who">
                    <span class="avatar byline__avatar">{{ $post['author_initials'] }}</span>
                    <span class="stack">
                        <span class="byline__name">{{ $post['author'] }}</span>
                        <span class="byline__meta">{{ $post['date'] }} · {{ $post['read'] }}</span>
                    </span>
                </span>
                <span class="share">
                    <a href="https://wa.me/" data-share-href="https://wa.me/?text=" class="icon-circle share__btn" aria-label="Share on WhatsApp" target="_blank" rel="noopener"><x-icon name="whatsapp" /></a>
                    <a href="https://x.com/intent/post" data-share-href="https://x.com/intent/post?text=" class="icon-circle share__btn" aria-label="Share on X" target="_blank" rel="noopener"><x-icon name="x" :size="18" /></a>
                    <button type="button" class="icon-circle share__btn" aria-label="Copy link" data-copy data-copy-label="Copy link"><x-icon name="link" /></button>
                </span>
            </div>
        </header>

        <div class="article-image-wrap">
            <div role="img" aria-label="Article image placeholder" class="placeholder bg-mid article-image">
                <x-crown :width="420" :height="350" jewels class="crown-bg" style="right: -40px; bottom: -80px; opacity: 0.14" />
                <span class="placeholder__note" style="color: var(--mist)">[Article image]</span>
            </div>
        </div>

        <div class="prose">
            @forelse ($post['body'] ?? [] as $block)
                @switch($block[0])
                    @case('p')
                        <p>{{ $block[1] }}</p>
                        @break
                    @case('h2')
                        <h2>{{ $block[1] }}</h2>
                        @break
                    @case('quote')
                        <figure class="scripture">
                            <x-crown :width="34" :height="28" />
                            <blockquote>"{{ $block[1] }}"</blockquote>
                            <figcaption>{{ $block[2] }}</figcaption>
                        </figure>
                        @break
                    @case('reflect')
                        <div class="reflect">
                            <span class="eyebrow">{{ $block[1] }}</span>
                            <ol>
                                @foreach ($block[2] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ol>
                        </div>
                        @break
                @endswitch
            @empty
                <p>[Article body]</p>
            @endforelse
        </div>

        <div class="author-wrap">
            <div class="card author">
                <span class="avatar author__avatar">{{ $post['author_initials'] }}</span>
                <span class="author__text">
                    <span class="author__name">{{ $post['author'] }}</span>
                    <span class="author__bio">{{ $post['author_bio'] }}</span>
                </span>
            </div>
        </div>
    </article>

    <section class="container section--tight section--last stack" style="gap: 24px">
        <h2 class="h2 h2--sm">Keep reading</h2>
        <div class="grid-auto" style="--min: 320px; --gap: 22px">
            @foreach ($related as $p)
                <a href="{{ route('blog.show', $p['slug']) }}" class="related">
                    <div role="img" aria-label="Article image placeholder" class="related__image bg-{{ $p['swatch'] }}"></div>
                    <span class="related__topic">{{ $p['topic'] }}</span>
                    <span class="related__title">{{ $p['title'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
