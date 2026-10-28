@extends('layouts.app', ['active' => 'home'])

@php
    $links = config('church.links');
    $testimony = $home['testimony'];
    $followLinks = [
        ['label' => 'Instagram', 'icon' => 'instagram', 'href' => $links['instagram']],
        ['label' => 'YouTube', 'icon' => 'youtube', 'href' => $links['youtube']],
        ['label' => 'TikTok', 'icon' => 'tiktok', 'href' => $links['tiktok']],
        ['label' => 'Facebook', 'icon' => 'facebook', 'href' => $links['facebook']],
        ['label' => 'X', 'icon' => 'x', 'href' => $links['x']],
        ['label' => 'WhatsApp', 'icon' => 'whatsapp', 'href' => $links['whatsapp']],
    ];
@endphp

@section('content')
    {{-- Hero carousel --}}
    <section class="hero on-dark" aria-roledescription="carousel" aria-label="Highlights" data-carousel>
        <div class="container hero__inner">
            <div class="hero__slides">
                <div class="slide" role="group" aria-roledescription="slide" aria-label="1 of 4" data-slide>
                    <div class="slide__copy">
                        <span class="slide__kicker slide__kicker--outline">RCCG YOUTH CHURCH</span>
                        <h1 class="display slide__title">Young. Royal.<br><span class="accent">Rooted in Christ.</span></h1>
                        <p class="slide__text">A home for students, creatives and young professionals. Come as you are and grow into who God called you to be.</p>
                        <div class="slide__ctas">
                            <a href="{{ route('about') }}#visit" class="btn btn--gold">Plan your visit</a>
                            <a href="{{ $links['youtube'] }}" class="btn btn--outline-light"><x-icon name="play" :size="16" />Watch on YouTube</a>
                        </div>
                    </div>
                    <div role="img" aria-label="Photo placeholder: Sunday worship" class="placeholder slide__media bg-mid hero__bordered">
                        <span class="placeholder__note placeholder__note--right">[Photo: Sunday worship]</span>
                        <div class="next-card">
                            <span class="next-card__label">NEXT GATHERING</span>
                            <span class="next-card__time">Sunday · {{ config('church.sunday_time') }}</span>
                            <span class="next-card__where">{{ config('church.address') }}</span>
                        </div>
                    </div>
                </div>

                <div class="slide" role="group" aria-roledescription="slide" aria-label="2 of 4" data-slide hidden>
                    <div class="slide__copy">
                        <span class="slide__kicker slide__kicker--gold">SAT 14 NOVEMBER</span>
                        <h1 class="display slide__title">Royal Youth<br><span class="accent">Convention</span></h1>
                        <p class="slide__text">Worship, honest conversations and the biggest Tribe hangout of the year. Bring a friend.</p>
                        <div class="slide__ctas">
                            <a href="{{ route('events') }}" class="btn btn--gold">Get your ticket</a>
                            <a href="{{ route('events') }}" class="btn btn--outline-light">Event details</a>
                        </div>
                    </div>
                    <div role="img" aria-label="Photo placeholder: convention artwork" class="placeholder slide__media bg-gold">
                        <x-crown :width="420" :height="350" fill="var(--midnight)" jewels class="crown-bg" style="right: -60px; bottom: -70px; opacity: 0.1" />
                        <span class="placeholder__note placeholder__note--right">[Convention flyer]</span>
                        <span class="slide__date">14<br><span>NOV</span></span>
                    </div>
                </div>

                <div class="slide" role="group" aria-roledescription="slide" aria-label="3 of 4" data-slide hidden>
                    <div class="slide__copy">
                        <span class="slide__kicker slide__kicker--outline">NEW SERMON SERIES</span>
                        <h1 class="display slide__title">{{ $headline['series_title'] }}<br><span class="accent">{{ $headline['series_accent'] }}</span></h1>
                        <p class="slide__text">A series on identity, purpose and living as a royal priesthood. New episodes every Sunday.</p>
                        <div class="slide__ctas">
                            <a href="{{ $headline['url'] ?? route('watch') }}" class="btn btn--gold"><x-icon name="play" :size="16" />Watch {{ $headline['week'] ? 'week '.$headline['week'] : 'now' }}</a>
                            <a href="{{ route('watch') }}" class="btn btn--outline-light">All episodes</a>
                        </div>
                    </div>
                    <div role="img" aria-label="Photo placeholder: series artwork" class="placeholder slide__media bg-night hero__bordered">
                        <x-crown :width="360" :height="300" jewels band class="crown-bg slide__crown" style="opacity: 0.9" />
                        <span class="placeholder__note placeholder__note--right">[Series artwork]</span>
                        <span class="slide__series">{{ strtoupper(trim($headline['series_title'].' '.$headline['series_accent'])) }}</span>
                    </div>
                </div>

                <div class="slide" role="group" aria-roledescription="slide" aria-label="4 of 4" data-slide hidden>
                    <div class="slide__copy">
                        <span class="slide__kicker slide__kicker--outline">THIS WEEK'S DECLARATION</span>
                        <h1 class="display slide__title">I am chosen.<br>I am royal.<br><span class="accent">I am His.</span></h1>
                        <div class="slide__ctas">
                            <a href="#declaration" class="btn btn--gold">Read the full declaration</a>
                            <button type="button" class="btn btn--outline-light" data-share data-share-text="I am chosen. I am royal. I am His. 1 Peter 2:9">Share it</button>
                        </div>
                    </div>
                    <div class="slide__media slide__verse">
                        <x-crown :width="64" :height="54" jewels />
                        <span class="slide__quote">"But you are a chosen people, a royal priesthood, a holy nation, God's special possession."</span>
                        <span class="slide__ref">1 PETER 2:9</span>
                    </div>
                </div>
            </div>

            <div class="hero__controls">
                <div class="hero__progress">
                    <span class="hero__count"><span data-slide-num>01</span> <span class="hero__of">/ 04</span></span>
                    <div class="hero__dots">
                        @foreach (range(0, 3) as $n)
                            <button type="button" class="dot" aria-label="Go to slide {{ $n + 1 }}" @if ($n === 0) aria-current="true" @endif data-dot="{{ $n }}"><span class="dot__fill"></span></button>
                        @endforeach
                    </div>
                </div>
                <div class="hero__arrows">
                    <button type="button" class="arrow arrow--ghost" aria-label="Previous slide" data-prev><x-icon name="chevron-left" /></button>
                    <button type="button" class="arrow arrow--solid" aria-label="Next slide" data-next><x-icon name="chevron-right" /></button>
                    <button type="button" class="hero__pause" data-pause aria-pressed="false">Pause</button>
                </div>
            </div>
        </div>
    </section>

    {{-- Quick links --}}
    <section class="container quick">
        <div class="grid-auto" style="--min: 250px; --gap: 14px">
            <a href="{{ route('about') }}#visit" class="card quick__item">
                <span class="icon-tile tile-wash"><x-icon name="pin" :size="24" /></span>
                <span class="quick__text"><span class="quick__title">Plan a visit</span><span class="quick__sub">Service times &amp; directions</span></span>
            </a>
            <a href="{{ $links['youtube'] }}" class="card quick__item">
                <span class="icon-tile tile-ice"><x-icon name="youtube" :size="24" /></span>
                <span class="quick__text"><span class="quick__title">Watch on YouTube</span><span class="quick__sub">Live every Sunday</span></span>
            </a>
            <a href="#prayer" class="card quick__item">
                <span class="icon-tile tile-wash"><x-icon name="prayer" :size="24" /></span>
                <span class="quick__text"><span class="quick__title">Prayer request</span><span class="quick__sub">Our team will pray with you</span></span>
            </a>
            <a href="{{ route('give') }}" class="quick__item quick__item--navy">
                <span class="icon-tile tile-gold"><x-icon name="heart" :size="24" /></span>
                <span class="quick__text"><span class="quick__title">Give online</span><span class="quick__sub">Card, transfer or USSD</span></span>
            </a>
        </div>
    </section>

    {{-- Declaration and announcements (edited in the admin dashboard) --}}
    <section id="declaration" class="container section grid-auto" style="--min: 460px">
        @if ($declaration)
            <div class="panel bg-gold stack declaration">
                <x-crown :width="300" :height="250" fill="var(--midnight)" class="crown-bg" style="right: -60px; top: -50px; opacity: 0.07" />
                <span class="eyebrow eyebrow--ink">WEEKLY DECLARATION · WEEK OF {{ strtoupper($declaration->week_of->format('j F')) }}</span>
                <h2 class="h2 h2--sm declaration__title">Say it out loud this week.</h2>
                <ol class="declaration__list">
                    @foreach ($declaration->lines as $line)
                        <li><span class="declaration__num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $line }}</li>
                    @endforeach
                </ol>
                <button type="button" class="btn btn--navy btn--md declaration__share" data-share data-share-text="{{ implode("\n", $declaration->lines) }}">Share the declaration</button>
            </div>
        @endif
        <div class="card panel stack announcements" style="padding: 36px">
            <div class="announcements__head">
                <h2 class="announcements__title">Announcements</h2>
            </div>
            @forelse ($announcements as $a)
                @if ($a->url)
                    <a href="{{ $a->url }}" class="announcement">
                @else
                    <div class="announcement">
                @endif
                    <span class="tag tag--{{ $a->tone }} announcement__tag">{{ $a->tag }}</span>
                    <span class="announcement__text">
                        <span class="announcement__title">{{ $a->title }}</span>
                        <span class="announcement__body">{{ $a->body }}</span>
                    </span>
                {!! $a->url ? '</a>' : '</div>' !!}
            @empty
                <p class="announcement__body" style="margin: 0">No announcements right now. Check back soon.</p>
            @endforelse
        </div>
    </section>

    {{-- Latest messages --}}
    <section class="container section stack" style="gap: 32px">
        <div class="section-head">
            <div class="stack" style="gap: 12px">
                <span class="eyebrow">WATCH &amp; LISTEN</span>
                <h2 class="h2 h2--lg">Latest messages</h2>
            </div>
            <a href="{{ $links['youtube'] }}" class="btn btn--youtube btn--md"><x-icon name="youtube" />Subscribe on YouTube</a>
        </div>
        <div class="grid-auto" style="--min: 420px; --gap: 24px">
            <a href="{{ $headline['url'] ?? route('watch') }}" class="feature-message bg-navy">
                <x-crown :width="380" :height="320" jewels class="crown-bg" style="right: -80px; top: -40px; opacity: 0.12" />
                <span class="tag tag--gold" style="position: relative">NEW SERIES</span>
                <div class="feature-message__body">
                    <span class="feature-message__title">{{ $headline['series_title'] }}<br><span class="accent">{{ $headline['series_accent'] }}</span></span>
                    <span class="feature-message__now">
                        <span class="icon-circle feature-message__play"><x-icon name="play" /></span>
                        <span class="stack">
                            <span class="feature-message__name">@if ($headline['week'])Week {{ $headline['week'] }}: @endif{{ $headline['title'] ?? 'Coming soon' }}</span>
                            <span class="feature-message__meta">{{ collect([$headline['scripture'], $headline['length'], 'YouTube'])->filter()->join(' · ') }}</span>
                        </span>
                    </span>
                </div>
            </a>
            <div class="stack" style="gap: 14px">
                @foreach ($sideMessages as $m)
                    <a href="{{ $m['url'] }}" class="card message-row">
                        <div class="message-row__thumb bg-{{ $m['swatch'] }}">
                            <x-icon :name="$m['type'] === 'podcast' ? 'headphones' : 'play'" :size="22" />
                        </div>
                        <span class="stack" style="gap: 4px">
                            <span class="message-row__kind">{{ $m['type'] === 'podcast' ? 'PODCAST' : strtoupper($m['meta']) }}</span>
                            <span class="message-row__title">{{ $m['title'] }}</span>
                            <span class="message-row__len">{{ $m['length'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- From the blog --}}
    <section class="container section stack" style="gap: 28px">
        <div class="section-head">
            <div class="stack" style="gap: 12px">
                <span class="eyebrow">FROM THE BLOG</span>
                <h2 class="h2 h2--lg">Real talk for real life.</h2>
            </div>
            <a href="{{ route('blog.index') }}" class="btn btn--outline btn--md">All articles</a>
        </div>
        <div class="filters">
            <a href="{{ route('blog.index') }}" class="filter topic-link" aria-current="page">All topics</a>
            @foreach ($topics as $topic)
                <a href="{{ route('blog.index', ['topic' => $topic]) }}" class="filter topic-link">{{ $topic }}</a>
            @endforeach
        </div>
        <div class="grid-auto" style="--min: 320px; --gap: 22px">
            @foreach ($posts as $post)
                <x-post-card :post="$post" />
            @endforeach
        </div>
    </section>

    {{-- Upcoming events --}}
    <section class="container section">
        <div class="events-panel bg-navy on-dark">
            <x-crown :width="360" :height="300" class="crown-bg" style="left: -80px; bottom: -110px; opacity: 0.08" />
            <div class="stack events-panel__copy">
                <span class="eyebrow">UPCOMING EVENTS</span>
                <h2 class="display events-panel__title">Don't miss what's next.</h2>
                <span class="events-panel__text">RSVP in the app and get a reminder before each event.</span>
                <a href="{{ route('events') }}" class="btn btn--gold" style="align-self: flex-start; margin-top: 6px">See the calendar</a>
            </div>
            <div class="stack events-panel__list">
                @forelse ($events as $e)
                    <a href="{{ route('events.show', $e) }}" @class(['event-row', 'event-row--gold' => $e->is_featured])>
                        <span class="date-tile">
                            <span class="date-tile__mon">{{ strtoupper($e->starts_at->format('M')) }}</span>
                            <span class="date-tile__day">{{ $e->starts_at->format('d') }}</span>
                        </span>
                        <span class="stack" style="flex: 1">
                            <span class="event-row__title">{{ $e->title }}</span>
                            <span class="event-row__when">{{ $e->starts_at->format('D · g:i A') }}@if ($e->is_featured) · Booking open @endif</span>
                        </span>
                    </a>
                @empty
                    <span class="events-panel__text">New events are coming soon.</span>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Member market --}}
    <section id="market" class="container section stack" style="gap: 32px">
        <div class="section-head">
            <div class="stack" style="gap: 12px; max-width: 640px">
                <span class="eyebrow">{{ strtoupper(config('church.brand.market_name')) }}</span>
                <h2 class="h2 h2--lg">Shop from the family.</h2>
                <span class="lede">Every order supports a member's business and the work of the church.</span>
            </div>
            <a href="{{ route('market.index') }}" class="btn btn--navy btn--md">Visit the market</a>
        </div>
        @if ($products->isEmpty())
            <div class="card notices-empty">
                <p class="lede" style="margin: 0">Members' shops are opening soon. Run a business? Sell to the family.</p>
                <a href="{{ route('market.apply') }}" class="btn btn--outline btn--md">Open a shop</a>
            </div>
        @else
            <div class="grid-auto" style="--min: 230px; --gap: 18px">
                @foreach ($products as $product)
                    @include('market.product-card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Featured paid notices from other churches and businesses --}}
    @if ($notices->isNotEmpty())
        <section class="container section stack" style="gap: 28px">
            <div class="section-head">
                <div class="stack" style="gap: 12px">
                    <span class="eyebrow">NOTICE BOARD</span>
                    <h2 class="h2 h2--lg">Around the community.</h2>
                </div>
                <a href="{{ route('notices.index') }}" class="btn btn--outline btn--md">All notices</a>
            </div>
            <div class="grid-auto" style="--min: 320px; --gap: 22px">
                @foreach ($notices as $notice)
                    @include('notices.card', ['notice' => $notice])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Prayer request and testimony --}}
    <section id="prayer" class="container section grid-auto" style="--min: 460px">
        <div class="card panel stack prayer" style="padding: 40px">
            <span class="eyebrow">PRAYER REQUEST</span>
            <h2 class="h2 h2--sm">How can we pray with you?</h2>
            @if (session('prayer_sent'))
                <div class="prayer__sent stack" role="status">
                    <span class="prayer__sent-title">We've received it. We're praying with you.</span>
                    <span class="prayer__sent-text">Someone from our pastoral team will reach out if you left your details.</span>
                    <a href="{{ route('home') }}#prayer" class="btn btn--outline btn--sm" style="align-self: flex-start">Send another</a>
                </div>
            @else
                <form class="stack prayer__form" method="POST" action="{{ route('prayer-requests.store') }}">
                    @csrf
                    <label class="field">Your name (optional)
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Tolu" autocomplete="name" maxlength="100">
                    </label>
                    <label class="field">Phone or email (optional)
                        <input type="text" name="contact" value="{{ old('contact') }}" placeholder="So a pastor can follow up" maxlength="150">
                    </label>
                    <label class="field">Your prayer request
                        <textarea name="request" required maxlength="5000" placeholder="Share as much or as little as you like"
                            @error('request') aria-invalid="true" aria-describedby="prayer-error" @enderror>{{ old('request') }}</textarea>
                    </label>
                    @error('request')
                        <p class="prayer__error" id="prayer-error" role="alert">{{ $message }}</p>
                    @enderror
                    <label class="prayer__private">
                        <input type="checkbox" name="is_private" value="1" @checked(old('is_private'))>Keep it private: only the pastoral team will see it
                    </label>
                    <label class="honeypot" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    <button type="submit" class="btn btn--navy prayer__submit">Send prayer request</button>
                </form>
            @endif
        </div>
        <figure class="panel bg-navy testimony">
            <x-crown :width="52" :height="44" />
            <blockquote class="testimony__quote">"{{ trim($testimony['quote'], '"“” ') }}"</blockquote>
            <figcaption class="testimony__who">
                @if (! empty($testimony['photo']))
                    <img src="{{ \App\Models\PageContent::photoUrl($testimony['photo']) }}" alt="" class="avatar testimony__avatar" style="object-fit: cover" width="48" height="48" loading="lazy">
                @else
                    <span class="avatar testimony__avatar">{{ $testimony['initials'] }}</span>
                @endif
                <span class="stack">
                    <span class="testimony__name">{{ $testimony['name'] }}</span>
                    <span class="testimony__role">{{ $testimony['role'] }}</span>
                </span>
            </figcaption>
        </figure>
    </section>

    {{-- Give and the app --}}
    <section id="app" class="container section grid-auto" style="--min: 380px">
        <div class="panel bg-gold stack" style="gap: 16px">
            <span class="eyebrow eyebrow--ink">GIVE</span>
            <h2 class="cta-title">Honour God with your increase.</h2>
            <p class="cta-text cta-text--strong">Tithes, offerings, building fund and missions, by card, bank transfer or USSD.</p>
            <a href="{{ route('give') }}" class="btn btn--navy" style="align-self: flex-start; height: 54px; font-size: 15px">Give online</a>
        </div>
        <div class="panel bg-ice stack" style="gap: 16px">
            <span class="eyebrow">{{ strtoupper(config('church.brand.app_name')) }}</span>
            <h2 class="cta-title">Church in your pocket.</h2>
            <p class="cta-text">Watch live, take sermon notes, join the prayer wall, pay your token and shop from members.</p>
            <div class="store-links">
                <a href="{{ $links['app_store'] }}" class="store-link">App Store</a>
                <a href="{{ $links['google_play'] }}" class="store-link">Google Play</a>
            </div>
        </div>
    </section>

    {{-- Follow --}}
    <section class="container section section--last follow">
        <span class="eyebrow">FOLLOW THE TRIBE</span>
        <h2 class="h2 h2--lg">{{ config('church.handle') }} everywhere.</h2>
        <div class="follow__links">
            @foreach ($followLinks as $s)
                <a href="{{ $s['href'] }}" class="follow__link"><x-icon :name="$s['icon']" :size="22" />{{ $s['label'] }}</a>
            @endforeach
        </div>
    </section>
@endsection
