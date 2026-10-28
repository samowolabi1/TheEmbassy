@extends('layouts.app', ['title' => 'Log in', 'active' => null, 'description' => 'Sign in to your '.config('church.short_name').' account.'])

@section('content')
    <section class="container section section--last">
        <div class="card auth">
            <h1 class="auth__title">Welcome back</h1>
            @if (session('status'))
                <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('login.store') }}" class="notice-form__group">
                @csrf
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" aria-describedby="login-error" @enderror>
                </label>
                <label class="field">Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                @error('email') <p class="field-error" id="login-error" role="alert" style="margin: 0">{{ $message }}</p> @enderror
                <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
                <button type="submit" class="btn btn--navy">Log in</button>
            </form>
            <div class="auth__links">
                <a href="{{ route('password.request') }}">Forgot your password?</a>
                <a href="{{ route('join') }}">New here? Join the family</a>
            </div>
        </div>
    </section>
@endsection
