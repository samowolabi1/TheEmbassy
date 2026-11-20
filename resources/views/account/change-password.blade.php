@extends('layouts.app', ['title' => $forced ? 'Choose your password' : 'Change password', 'active' => null, 'robots' => 'noindex'])

@section('content')
    <section class="container section section--last">
        <div class="card auth">
            @unless ($forced)<a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>@endunless
            <h1 class="auth__title">{{ $forced ? 'Choose your password' : 'Change password' }}</h1>
            @if ($forced)
                <p class="lede" style="margin: 0">Welcome! You signed in with a temporary password from the church office. Choose your own now: only you will know it.</p>
            @endif
            @if (session('status'))<p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>@endif
            <form method="POST" action="{{ route('account.password.update') }}" class="notice-form__group">
                @csrf
                @method('PUT')
                <label class="field">{{ $forced ? 'The temporary password you were given' : 'Current password' }}
                    <input type="password" name="current_password" required autocomplete="current-password" @error('current_password') aria-invalid="true" @enderror>
                    @error('current_password') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">New password
                    <input type="password" name="password" required minlength="10" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                    <span class="field-hint">At least 10 characters. A short sentence you'll remember works well.</span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Type it again
                    <input type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
                <button type="submit" class="btn btn--navy">Save my password</button>
            </form>
            @if ($forced)
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn--ghost btn--sm">Sign out</button></form>
            @endif
        </div>
    </section>
@endsection
