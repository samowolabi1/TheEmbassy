@php
    $links = config('church.links');
    $socials = [
        ['label' => 'YouTube', 'icon' => 'youtube', 'href' => $links['youtube']],
        ['label' => 'Instagram', 'icon' => 'instagram', 'href' => $links['instagram']],
        ['label' => 'TikTok', 'icon' => 'tiktok', 'href' => $links['tiktok']],
        ['label' => 'Facebook', 'icon' => 'facebook', 'href' => $links['facebook']],
    ];
@endphp

<footer class="foot">
    <div class="foot__inner">
        <div class="foot__signup" id="newsletter">
            <div class="foot__pitch">
                <span class="foot__title">Get the weekly declaration in your inbox.</span>
                <span>Plus announcements and new messages. One email every Monday.</span>
            </div>
            <form class="foot__form" method="POST" action="{{ route('newsletter.store') }}">
                @csrf
                <label class="foot__email">
                    <span class="sr-only">Email address</span>
                    <input type="email" name="email" required placeholder="Your email address" autocomplete="email"
                        value="{{ old('email') }}" @error('email', 'newsletter') aria-invalid="true" aria-describedby="newsletter-status" @enderror>
                </label>
                <label class="honeypot" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                <button class="btn btn--gold foot__submit" type="submit">Subscribe</button>
                @if (session('subscribed'))
                    <p class="foot__status" id="newsletter-status" role="status">You're subscribed. See you on Monday.</p>
                @endif
                @error('email', 'newsletter')
                    <p class="foot__status" id="newsletter-status" role="alert">{{ $message }}</p>
                @enderror
            </form>
        </div>

        <div class="foot__cols">
            <div class="foot__col">
                <div class="foot__logo">
                    <img src="{{ asset(config('church.brand.logo')) }}" alt="{{ config('church.name') }} logo" width="72" height="62">
                </div>
                <span class="foot__about">{{ config('church.tagline') }}</span>
                <div class="foot__socials">
                    @foreach ($socials as $s)
                        <a href="{{ $s['href'] }}" aria-label="{{ $s['label'] }}" class="icon-circle foot__social"><x-icon :name="$s['icon']" /></a>
                    @endforeach
                </div>
            </div>
            <div class="foot__col">
                <span class="foot__heading">VISIT</span>
                <span>{{ config('church.address') }}</span>
                <span>Sundays · {{ config('church.sunday_time') }}</span>
                <span>{{ config('church.midweek_day') }} · {{ config('church.midweek_time') }}</span>
                <a href="{{ route('about') }}#visit" class="foot__strong">Plan your visit →</a>
            </div>
            <div class="foot__col">
                <span class="foot__heading">EXPLORE</span>
                <a href="{{ route('about') }}">About us</a>
                <a href="{{ route('watch') }}">Sermons</a>
                <a href="{{ route('blog.index') }}">Blog</a>
                <a href="{{ route('events') }}">Events</a>
                <a href="{{ route('notices.index') }}">Notice board</a>
                <a href="{{ route('market.index') }}">{{ config('church.brand.market_name') }}</a>
            </div>
            <div class="foot__col">
                <span class="foot__heading">GET INVOLVED</span>
                <a href="{{ route('give') }}">Give</a>
                <a href="{{ route('home') }}#prayer">Prayer request</a>
                <a href="{{ route('about') }}#units">Join a unit</a>
                <a href="{{ route('notices.create') }}">Advertise with us</a>
                <a href="{{ $links['contact'] }}">Contact us</a>
            </div>
        </div>
    </div>
    <div class="foot__base">
        <div class="foot__base-inner">
            <span>© {{ now()->year }} {{ config('church.name') }}</span>
            <span class="foot__legal"><a href="{{ $links['privacy'] }}">Privacy</a><a href="{{ $links['terms'] }}">Terms</a></span>
        </div>
    </div>
</footer>
