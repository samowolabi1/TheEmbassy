@extends('layouts.app', [
    'title' => 'Watch',
    'active' => 'watch',
    'description' => 'Watch Sunday services live, catch up on sermon series and listen to the Tribe Talk podcast.',
])

@php
    $links = config('church.links');
    $listen = [
        'Spotify' => $links['spotify'],
        'Apple Podcasts' => $links['apple_podcasts'],
        'Audiomack' => $links['audiomack'],
    ];
@endphp

@section('content')
    <section class="live bg-night on-dark">
        <div class="container live__inner">
            <div class="live__main">
                <div class="player">
                    <x-crown :width="420" :height="350" class="crown-bg" style="right: -60px; bottom: -80px; opacity: 0.1" />
                    <span class="player__live"><span class="player__dot"></span>LIVE ON YOUTUBE</span>
                    <span class="player__note">[YouTube live player]</span>
                    <a href="{{ $links['youtube_live'] }}" class="player__play" aria-label="Play livestream"><x-icon name="play" :size="30" /></a>
                </div>
                <div class="live__meta">
                    <div class="stack" style="gap: 4px">
                        <h1 class="live__title">Sunday Service: Crowned for More, week 3</h1>
                        <span class="live__when">Every Sunday at {{ config('church.sunday_time') }} · 1 Peter 2:9</span>
                    </div>
                    <a href="{{ $links['youtube'] }}" class="btn btn--youtube btn--md"><x-icon name="youtube" />Subscribe</a>
                </div>
            </div>

            <aside class="upcoming">
                <span class="eyebrow">COMING UP LIVE</span>
                @foreach ($sermons['upcoming_live'] as $u)
                    <div class="upcoming__item">
                        <span class="upcoming__title">{{ $u['title'] }}</span>
                        <span class="upcoming__when">{{ $u['when'] }}</span>
                    </div>
                @endforeach
                <div class="upcoming__listen">
                    <span class="upcoming__label">Listen on the go</span>
                    <div class="upcoming__links">
                        @foreach ($listen as $label => $href)
                            <a href="{{ $href }}" class="upcoming__link">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="container section--tight stack" style="gap: 28px">
        <div class="stack" style="gap: 12px">
            <span class="eyebrow">SERIES</span>
            <h2 class="h2">Binge a whole series.</h2>
        </div>
        <div class="grid-auto" style="--min: 320px">
            @foreach ($sermons['series'] as $s)
                <a href="{{ $s['url'] }}" class="series series--{{ $s['style'] }}">
                    <span class="tag series__tag">{{ $s['label'] }}</span>
                    <span class="series__title">{{ $s['title'] }}<br><span class="series__accent">{{ $s['accent'] }}</span></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="container section--tight section--last stack" style="gap: 24px" data-filter-scope>
        <div class="section-head">
            <h2 class="h2">All messages</h2>
            <label class="search">
                <x-icon name="search" :size="18" />
                <span class="sr-only">Search messages</span>
                <input type="search" placeholder="Search by title or scripture" data-filter-search>
            </label>
        </div>
        <div role="tablist" aria-label="Filter messages" class="filters">
            @foreach ($sermons['filters'] as $id => $label)
                <button type="button" role="tab" class="filter" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-filter="{{ $id }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="grid-auto" style="--min: 300px; --gap: 22px">
            @foreach ($sermons['messages'] as $m)
                <a href="{{ $m['url'] }}" class="message" data-filter-item data-tag="{{ $m['type'] }}" data-search="{{ $m['title'] }} {{ $m['meta'] }} {{ $m['kind'] }}">
                    <div class="message__thumb bg-{{ $m['swatch'] }}">
                        <span class="icon-circle message__play"><x-icon name="play" :size="18" /></span>
                        <span class="message__len">{{ $m['length'] }}</span>
                    </div>
                    <span class="stack" style="gap: 4px">
                        <span class="message__kind">{{ $m['kind'] }}</span>
                        <span class="message__title">{{ $m['title'] }}</span>
                        <span class="message__meta">{{ $m['meta'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        <p class="empty" data-filter-empty hidden>No messages match that search yet.</p>
    </section>
@endsection
