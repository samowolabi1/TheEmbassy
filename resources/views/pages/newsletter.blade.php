@extends('layouts.app', ['title' => 'Weekly email', 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container section section--last">
        <div class="card auth" style="text-align: center; align-items: center">
            @switch($state)
                @case('confirmed')
                    <span class="eyebrow">YOU'RE IN</span>
                    <h1 class="auth__title">See you on Monday.</h1>
                    <p class="lede" style="margin: 0">The weekly email arrives on Monday mornings with the declaration for the week, announcements, new messages and what's coming up.</p>
                    @break
                @case('confirm-unsubscribe')
                    <span class="eyebrow">WEEKLY EMAIL</span>
                    <h1 class="auth__title">Stop the weekly email?</h1>
                    <p class="lede" style="margin: 0">We'll stop sending it to {{ $subscriber->email }}.</p>
                    <form method="POST" action="{{ route('newsletter.unsubscribe.store', $subscriber->token) }}">
                        @csrf
                        <button type="submit" class="btn btn--navy">Unsubscribe</button>
                    </form>
                    @break
                @default
                    <span class="eyebrow">UNSUBSCRIBED</span>
                    <h1 class="auth__title">You won't get the weekly email.</h1>
                    <p class="lede" style="margin: 0">Changed your mind? Sign up again at the bottom of any page.</p>
            @endswitch
            <a href="{{ route('home') }}" class="btn btn--outline">Back to home</a>
        </div>
    </section>
@endsection
