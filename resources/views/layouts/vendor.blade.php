@php
    $user = auth()->user();
    $shop = $user->shop;
    $nav = [
        ['id' => 'orders', 'label' => 'Orders', 'route' => 'vendor.orders.index'],
        ['id' => 'products', 'label' => 'Products', 'route' => 'vendor.products.index'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $shop->name }}</title>
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
            <a href="{{ route('vendor.orders.index') }}" class="side__brand">
                <span class="side__logo"><img src="{{ asset(config('church.brand.logo')) }}" alt="" width="40" height="38"></span>
                <span class="side__words"><span class="side__name">{{ $shop->name }}</span><span class="side__role">{{ config('church.brand.market_name') }}</span></span>
            </a>
            <nav class="side__nav" aria-label="Shop">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" class="side__link" @if ($item['id'] === $active) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ route('market.shop', $shop) }}" class="side__link">View my shop</a>
            </nav>
            <div class="side__user">
                <span class="avatar side__avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="side__who"><strong>{{ $user->name }}</strong>Shop owner</span>
                <form method="POST" action="{{ route('vendor.logout') }}">
                    @csrf
                    <button type="submit" class="side__signout" aria-label="Sign out"><x-icon name="sign-out" :size="18" /></button>
                </form>
            </div>
        </aside>
        <main id="main" class="admin-main">
            @if (session('status'))
                <p class="flash" role="status">{{ session('status') }}</p>
            @endif
            @unless ($shop->canSell())
                <p class="flash" style="background: var(--wash); color: var(--navy)" role="status">Your payout account isn't set up yet, so buyers can see your products but can't pay online. The church office is sorting it out.</p>
            @endunless
            @yield('content')
        </main>
    </div>
</body>
</html>
