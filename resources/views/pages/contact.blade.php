@extends('layouts.app', ['title' => 'Contact us', 'active' => null, 'description' => 'Get in touch with '.config('church.short_name').'.'])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">CONTACT US</span>
        <h1 class="display notices-hero__title">We'd love to hear from you.</h1>
        <p class="lede" style="max-width: 680px">Questions, visiting for the first time, counselling, partnerships: send us a message and the church office will get back to you.</p>
    </section>

    <section class="container section--tight section--last">
        <div class="notice-form">
            <div class="card notice-form__main">
                @if (session('sent'))
                    <div class="stack" style="gap: 12px">
                        <span class="eyebrow">MESSAGE SENT</span>
                        <h2 class="h2 h2--sm">Thank you. We'll be in touch soon.</h2>
                        <a href="{{ route('home') }}" class="btn btn--outline" style="align-self: flex-start">Back to home</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('contact.store') }}" class="notice-form__group" novalidate>
                        @csrf
                        <label class="honeypot" aria-hidden="true">Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        <div class="grid-auto" style="--min: 220px; --gap: 14px">
                            <label class="field">Your name
                                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">
                                @error('name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Email
                                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="field">Phone (optional)
                                <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel">
                            </label>
                            <label class="field">About
                                <select name="subject" required>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject }}" @selected(old('subject') === $subject)>{{ $subject }}</option>
                                    @endforeach
                                </select>
                                @error('subject') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <label class="field">Message
                            <textarea name="message" required maxlength="3000" style="height: 160px">{{ old('message') }}</textarea>
                            @error('message') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="btn btn--gold" style="align-self: flex-start">Send message</button>
                    </form>
                @endif
            </div>
            <aside class="card notice-form__side">
                <h2 style="margin: 0; font-family: var(--font-display); font-weight: 800; font-size: 22px; color: var(--navy)">Visit us</h2>
                <dl class="facts">
                    <dt>Where</dt><dd>{{ config('church.address') }}</dd>
                    <dt>Sundays</dt><dd>{{ config('church.sunday_time') }}</dd>
                    <dt>{{ config('church.midweek_day') }}</dt><dd>{{ config('church.midweek_time') }}</dd>
                    @if (config('church.phone'))<dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', config('church.phone')) }}">{{ config('church.phone') }}</a></dd>@endif
                    @if (config('church.email'))<dt>Email</dt><dd><a href="mailto:{{ config('church.email') }}">{{ config('church.email') }}</a></dd>@endif
                </dl>
                <a href="{{ config('church.directions_url') }}" class="btn btn--navy">Get directions</a>
                <a href="{{ route('home') }}#prayer" class="btn btn--outline">Send a prayer request</a>
            </aside>
        </div>
    </section>
@endsection
