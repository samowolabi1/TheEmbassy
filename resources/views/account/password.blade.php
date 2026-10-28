@extends('layouts.app', ['title' => 'Choose a password', 'active' => null])

@section('content')
    <section class="container section section--last">
        <div class="card auth">
            <h1 class="auth__title">Choose a password</h1>
            <form method="POST" action="{{ route('password.update') }}" class="notice-form__group">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">New password
                    <input type="password" name="password" required minlength="10" autocomplete="new-password">
                    <span class="field-hint">At least 10 characters.</span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Type it again
                    <input type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
                <button type="submit" class="btn btn--navy">Save password</button>
            </form>
        </div>
    </section>
@endsection
