@php
    $user = auth()->user();
    $can = fn (string $area) => $user->canManage($area);
    $count = fn (string $area, callable $query) => $can($area) ? ($query() ?: null) : null;

    // The sidebar from the design, showing only the areas this person's role covers.
    $nav = [
        'OVERVIEW' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard'],
        ],
        'PEOPLE' => [
            ['id' => 'members', 'area' => 'members', 'label' => 'Members', 'route' => 'admin.members.index',
                'badge' => $count('members', fn () => \App\Models\Member::where('status', \App\Models\Member::PENDING)->count())],
            ['id' => 'firsttimers', 'area' => 'attendance', 'label' => 'First-timers & follow-up', 'route' => 'admin.first-timers.index',
                'badge' => $count('attendance', fn () => \App\Models\FirstTimer::whereNull('followed_up_at')->count())],
            ['id' => 'care', 'area' => 'care', 'label' => 'Follow-up & care', 'route' => 'admin.care.index',
                'badge' => $count('care', fn () => \App\Models\FollowUp::open()->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'))->count())],
            ['id' => 'attendance', 'area' => 'attendance', 'label' => 'Attendance', 'route' => 'admin.attendance.index'],
            ['id' => 'welfare', 'area' => 'welfare', 'label' => 'Welfare', 'route' => 'admin.welfare',
                'badge' => $count('welfare', fn () => \App\Models\WelfareCase::where('status', 'awaiting_pastor')->count())],
            ['id' => 'units', 'area' => 'units', 'label' => 'Units & fellowships', 'route' => 'admin.units.index'],
            ['id' => 'subscribers', 'area' => 'subscribers', 'label' => 'Newsletter subscribers', 'route' => 'admin.subscribers.index'],
        ],
        'MONEY' => [
            ['id' => 'finance', 'area' => 'finance', 'label' => 'Giving & tokens', 'route' => 'admin.finance.index'],
            ['id' => 'expenses', 'area' => 'expenses', 'label' => 'Expenses & budgets', 'route' => 'admin.expenses.index',
                'badge' => $count('expenses', fn () => \App\Models\Expense::where('status', \App\Models\Expense::PENDING)->count())],
        ],
        'MARKET' => [
            ['id' => 'market', 'area' => 'market', 'label' => config('church.brand.market_name'), 'route' => 'admin.market.index',
                'badge' => $count('market', fn () => \App\Models\Shop::where('status', \App\Models\Shop::PENDING)->count())],
        ],
        'CONTENT' => [
            ['id' => 'content', 'area' => 'content', 'label' => 'Content & announcements', 'route' => 'admin.content'],
            ['id' => 'notices', 'area' => 'notices', 'label' => 'Notice board', 'route' => 'admin.notices.index',
                'badge' => $count('notices', fn () => \App\Models\Notice::where('status', \App\Models\Notice::IN_REVIEW)->count())],
            ['id' => 'live', 'area' => 'sermons', 'label' => 'Sermons & live', 'route' => 'admin.sermons.index'],
            ['id' => 'events', 'area' => 'events', 'label' => 'Events & tickets', 'route' => 'admin.events.index'],
            ['id' => 'prayer', 'area' => 'prayer', 'label' => 'Prayer requests', 'route' => 'admin.prayer-requests.index',
                'badge' => $count('prayer', fn () => \App\Models\PrayerRequest::unread()->count())],
            ['id' => 'messages', 'area' => 'messages', 'label' => 'Contact messages', 'route' => 'admin.messages.index',
                'badge' => $count('messages', fn () => \App\Models\ContactMessage::whereNull('read_at')->count())],
        ],
        'SYSTEM' => [
            ['id' => 'settings', 'area' => 'settings', 'label' => 'Settings & roles', 'route' => 'admin.settings.edit'],
        ],
    ];
    $nav = collect($nav)
        ->map(fn ($items) => array_values(array_filter($items, fn ($item) => ! isset($item['area']) || $can($item['area']))))
        ->filter()
        ->all();
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
                        @if (\Illuminate\Support\Facades\Route::has($item['route']))
                            <a href="{{ route($item['route']) }}" class="side__link" @if ($item['id'] === $active) aria-current="page" @endif>
                                {{ $item['label'] }}
                                @if ($item['badge'] ?? null)
                                    <span class="side__badge"><span class="sr-only">to do: </span>{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @else
                            <span class="side__link side__link--soon">{{ $item['label'] }} <span class="side__soon">SOON</span></span>
                        @endif
                    @endforeach
                @endforeach
            </nav>

            <div class="side__user">
                <span class="avatar side__avatar">{{ $initials }}</span>
                <span class="side__who"><strong>{{ $user->name }}</strong>{{ $user->roleName() }}</span>
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
