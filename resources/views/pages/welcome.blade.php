@extends('layouts.app', ['title' => 'Welcome', 'active' => null, 'description' => 'New at '.config('church.short_name').'? Say hello.'])

@section('content')
    <section class="container section section--last">
        <div class="card auth">
            @if (session('welcomed'))
                <span class="eyebrow">WELCOME</span>
                <h1 class="auth__title">We're so glad you came, {{ session('welcomed') }}!</h1>
                <p class="lede" style="margin: 0">Someone from our welcome team will reach out this week. In the meantime, feel free to look around.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px">
                    <a href="{{ route('about') }}" class="btn btn--navy">About us</a>
                    <a href="{{ route('events') }}" class="btn btn--outline">What's on</a>
                </div>
            @else
                <span class="eyebrow">FIRST TIME HERE?</span>
                <h1 class="auth__title">Say hello.</h1>
                <p class="lede" style="margin: 0">Leave your details and we'll be in touch to welcome you properly.</p>
                <form method="POST" action="{{ route('welcome.store') }}" class="notice-form__group" novalidate>
                    @csrf
                    <label class="honeypot" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    <label class="field">Your name
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Phone (WhatsApp if you have it)
                        <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="40" autocomplete="tel">
                        @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Email (optional)
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Who invited you? (optional)
                        <input type="text" name="invited_by" value="{{ old('invited_by') }}" maxlength="100">
                    </label>
                    <label class="field">Anything you'd like us to know? (optional)
                        <textarea name="notes" maxlength="1000" style="height: 90px">{{ old('notes') }}</textarea>
                    </label>
                    <label class="check"><input type="checkbox" name="wants_to_join" value="1" @checked(old('wants_to_join'))> I'd like to become a member</label>
                    <button type="submit" class="btn btn--gold">Send</button>
                    <span class="field-hint">We only use these details to welcome you. <a href="{{ route('privacy') }}">Privacy notice</a></span>
                </form>
            @endif
        </div>
    </section>
@endsection
