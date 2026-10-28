@extends('layouts.app', [
    'title' => $album->title.' · Gallery',
    'active' => 'gallery',
    'description' => $album->description ? \Illuminate\Support\Str::limit($album->description, 160) : 'Photos from '.$album->title.' at '.config('church.short_name').'.',
    'image' => $album->coverItem()?->isVideo() === false ? $album->coverItem()->url() : null,
])

@section('content')
    <section class="article-head gallery-head">
        <a href="{{ route('gallery.index') }}" class="back"><x-icon name="chevron-left" :size="16" />All albums</a>
        <h1 class="display article-head__title">{{ $album->title }}</h1>
        <div class="gallery-head__meta">
            @if ($album->taken_on)
                <span><x-icon name="calendar" :size="16" /> {{ $album->taken_on->format('j F Y') }}</span>
            @endif
            <span><x-icon name="image" :size="16" /> {{ $album->items->count() }} {{ \Illuminate\Support\Str::plural('item', $album->items->count()) }}</span>
            @if ($album->event?->is_published)
                <a href="{{ route('events.show', $album->event) }}" class="accent-bronze">{{ $album->event->title }} →</a>
            @endif
        </div>
        @if ($album->description)
            <p class="article-head__dek" style="font-size: 18px">{!! nl2br(e($album->description)) !!}</p>
        @endif
        <span class="share">
            <a href="https://wa.me/" data-share-href="https://wa.me/?text=" class="icon-circle share__btn" aria-label="Share on WhatsApp" target="_blank" rel="noopener"><x-icon name="whatsapp" /></a>
            <a href="https://x.com/intent/post" data-share-href="https://x.com/intent/post?text=" class="icon-circle share__btn" aria-label="Share on X" target="_blank" rel="noopener"><x-icon name="x" :size="18" /></a>
            <button type="button" class="icon-circle share__btn" aria-label="Copy link" data-copy="{{ route('gallery.show', $album) }}" data-copy-label="Copy link"><x-icon name="link" /></button>
        </span>
    </section>

    <section class="container section" data-gallery>
        <ul class="gallery-grid" role="list">
            @foreach ($album->items as $item)
                <li id="photo-{{ $item->id }}" class="gallery-grid__item">
                    <a href="{{ $item->isVideo() ? 'https://www.youtube.com/watch?v='.$item->youtube_id : $item->url() }}"
                        @class(['gallery-grid__link', 'gallery-grid__link--video' => $item->isVideo()]) data-gallery-item
                        data-id="{{ $item->id }}"
                        data-kind="{{ $item->kind }}"
                        @if ($item->isVideo()) data-video="{{ $item->youtube_id }}" @else data-full="{{ $item->url() }}" @endif
                        data-caption="{{ $item->caption }}"
                        @if ($item->isVideo()) target="_blank" rel="noopener" @endif>
                        <img src="{{ $item->thumbUrl() }}" alt="{{ $item->caption ?: ($item->isVideo() ? 'Video' : 'Photo').' '.$loop->iteration.' from '.$album->title }}"
                            @if ($item->width) width="{{ $item->width }}" height="{{ $item->height }}" @endif loading="lazy" decoding="async">
                        @if ($item->isVideo())
                            <span class="gallery-grid__play" aria-hidden="true"><x-icon name="play" :size="26" /></span>
                        @endif
                    </a>
                    @if ($item->caption)
                        <span class="gallery-grid__caption">{{ $item->caption }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <dialog class="lightbox" aria-label="Photo viewer" data-lightbox>
            <div class="lightbox__bar">
                <span class="lightbox__count" data-lightbox-count></span>
                <span class="lightbox__actions">
                    <button type="button" class="lightbox__btn" data-copy data-lightbox-link aria-label="Copy link to this photo"><x-icon name="link" :size="20" /></button>
                    <a href="#" class="lightbox__btn" data-lightbox-download download aria-label="Download this photo"><x-icon name="download" :size="20" /></a>
                    <button type="button" class="lightbox__btn" data-lightbox-close aria-label="Close"><x-icon name="close" :size="22" /></button>
                </span>
            </div>
            <div class="lightbox__stage" data-lightbox-stage></div>
            <p class="lightbox__caption" data-lightbox-caption></p>
            <button type="button" class="lightbox__nav lightbox__nav--prev" data-lightbox-prev aria-label="Previous"><x-icon name="chevron-left" :size="26" /></button>
            <button type="button" class="lightbox__nav lightbox__nav--next" data-lightbox-next aria-label="Next"><x-icon name="chevron-right" :size="26" /></button>
        </dialog>
    </section>

    @if ($more->isNotEmpty())
        <section class="container section--tight section--last stack" style="gap: 24px">
            <h2 class="h2 h2--sm">More albums</h2>
            <div class="grid-auto" style="--min: 300px; --gap: 22px">
                @foreach ($more as $other)
                    @php $cover = $other->coverItem(); @endphp
                    <a href="{{ route('gallery.show', $other) }}" class="album-card">
                        <span class="album-card__image bg-ice">
                            @if ($cover)
                                <img src="{{ $cover->thumbUrl() }}" alt="" loading="lazy" decoding="async">
                            @endif
                            <span class="label-chip">{{ $other->items_count }} {{ \Illuminate\Support\Str::plural('item', $other->items_count) }}</span>
                        </span>
                        <span class="post-card__title">{{ $other->title }}</span>
                        @if ($other->taken_on)
                            <span class="post-card__meta">{{ $other->taken_on->format('j F Y') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
