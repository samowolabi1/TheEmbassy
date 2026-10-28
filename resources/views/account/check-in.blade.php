@extends('layouts.app', ['title' => 'Check in', 'active' => null])

@section('content')
    <section class="container section section--last">
        <div class="card auth">
            <span class="eyebrow">ATTENDANCE</span>
            <h1 class="auth__title">Check in</h1>
            @if (session('status'))
                <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="notice-alert" role="alert" style="margin: 0; background: #fdecea; color: #8a1c12">{{ $errors->first() }}</p>
            @endif

            @if (! $member)
                <p class="lede" style="margin: 0">Check-in is for members on the church register.</p>
                <a href="{{ route('join') }}" class="btn btn--gold" style="align-self: flex-start">Join the family</a>
            @elseif ($member->status !== 'active')
                <p class="lede" style="margin: 0">Once the church office confirms your membership, you can check in here. Until then, an usher can mark you present.</p>
            @elseif ($services->isEmpty())
                <p class="lede" style="margin: 0">Check-in opens {{ (int) config('church.checkin.opens_before') }} minutes before each service. Come back then.</p>
            @else
                @foreach ($services as $service)
                    <div class="checkin-card">
                        <div class="stack" style="gap: 2px">
                            <strong class="checkin-card__title">{{ $service->label() }}</strong>
                            <span class="field-hint">{{ $service->starts_at->format('D j M · g:i A') }} · {{ $service->formatLabel() }}</span>
                        </div>
                        @if (in_array($service->id, $done, true))
                            <span class="status-chip status-chip--ok">✓ You're checked in</span>
                        @else
                            @if ($service->in_person_checkin)
                                <form method="POST" action="{{ route('check-in.store') }}" class="notice-form__group" data-geo-checkin>
                                    @csrf
                                    <input type="hidden" name="service" value="{{ $service->id }}">
                                    <input type="hidden" name="mode" value="in_person">
                                    <input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy">
                                    @if ($requireCode)
                                        <label class="field">Code on the screen
                                            <input type="text" name="code" value="{{ $code }}" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="6 digits">
                                        </label>
                                    @endif
                                    <button type="submit" class="btn btn--navy">I'm at church</button>
                                    <span class="field-hint" data-geo-status>We check that you're at the venue using your phone's location. Only the distance is kept.</span>
                                </form>
                            @endif
                            @if ($service->online_checkin)
                                <form method="POST" action="{{ route('check-in.store') }}" class="notice-form__group">
                                    @csrf
                                    <input type="hidden" name="service" value="{{ $service->id }}">
                                    <input type="hidden" name="mode" value="online">
                                    <label class="field">Code on the live stream
                                        <input type="text" name="code" value="{{ $code }}" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="6 digits">
                                    </label>
                                    <button type="submit" class="btn btn--gold">I'm watching online</button>
                                </form>
                            @endif
                        @endif
                    </div>
                @endforeach
            @endif

            @if ($recent->isNotEmpty())
                <div class="stack" style="gap: 8px">
                    <strong>Your recent attendance</strong>
                    <ul class="mini-list">
                        @foreach ($recent as $s)
                            <li><span>{{ $s->held_on->format('D j M') }} · {{ $s->label() }}</span><strong>{{ ['in_person' => 'In person', 'online' => 'Online'][$s->pivot->mode] ?? 'Present' }}</strong></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endsection
