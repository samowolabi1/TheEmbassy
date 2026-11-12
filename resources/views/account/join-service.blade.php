@extends('layouts.app', ['title' => 'Join online · '.$service->label(), 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container section section--last join-page">
        <a href="{{ route('account') }}" class="back"><x-icon name="chevron-left" :size="16" />My account</a>
        <div class="stack" style="gap: 6px">
            <span class="eyebrow">JOIN ONLINE</span>
            <h1 class="display join-page__title">{{ $service->label() }}</h1>
            <span class="field-hint">{{ $service->starts_at?->format('l j F · g:i A') }}@if ($service->ends_at) – {{ $service->ends_at->format('g:i A') }}@endif</span>
        </div>

        <div class="join-status" data-join-status
            @if ($open && ! $present && $youtube) data-watch-url="{{ route('services.watching', $service) }}" data-minutes="{{ $watchMinutes }}" @endif>
            @if ($present)
                <span class="status-chip status-chip--ok">✓ You're marked present online. Welcome!</span>
            @elseif (! $open)
                <span class="status-chip">Online attendance opens {{ (int) config('church.checkin.opens_before') }} minutes before the service.</span>
            @elseif ($youtube)
                <span class="status-chip" data-join-message>Keep the stream playing for {{ $watchMinutes }} {{ \Illuminate\Support\Str::plural('minute', $watchMinutes) }} and you'll be marked present.</span>
            @elseif ($meeting)
                <span class="status-chip">Opening {{ $service->meetingName() }} from here marks you present.</span>
            @endif
        </div>

        @if ($youtube)
            <div class="player join-player">
                <iframe class="player__frame" src="https://www.youtube-nocookie.com/embed/{{ $youtube }}?autoplay=1&playsinline=1&rel=0" title="{{ $service->label() }} live stream"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
        @endif

        @if ($meeting)
            <form method="POST" action="{{ route('services.open', $service) }}" class="join-meeting" target="_blank">
                @csrf
                <button type="submit" class="btn btn--gold">Join on {{ $service->meetingName() }}</button>
                <span class="field-hint">Opens in a new window or the {{ $service->meetingName() }} app.</span>
            </form>
        @endif

        @if (! $youtube && ! $meeting)
            <div class="card" style="padding: 24px">
                <p class="lede" style="margin: 0">The stream link isn't up yet. Watch on the <a href="{{ route('watch') }}" class="accent-bronze">Watch page</a> or YouTube, and check in with the code shown on the stream.</p>
            </div>
        @endif

        @if ($open && ! $present)
            <details class="card join-code">
                <summary>Watching somewhere else? Enter the code on the stream</summary>
                <form method="POST" action="{{ route('check-in.store') }}" class="notice-form__group">
                    @csrf
                    <input type="hidden" name="service" value="{{ $service->id }}">
                    <input type="hidden" name="mode" value="online">
                    <label class="field">Code on the live stream
                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="6 digits">
                    </label>
                    <button type="submit" class="btn btn--navy" style="align-self: flex-start">Check in</button>
                </form>
            </details>
        @endif
    </section>
@endsection
