@extends('layouts.app', [
    'title' => 'Events',
    'active' => 'events',
    'description' => "What's on at The Royal Tribe Embassy: worship nights, the Royal Youth Convention and regular gatherings.",
])

@section('content')
    <section class="featured-event bg-gold">
        <x-crown :width="640" :height="530" fill="var(--midnight)" jewels class="crown-bg" style="right: -140px; top: -60px; opacity: 0.07" />
        <div class="container featured-event__inner">
            <div class="stack" style="gap: 20px">
                <span class="eyebrow eyebrow--ink">FEATURED EVENT · {{ strtoupper($featured['date_label']) }}</span>
                <h1 class="display featured-event__title">{{ $featured['title'] }}</h1>
                <p class="featured-event__text">{{ $featured['summary'] }} {{ $featured['where'] }}</p>
                <div class="featured-event__ctas">
                    <a href="{{ $featured['ticket_url'] }}" class="btn btn--navy">Get your ticket</a>
                    <a href="{{ $featured['calendar_url'] }}" class="btn btn--outline-ink"><x-icon name="calendar" :size="18" />Add to calendar</a>
                </div>
            </div>
            <div class="stats">
                <div class="stat bg-navy">
                    <span class="stat__num" data-countdown="{{ $startsAt->toIso8601String() }}">{{ $daysUntil }}</span>
                    <span class="stat__label">DAYS</span>
                </div>
                <div class="stat bg-navy">
                    <span class="stat__num">{{ $featured['speakers'] }}</span>
                    <span class="stat__label">SPEAKERS</span>
                </div>
                <div class="stat bg-white stat--light">
                    <span class="stat__num">{{ $featured['entry'] }}</span>
                    <span class="stat__label">ENTRY</span>
                </div>
            </div>
        </div>
    </section>

    <section class="container section--tight stack" style="gap: 24px" data-filter-scope>
        <div class="section-head" style="align-items: center">
            <h2 class="h2">What's on</h2>
            <div role="tablist" aria-label="Month" class="months">
                @foreach ($months as $id => $label)
                    <button type="button" role="tab" class="month" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-filter="{{ $id }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="stack" style="gap: 14px">
            @foreach ($events as $e)
                <div class="card event" data-filter-item data-tag="{{ $e['month'] }}">
                    <span class="event__date">
                        <span class="event__mon">{{ $e['mon'] }}</span>
                        <span class="event__day">{{ $e['day'] }}</span>
                    </span>
                    <span class="event__info">
                        <span class="tag tag--wash event__tag">{{ $e['tag'] }}</span>
                        <span class="event__title">{{ $e['title'] }}</span>
                        <span class="event__when">{{ $e['when'] }} · {{ $e['where'] }}</span>
                    </span>
                    <span class="event__actions">
                        <a href="{{ $e['url'] }}" class="btn btn--navy btn--sm" style="height: 48px; padding: 0 20px">{{ $e['cta'] }}<span class="sr-only">: {{ $e['title'] }}</span></a>
                        <a href="{{ $e['calendar_url'] }}" class="icon-circle event__cal" aria-label="Add {{ $e['title'] }} to calendar"><x-icon name="calendar" /></a>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="container section--tight section--last stack" style="gap: 24px">
        <div class="stack" style="gap: 12px">
            <span class="eyebrow">EVERY WEEK</span>
            <h2 class="h2">Regular gatherings</h2>
        </div>
        <div class="grid-auto" style="--min: 270px; --gap: 16px">
            <div class="gathering bg-navy">
                <span class="gathering__when gathering__when--gold">SUNDAY</span>
                <span class="gathering__title">Sunday Service</span>
                <span class="gathering__text" style="color: var(--mist)">{{ config('church.sunday_time') }} · In person &amp; YouTube</span>
            </div>
            <div class="gathering card">
                <span class="gathering__when">{{ strtoupper(config('church.midweek_day')) }}</span>
                <span class="gathering__title">Midweek prayer</span>
                <span class="gathering__text">{{ config('church.midweek_time') }} · Online</span>
            </div>
            <div class="gathering card">
                <span class="gathering__when">ALL WEEK</span>
                <span class="gathering__title">House fellowships</span>
                <span class="gathering__text">Campuses &amp; neighbourhoods</span>
            </div>
            <div class="gathering bg-wash">
                <span class="gathering__when">MONTHLY</span>
                <span class="gathering__title">Unit meetings</span>
                <span class="gathering__text" style="color: var(--body)">Dates in the app</span>
            </div>
        </div>
    </section>
@endsection
