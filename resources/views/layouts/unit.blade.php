@php
    $user = auth()->user();
    // Leaders see the whole unit area; media team members only see the media tools.
    $nav = $user->can('lead-unit', $unit) ? [
        ['id' => 'overview', 'label' => 'Overview', 'route' => 'unit.show'],
        ['id' => 'members', 'label' => 'Members', 'route' => 'unit.members'],
        ['id' => 'attendance', 'label' => 'Attendance', 'route' => 'unit.attendance'],
        ['id' => 'meetings', 'label' => 'Meetings', 'route' => 'unit.meetings.index'],
        ['id' => 'rota', 'label' => 'Rota', 'route' => 'unit.rota'],
        ['id' => 'messages', 'label' => 'Messages', 'route' => 'unit.messages'],
    ] : [];
    if ($unit->isWelfare() && $user->can('manage-welfare', $unit)) {
        $nav[] = ['id' => 'welfare', 'label' => 'Welfare cases', 'route' => 'unit.welfare.index'];
        $nav[] = ['id' => 'celebrations', 'label' => 'Celebrations', 'route' => 'unit.welfare.celebrations'];
    }
    if ($unit->isMedia() && $user->can('media-team', $unit)) {
        $nav[] = ['id' => 'equipment', 'label' => 'Equipment', 'route' => 'unit.equipment.index'];
        $nav[] = ['id' => 'setup', 'label' => 'Service setup & streaming', 'route' => 'unit.setup.index'];
        $nav[] = ['id' => 'social', 'label' => 'Social media', 'route' => 'unit.social.index'];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $unit->name }}</title>
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
            <a href="{{ $nav ? route($nav[0]['route'], $unit) : route('account') }}" class="side__brand">
                <span class="side__logo"><img src="{{ asset(config('church.brand.logo')) }}" alt="" width="40" height="38"></span>
                <span class="side__words"><span class="side__name">{{ $unit->name }}</span><span class="side__role">{{ $unit->isLedBy($user->member) ? ($unit->hod_member_id === $user->member?->id ? 'Head of department' : 'Assistant HOD') : ($user->member?->unit_id === $unit->id ? 'Team member' : 'Unit area') }}</span></span>
            </a>
            <nav class="side__nav" aria-label="{{ $unit->name }}">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route'], $unit) }}" class="side__link" @if ($item['id'] === $active) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ route('account') }}" class="side__link">My account</a>
                @if ($user->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="side__link">Church admin</a>
                @endif
            </nav>
            <div class="side__user">
                @php($displayName = $user->member?->name ?? $user->name)
                <span class="avatar side__avatar">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                <span class="side__who"><strong>{{ $displayName }}</strong>{{ $unit->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
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
