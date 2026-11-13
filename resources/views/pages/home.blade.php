@extends('layouts.app', ['active' => 'home'])

@php
    $links = config('church.links');
    $testimony = $home['testimony'];
    // Slides staff haven't hidden, and whose "show until" date hasn't passed.
    $slides = collect($home['slides'] ?? [])
        ->reject(fn ($s) => ! empty($s['hidden']) || (! empty($s['show_until']) && \Illuminate\Support\Carbon::parse($s['show_until'])->endOfDay()->isPast()))
        ->values();
    if ($slides->isEmpty()) {
        $slides = collect(config('content.home.slides'))->take(1);
    }
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
                @foreach ($slides as $i => $slide)
                    @include('pages.partials.hero-slide', ['slide' => $slide, 'i' => $i, 'total' => $slides->count()])
                @endforeach
            </div>

            @if ($slides->count() > 1)
            <div class="hero__controls">
                <div class="hero__progress">
                    <span class="hero__count"><span data-slide-num>01</span> <span class="hero__of">/ {{ str_pad((string) $slides->count(), 2, '0', STR_PAD_LEFT) }}</span></span>
                    <div class="hero__dots">
                        @foreach (range(0, $slides->count() - 1) as $n)
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
            @endif
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
                <span class="events-panel__text">RSVP online and add each event to your calendar.</span>
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
            @auth
                @if (auth()->user()->member?->status === 'active')
                    <p class="prayer__sent-text" style="margin: 0">You're signed in: <a href="{{ route('account.prayer') }}" class="accent-bronze">send it from your account</a> to follow it up, hear back from the prayer team, and let the church pray with you.</p>
                @endif
            @endauth
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
            <p class="cta-text cta-text--strong">Tithes and offerings by bank transfer, in naira or other currencies. Donations by transfer or card.</p>
            <a href="{{ route('give') }}" class="btn btn--navy" style="align-self: flex-start; height: 54px; font-size: 15px">How to give</a>
        </div>
        <div class="panel bg-ice stack" style="gap: 16px">
            <span class="eyebrow">{{ strtoupper(config('church.brand.app_name')) }}</span>
            <h2 class="cta-title">Church in your pocket.</h2>
            <p class="cta-text">Put the Tribe on your home screen: watch live, check in on Sundays and earn tokens to spend in the {{ config('church.brand.market_name') }}, and get announcements as notifications.</p>
            <div class="app-actions">
                <button type="button" class="btn btn--navy" data-install aria-controls="install-hint" hidden>Install the app</button>
                <button type="button" class="btn btn--outline" data-push-toggle data-on-label="Turn on notifications" data-off-label="Turn off notifications" hidden>Turn on notifications</button>
                <span class="app-actions__status" data-push-status aria-live="polite"></span>
                <span class="app-actions__status" data-installed hidden>You're using the app.</span>
            </div>
            <p id="install-hint" class="cta-text" style="font-size: 14px" hidden>On iPhone: tap the Share button in Safari, then "Add to Home Screen". On Android: open the browser menu and choose "Install app" or "Add to Home screen".</p>
            @php $stores = array_filter(['App Store' => $links['app_store'], 'Google Play' => $links['google_play']], fn ($url) => $url && $url !== '#'); @endphp
            @if ($stores)
                <div class="store-links">
                    @foreach ($stores as $label => $url)
                        <a href="{{ $url }}" class="store-link">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
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
