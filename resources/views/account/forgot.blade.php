@extends('layouts.app', ['title' => 'Forgot password', 'active' => null])

@section('content')
    <section class="container section section--last">
        <div class="card auth">
            <h1 class="auth__title">Set a new password</h1>
            <p class="lede" style="margin: 0">Enter your email and we'll send you a link.</p>
            @if (session('status'))
                <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('password.email') }}" class="notice-form__group">
                @csrf
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="btn btn--navy">Send link</button>
            </form>
            <div class="auth__links"><a href="{{ route('login') }}">Back to log in</a></div>
        </div>
    </section>
@endsection
