@php
    $user = auth()->user();
    $unreadPrayer = \App\Models\PrayerRequest::unread()->count();
    $noticesToReview = \App\Models\Notice::where('status', \App\Models\Notice::IN_REVIEW)->count();

    // The sidebar from the design. Sections without a `route` aren't built yet and show as "Soon".
    $nav = [
        'OVERVIEW' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard'],
        ],
        'PEOPLE' => [
            ['id' => 'members', 'label' => 'Members'],
            ['id' => 'firsttimers', 'label' => 'First-timers & follow-up'],
            ['id' => 'attendance', 'label' => 'Attendance'],
            ['id' => 'units', 'label' => 'Units & communities'],
            ['id' => 'subscribers', 'label' => 'Newsletter subscribers', 'route' => 'admin.subscribers.index'],
        ],
        'MONEY' => [
            ['id' => 'finance', 'label' => 'Giving & tokens'],
            ['id' => 'expenses', 'label' => 'Expenses & budgets'],
        ],
        'MARKET' => [
            ['id' => 'market', 'label' => config('church.brand.market_name')],
        ],
        'CONTENT' => [
            ['id' => 'content', 'label' => 'Content & announcements', 'route' => 'admin.content'],
            ['id' => 'notices', 'label' => 'Notice board', 'route' => 'admin.notices.index', 'badge' => $noticesToReview ?: null],
            ['id' => 'live', 'label' => 'Sermons & live'],
            ['id' => 'events', 'label' => 'Events & tickets'],
            ['id' => 'prayer', 'label' => 'Prayer requests', 'route' => 'admin.prayer-requests.index', 'badge' => $unreadPrayer ?: null],
        ],
        'SYSTEM' => [
            ['id' => 'settings', 'label' => 'Settings & roles'],
        ],
    ];
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · Church admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@700;800&display=swap">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @include('partials.theme')
</head>
<body class="admin">
    <a class="skip-link" href="#main">Skip to content</a>
    <div class="admin-shell">
        <aside class="side">
            <a href="{{ route('admin.dashboard') }}" class="side__brand">
                <span class="side__logo"><img src="{{ asset(config('church.brand.logo')) }}" alt="" width="40" height="38"></span>
                <span class="side__words"><span class="side__name">{{ config('church.brand.wordmark') }}</span><span class="side__role">Church admin</span></span>
            </a>

            <nav class="side__nav" aria-label="Admin">
                @foreach ($nav as $group => $items)
                    <span class="side__label">{{ $group }}</span>
                    @foreach ($items as $item)
                        @isset($item['route'])
                            <a href="{{ route($item['route']) }}" class="side__link" @if ($item['id'] === $active) aria-current="page" @endif>
                                {{ $item['label'] }}
                                @if ($item['badge'] ?? null)
                                    <span class="side__badge"><span class="sr-only">to do: </span>{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @else
                            <span class="side__link side__link--soon">{{ $item['label'] }} <span class="side__soon">SOON</span></span>
                        @endisset
                    @endforeach
                @endforeach
            </nav>

            <div class="side__user">
                <span class="avatar side__avatar">{{ $initials }}</span>
                <span class="side__who"><strong>{{ $user->name }}</strong>Church admin</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="side__signout" aria-label="Sign out"><x-icon name="sign-out" :size="18" /></button>
                </form>
            </div>
        </aside>

        <main id="main" class="admin-main">
            @if (session('status'))
                <p class="flash" role="status">{{ session('status') }}</p>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
