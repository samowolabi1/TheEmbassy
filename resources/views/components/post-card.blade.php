{{-- A blog article card: cover photo (or a colour block) with topic, title, optional summary and byline. --}}
@props(['post', 'showDek' => false, 'imageHeight' => 220])

<a href="{{ $post->url() }}" {{ $attributes->class('post-card') }}>
    <div @class(['post-card__image', 'bg-'.$post->swatch, 'post-card__image--photo' => $post->thumbUrl()]) style="height: {{ $imageHeight }}px">
        @if ($post->thumbUrl())
            <img src="{{ $post->thumbUrl() }}" alt="{{ $post->cover_alt }}" loading="lazy" decoding="async">
        @endif
        <span @class(['label-chip', 'label-chip--gold' => $post->swatch === 'navy' && ! $post->thumbUrl()])>{{ $post->topic }}</span>
    </div>
    <span class="post-card__text">
        <span class="post-card__title">{{ $post->title }}</span>
        @if ($showDek && $post->dek)
            <span class="post-card__dek">{{ $post->dek }}</span>
        @endif
        <span class="post-card__meta">{{ $post->authorName() }} · {{ $post->readingTime() }}</span>
    </span>
</a>
