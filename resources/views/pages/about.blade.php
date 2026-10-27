@extends('layouts.app', [
    'title' => 'About',
    'active' => 'about',
    'description' => 'Who we are, what we believe and how to plan your first visit to '.config('church.short_name').'.',
])

@section('content')
    <section class="page-hero bg-navy on-dark">
        <x-crown :width="560" :height="470" jewels class="crown-bg" style="right: -120px; bottom: -150px; opacity: 0.08" />
        <div class="container page-hero__inner">
            <span class="eyebrow">ABOUT US</span>
            <h1 class="display page-hero__title">A royal tribe, raising a generation for Christ.</h1>
            <p class="page-hero__text">{{ config('church.short_name') }} is a youth parish of The Redeemed Christian Church of God, built for students, creatives and young professionals.</p>
        </div>
    </section>

    <section class="container section grid-auto story" style="--min: 440px; --gap: 48px">
        <div class="stack" style="gap: 18px">
            <span class="eyebrow">OUR STORY</span>
            <h2 class="h2">Church that feels like family.</h2>
            <p class="body-copy">[Your story: when the parish started, who started it and why. Two or three short paragraphs work best.]</p>
            <p class="body-copy">Today we gather every Sunday to worship, learn the Word and build friendships that last beyond the service. Whether you are in school, starting a career or building a business, there is a place for you here.</p>
        </div>
        <div role="img" aria-label="Photo placeholder: congregation" class="placeholder bg-ice story__photo">
            <span class="placeholder__note">[Photo: the Tribe together]</span>
            <div class="story__badge"><x-crown :width="36" :height="30" fill="var(--midnight)" /></div>
        </div>
    </section>

    <section class="container section grid-auto" style="--min: 340px">
        <div class="pillar bg-navy">
            <span class="eyebrow eyebrow--gold">OUR MISSION</span>
            <span class="pillar__text">To help young people know Christ, grow in Him and live out their royal calling.</span>
        </div>
        <div class="pillar bg-gold">
            <span class="eyebrow eyebrow--ink">OUR VISION</span>
            <span class="pillar__text">A generation of believers who lead with excellence in every sphere of life.</span>
        </div>
        <div class="pillar card">
            <span class="eyebrow">OUR VALUES</span>
            <ul class="values">
                @foreach ($about['values'] as $value)
                    <li class="{{ $loop->odd ? 'bg-wash' : 'bg-ice' }}">{{ $value }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="container section stack" style="gap: 32px">
        <div class="stack" style="gap: 12px; max-width: 680px">
            <span class="eyebrow">LEADERSHIP</span>
            <h2 class="h2">Meet our pastors.</h2>
        </div>
        <div class="grid-auto" style="--min: 260px">
            @foreach ($about['leaders'] as $leader)
                <div class="stack" style="gap: 14px">
                    <div role="img" aria-label="Photo placeholder: {{ $leader['role'] }}" class="leader__photo bg-{{ $leader['swatch'] }}">
                        <span class="leader__note">[Photo]</span>
                    </div>
                    <span class="stack" style="gap: 2px">
                        <span class="leader__name">{{ $leader['name'] }}</span>
                        <span class="leader__role">{{ $leader['role'] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="container section">
        <div class="card beliefs">
            <div class="stack" style="gap: 14px">
                <span class="eyebrow">WHAT WE BELIEVE</span>
                <h2 class="h2" style="font-size: clamp(30px, 3.6vw, 44px)">Rooted in the Word.</h2>
                <p class="beliefs__text">We hold the beliefs of The Redeemed Christian Church of God. Read the full statement of faith on the RCCG website.</p>
                <a href="{{ config('church.links.rccg_beliefs') }}" class="btn btn--outline btn--sm" style="align-self: flex-start; height: 48px">Read the full statement</a>
            </div>
            <ul class="beliefs__list">
                @foreach ($about['beliefs'] as $belief)
                    <li><x-crown :width="20" :height="20" class="beliefs__icon" />{{ $belief }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="units" class="container section stack" style="gap: 32px">
        <div class="section-head">
            <div class="stack" style="gap: 12px; max-width: 640px">
                <span class="eyebrow">UNITS</span>
                <h2 class="h2">Every gift has a place.</h2>
            </div>
            <a href="{{ route('home') }}#app" class="btn btn--navy btn--md">Join a unit in the app</a>
        </div>
        <div class="grid-auto" style="--min: 270px; --gap: 14px">
            @foreach ($about['units'] as $unit)
                <div class="card unit">
                    <span class="unit__name">{{ $unit['name'] }}</span>
                    <span class="unit__text">{{ $unit['text'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section id="visit" class="container section section--last grid-auto visit" style="--min: 460px; --gap: 24px">
        <div class="stack" style="gap: 20px">
            <span class="eyebrow">PLAN YOUR VISIT</span>
            <h2 class="h2">We saved you a seat.</h2>
            <div role="img" aria-label="Map placeholder" class="placeholder bg-ice visit__map">
                <span class="placeholder__note">[Map]</span>
                <span class="visit__pin"></span>
            </div>
            <div class="grid-auto" style="--min: 200px; --gap: 12px">
                <div class="card visit__fact">
                    <span class="visit__label">ADDRESS</span>
                    <span class="visit__value">{{ config('church.address') }}</span>
                </div>
                <div class="card visit__fact">
                    <span class="visit__label">SUNDAYS</span>
                    <span class="visit__value">{{ config('church.sunday_time') }}</span>
                </div>
            </div>
            <a href="{{ config('church.directions_url') }}" class="btn btn--gold" style="align-self: flex-start; height: 54px; font-size: 15px">Get directions</a>
        </div>
        <div class="stack" style="gap: 12px">
            <h3 class="faq__heading">Questions first-timers ask</h3>
            @foreach ($about['faqs'] as $faq)
                <details class="card faq" @if ($loop->first) open @endif>
                    <summary>
                        {{ $faq['q'] }}
                        <span class="faq__icon faq__icon--plus"><x-icon name="plus" /></span>
                        <span class="faq__icon faq__icon--minus"><x-icon name="minus" /></span>
                    </summary>
                    <p>{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endsection
