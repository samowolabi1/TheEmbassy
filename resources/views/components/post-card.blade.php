{{-- A blog article card: image placeholder with topic, title, optional summary and byline. --}}
@props(['post', 'showDek' => false, 'imageHeight' => 220])

<a href="{{ route('blog.show', $post['slug']) }}" {{ $attributes->class('post-card') }}>
    <div role="img" aria-label="Article image placeholder" class="post-card__image bg-{{ $post['swatch'] }}" style="height: {{ $imageHeight }}px">
        <span @class(['label-chip', 'label-chip--gold' => $post['swatch'] === 'navy'])>{{ $post['topic'] }}</span>
    </div>
    <span class="post-card__text">
        <span class="post-card__title">{{ $post['title'] }}</span>
        @if ($showDek)
            <span class="post-card__dek">{{ $post['dek'] }}</span>
        @endif
        <span class="post-card__meta">{{ $post['author'] }} · {{ $post['read'] }}</span>
    </span>
</a>
