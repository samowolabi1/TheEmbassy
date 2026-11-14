{{--
    One slide in the home page slider, from the content staff edit in admin (Pages › Home page).
    Needs: $slide, $i, $total, $headline (the current sermon series), $links (social links).
--}}
@php
    $series = $slide['feature'] === 'series';
    $title = trim((string) $slide['title']) !== '' ? $slide['title'] : ($series ? $headline['series_title'] : '');
    $accent = trim((string) $slide['accent']) !== '' ? $slide['accent'] : ($series ? $headline['series_accent'] : '');
    $link = fn (?string $to) => match (true) {
        $to === '@youtube' => $links['youtube'] ?? route('watch'),
        $to === '@sermon' => $headline['url'] ?? route('watch'),
        blank($to) => null,
        default => $to,
    };
    $buttons = collect([['primary', 'btn--gold'], ['secondary', 'btn--outline-light']])
        ->map(fn ($b) => ['label' => trim((string) ($slide[$b[0].'_label'] ?? '')), 'to' => $slide[$b[0].'_link'] ?? null, 'class' => $b[1]])
        ->filter(fn ($b) => $b['label'] !== '' && filled($b['to']));
    $photo = \App\Models\PageContent::photoUrl($slide['photo'] ?? null);
    $date = preg_split('/\s+/', trim((string) ($slide['feature_text'] ?? '')), 2);
@endphp
<div class="slide" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $total }}" data-slide @if ($i > 0) hidden @endif>
    <div class="slide__copy">
        @if (filled($slide['kicker']))
            <span class="slide__kicker slide__kicker--{{ $slide['kicker_style'] === 'gold' ? 'gold' : 'outline' }}">{{ mb_strtoupper($slide['kicker']) }}</span>
        @endif
        <{{ $i === 0 ? 'h1' : 'h2' }} class="display slide__title">{!! nl2br(e($title)) !!}@if ($accent !== '')@if ($title !== '')<br>@endif<span class="accent">{{ $accent }}</span>@endif</{{ $i === 0 ? 'h1' : 'h2' }}>
        @if (filled($slide['text']))
            <p class="slide__text">{{ $slide['text'] }}</p>
        @endif
        @if ($buttons->isNotEmpty())
            <div class="slide__ctas">
                @foreach ($buttons as $b)
                    @if ($b['to'] === '@share')
                        <button type="button" class="btn {{ $b['class'] }}" data-share data-share-text="{{ trim(preg_replace('/\s+/', ' ', $title.' '.$accent)) }}{{ $slide['feature'] === 'verse' && $slide['feature_ref'] ? ' '.$slide['feature_ref'] : '' }}">{{ $b['label'] }}</button>
                    @else
                        <a href="{{ $link($b['to']) }}" class="btn {{ $b['class'] }}">@if ($b['to'] === '@youtube' || $b['to'] === '@sermon')<x-icon name="play" :size="16" />@endif{{ $b['label'] }}</a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    @if ($slide['feature'] === 'verse')
        <div class="slide__media slide__verse">
            <x-crown :width="64" :height="54" jewels />
            <span class="slide__quote">"{{ trim((string) $slide['feature_text'], '"“” ') }}"</span>
            @if ($slide['feature_ref'])
                <span class="slide__ref">{{ mb_strtoupper($slide['feature_ref']) }}</span>
            @endif
        </div>
    @else
        <div @if ($photo) role="img" aria-label="{{ $slide['photo_alt'] ?: $title }}" @else role="img" aria-label="{{ $slide['photo_alt'] ?: 'Decorative' }}" @endif
            class="placeholder slide__media bg-{{ $slide['swatch'] ?? 'mid' }} {{ in_array($slide['swatch'] ?? 'mid', ['mid', 'night', 'navy'], true) ? 'hero__bordered' : '' }} {{ $photo ? 'slide__media--photo' : '' }}">
            @if ($photo)
                <img src="{{ $photo }}" alt="" class="slide__photo" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async">
            @elseif ($slide['feature'] === 'series')
                <x-crown :width="360" :height="300" jewels band class="crown-bg slide__crown" style="opacity: 0.9" />
            @elseif (($slide['swatch'] ?? '') === 'gold')
                <x-crown :width="420" :height="350" fill="var(--midnight)" jewels class="crown-bg" style="right: -60px; bottom: -70px; opacity: 0.1" />
            @endif
            @switch($slide['feature'])
                @case('service')
                    <div class="next-card">
                        <span class="next-card__label">NEXT GATHERING</span>
                        <span class="next-card__time">Sunday · {{ config('church.sunday_time') }}</span>
                        @if ($directions = \App\Support\ChurchLocation::directionsUrl())
                            <a href="{{ $directions }}" target="_blank" rel="noopener" class="next-card__where" style="color: inherit">{{ config('church.address') }} · Directions →</a>
                        @else
                            <span class="next-card__where">{{ config('church.address') }}</span>
                        @endif
                    </div>
                    @break
                @case('date')
                    @if (filled($slide['feature_text']))
                        <span class="slide__date">{{ $date[0] }}@if (isset($date[1]))<br><span>{{ mb_strtoupper($date[1]) }}</span>@endif</span>
                    @endif
                    @break
                @case('series')
                    <span class="slide__series">{{ mb_strtoupper(trim($title.' '.$accent)) }}</span>
                    @break
            @endswitch
        </div>
    @endif
</div>
