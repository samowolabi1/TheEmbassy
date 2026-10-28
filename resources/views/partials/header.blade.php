@php
    $nav = collect(config('church.nav'))->map(fn ($l) => [
        ...$l,
        'href' => route($l['route']).(isset($l['fragment']) ? '#'.$l['fragment'] : ''),
    ]);
    $banner = config('church.banner');
@endphp

<div class="site-header">
    @if ($banner)
        <div class="banner">
            <div class="banner__inner">
                <span class="banner__text"><span class="banner__new">NEW</span>{{ $banner['text'] }}</span>
                <a href="{{ route($banner['route']) }}" class="banner__cta">{{ $banner['cta'] }} →</a>
            </div>
        </div>
    @endif

    <header class="head">
        <div class="head__inner">
            <a href="{{ route('home') }}" class="brand" aria-label="{{ config('church.short_name') }} home">
                <img class="brand__logo" src="{{ asset(config('church.brand.logo')) }}" alt="" width="58" height="50">
                <span class="brand__words">
                    <span class="brand__name">{{ config('church.brand.wordmark') }}</span>
                    <span class="brand__sub">{{ config('church.brand.wordmark_sub') }}</span>
                </span>
            </a>

            <nav class="nav-desktop" aria-label="Primary">
                @foreach ($nav as $link)
                    <a href="{{ $link['href'] }}" @if ($link['id'] === $active) aria-current="page" @endif>{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="head__actions">
                <a class="btn btn--outline head__btn head__login" href="{{ auth()->check() ? route('account') : route('login') }}">{{ auth()->check() ? 'My account' : 'Log in' }}</a>
                <a class="btn btn--navy head__btn" href="{{ route('give') }}">Give</a>
                <button class="burger" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu" data-menu-toggle>
                    <span data-icon-open><x-icon name="menu" :size="22" /></span>
                    <span data-icon-close hidden><x-icon name="close" :size="22" /></span>
                </button>
            </div>
        </div>

        <nav id="mobile-nav" class="nav-mobile" aria-label="Mobile" hidden>
            @foreach ($nav as $link)
                <a href="{{ $link['href'] }}" @if ($link['id'] === $active) aria-current="page" @endif>
                    {{ $link['label'] }}
                    <x-icon name="chevron-right" :size="18" />
                </a>
            @endforeach
            <div class="nav-mobile__actions">
                <a class="btn btn--outline" href="{{ auth()->check() ? route('account') : route('login') }}">{{ auth()->check() ? 'My account' : 'Log in' }}</a>
                <a class="btn btn--gold" href="{{ route('watch') }}">Watch live</a>
            </div>
        </nav>
    </header>
</div>
