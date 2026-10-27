<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Seller sign in · {{ config('church.brand.market_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@700;800&display=swap">
    @vite(['resources/css/admin.css'])
    @include('partials.theme')
</head>
<body class="admin">
    <main class="login">
        <div class="login__card">
            <div class="login__brand">
                <img src="{{ asset(config('church.brand.logo')) }}" alt="{{ config('church.name') }} logo" width="58" height="50">
                <span class="side__words"><span class="side__name" style="color: var(--navy)">{{ config('church.brand.wordmark') }}</span><span class="side__role" style="color: var(--bronze)">{{ config('church.brand.market_name') }} sellers</span></span>
            </div>
            <h1>Seller sign in</h1>
            @if (session('status'))
                <p class="flash" role="status" style="margin: 0">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('vendor.login.store') }}" class="form">
                @csrf
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" aria-describedby="login-error" @enderror>
                </label>
                <label class="field">Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                @error('email')
                    <p class="field-error" id="login-error" role="alert" style="margin: 0">{{ $message }}</p>
                @enderror
                <label class="login__remember"><input type="checkbox" name="remember" value="1">Keep me signed in on this device</label>
                <button type="submit" class="btn btn--navy">Sign in</button>
            </form>
            <a href="{{ route('vendor.password.request') }}" class="box__link">Forgot your password?</a>
            <a href="{{ route('market.apply') }}" class="box__link">Don't have a shop? Apply to sell</a>
        </div>
    </main>
</body>
</html>
