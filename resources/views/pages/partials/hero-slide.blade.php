{{--
    One slide in the home page slider, from the content staff edit in admin (Pages › Home page).
    Needs: $slide, $i, $total, $headline (the current sermon series), $links (social links).

    The whole slide is the picture (or a colour with the crown), the words sit over it, and an
    optional card floats beside them: the next service, a date, the sermon series or a verse.
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
    $swatch = $slide['swatch'] ?? 'mid';
    $photo = \App\Models\PageContent::photoUrl($slide['photo'] ?? null);
    // Gold slides without a photo use dark words; everything else is light on dark.
    $light = ! $photo && $swatch === 'gold';
    $buttons = collect([['primary', $light ? 'btn--navy' : 'btn--gold'], ['secondary', $light ? 'btn--outline' : 'btn--glass']])
        ->map(fn ($b) => ['label' => trim((string) ($slide[$b[0].'_label'] ?? '')), 'to' => $slide[$b[0].'_link'] ?? null, 'class' => $b[1]])
        ->filter(fn ($b) => $b['label'] !== '' && filled($b['to']));
    $lines = collect(preg_split('/\R/', trim((string) $title)))->filter(fn ($l) => trim($l) !== '')->values();
    $date = preg_split('/\s+/', trim((string) ($slide['feature_text'] ?? '')), 2);
    $tag = $i === 0 ? 'h1' : 'h2';
    $n = 0;
@endphp
<article @class(['hero2__slide', 'hero2__slide--'.$swatch, 'hero2__slide--photo' => $photo, 'hero2__slide--light' => $light, 'is-active' => $i === 0])
    role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $total }}" data-slide @if ($i > 0) aria-hidden="true" inert @endif>
    <div class="hero2__bg" aria-hidden="true">
        @if ($photo)
            <img src="{{ $photo }}" alt="" class="hero2__photo" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async">
        @else
            <x-crown :width="560" :height="466" :fill="$light ? 'var(--midnight)' : 'var(--gold)'" jewels :band="! $light" class="hero2__crown" />
        @endif
    </div>
    @if ($photo && filled($slide['photo_alt']))
        <span class="sr-only">{{ $slide['photo_alt'] }}</span>
    @endif

    <div class="container hero2__inner">
        <div class="hero2__copy">
            @if (filled($slide['kicker']))
                <span class="hero2__kicker hero2__kicker--{{ $slide['kicker_style'] === 'gold' ? 'gold' : 'outline' }} hero2__in" style="--d: 0">{{ mb_strtoupper($slide['kicker']) }}</span>
            @endif
            <{{ $tag }} class="display hero2__title">@foreach ($lines as $line)<span class="hero2__line"><span style="--d: {{ ++$n }}">{{ $line }}</span></span>@endforeach @if ($accent !== '')<span class="hero2__line"><span class="accent" style="--d: {{ ++$n }}">{{ $accent }}</span></span>@endif</{{ $tag }}>
            @if (filled($slide['text']))
                <p class="hero2__text hero2__in" style="--d: {{ $n + 1 }}">{{ $slide['text'] }}</p>
            @endif
            @if ($buttons->isNotEmpty())
                <div class="hero2__ctas hero2__in" style="--d: {{ $n + 2 }}">
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

        @switch($slide['feature'])
            @case('service')
                <div class="hero2__card hero2__card--service">
                    <span class="hero2__card-label"><span class="hero2__live" aria-hidden="true"></span>NEXT GATHERING</span>
                    <span class="hero2__card-big">Sunday · {{ config('church.sunday_time') }}</span>
                    @if ($directions = \App\Support\ChurchLocation::directionsUrl())
                        <a href="{{ $directions }}" target="_blank" rel="noopener" class="hero2__card-where"><x-icon name="pin" :size="16" />{{ config('church.address') }} <span class="hero2__card-go">Directions →</span></a>
                    @else
                        <span class="hero2__card-where"><x-icon name="pin" :size="16" />{{ config('church.address') }}</span>
                    @endif
                </div>
                @break
            @case('date')
                @if (filled($slide['feature_text']))
                    <div class="hero2__card hero2__card--date" aria-hidden="true">
                        <span class="hero2__date-day">{{ $date[0] }}</span>
                        @isset($date[1])<span class="hero2__date-month">{{ mb_strtoupper($date[1]) }}</span>@endisset
                    </div>
                @endif
                @break
            @case('series')
                <a href="{{ $headline['url'] ?? route('watch') }}" class="hero2__card hero2__card--series">
                    <span class="hero2__play" aria-hidden="true"><x-icon name="play" :size="22" /></span>
                    <span class="stack" style="gap: 2px">
                        <span class="hero2__card-label">NOW SHOWING</span>
                        <span class="hero2__card-big">{{ trim($title.' '.$accent) }}</span>
                    </span>
                </a>
                @break
            @case('verse')
                <figure class="hero2__card hero2__card--verse">
                    <x-crown :width="44" :height="37" jewels />
                    <blockquote>"{{ trim((string) $slide['feature_text'], '"“” ') }}"</blockquote>
                    @if ($slide['feature_ref'])
                        <figcaption>{{ mb_strtoupper($slide['feature_ref']) }}</figcaption>
                    @endif
                </figure>
                @break
        @endswitch
    </div>
</article>
