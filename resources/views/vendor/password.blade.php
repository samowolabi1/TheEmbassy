<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Set your password · {{ config('church.brand.market_name') }}</title>
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
            <h1>Set your password</h1>
            <form method="POST" action="{{ route('vendor.password.update') }}" class="form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">New password
                    <input type="password" name="password" required minlength="10" autocomplete="new-password">
                    <span class="field__hint">At least 10 characters.</span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Type it again
                    <input type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
                <button type="submit" class="btn btn--navy">Save password</button>
            </form>
        </div>
    </main>
</body>
</html>
