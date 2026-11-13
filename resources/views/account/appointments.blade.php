@extends('layouts.app', ['title' => 'See a pastor', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">SEE A PASTOR</span>
        <h1 class="display notices-hero__title">Book a visit.</h1>
        <p class="lede" style="max-width: 680px">Counselling, prayer, guidance, or just to talk. Choose a time that's open, and the pastor will confirm it. What you tell us here is seen only by the pastor you're seeing.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="notice-alert" role="alert" style="margin: 0">{{ $errors->first() }}</p>
        @endif

        @if ($mine->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your visits</h2>
                @foreach ($mine as $a)
                    <div class="package-row">
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $a->when() }}</strong>
                            <span class="field-hint">With {{ $a->pastor?->communityName() }} · {{ \App\Models\Appointment::MODES[$a->mode] }} · {{ \App\Models\Appointment::TOPICS[$a->topic] ?? '' }}</span>
                            @if ($a->staff_note)
                                <span class="field-hint">"{{ $a->staff_note }}"</span>
                            @endif
                            @if ($a->call_link && $a->status === 'confirmed' && $a->ends_at->isFuture())
                                <a href="{{ $a->call_link }}" target="_blank" rel="noopener" class="accent-bronze" style="font-weight: 700">Join the call</a>
                            @endif
                        </span>
                        <span style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <span class="status-chip {{ $a->status === 'confirmed' ? 'status-chip--ok' : '' }}">{{ $a->statusLabel() }}</span>
                            @if ($a->isUpcoming())
                                <form method="POST" action="{{ route('account.appointments.cancel', $a) }}" onsubmit="return confirm('Cancel this visit?')">
                                    @csrf
                                    <button type="submit" class="btn btn--outline btn--sm">Cancel</button>
                                </form>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($pastors->isEmpty())
            <div class="card notice-form__main" style="flex: none">
                <p class="lede" style="margin: 0">Visiting times haven't been set up yet. Please call the church office to arrange a visit{{ config('church.phone') ? ' on '.config('church.phone') : '' }}.</p>
            </div>
        @else
            @if ($pastors->count() > 1)
                <div class="card notice-form__main" style="flex: none">
                    <h2 class="h2 h2--sm" style="margin: 0">Who would you like to see?</h2>
                    <div class="pill-choices">
                        @foreach ($pastors as $p)
                            <a href="{{ route('account.appointments', ['pastor' => $p->id]) }}#times" class="pill-choice" style="text-decoration: none"><input type="radio" @checked($pastor?->id === $p->id) tabindex="-1" aria-hidden="true"><span>{{ $p->communityName() }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($pastor)
                <form method="POST" action="{{ route('account.appointments.store') }}" class="card notice-form__main" style="flex: none" id="times">
                    @csrf
                    <input type="hidden" name="pastor_id" value="{{ $pastor->id }}">
                    <h2 class="h2 h2--sm" style="margin: 0">Open times with {{ $pastor->communityName() }}</h2>
                    @forelse ($times as $date => $slots)
                        <fieldset class="notice-form__group" style="gap: 8px">
                            <legend>{{ \Illuminate\Support\Carbon::parse($date)->format('l j F') }}@if ($slots[0]['location']) <span class="field-hint" style="font-weight: 500">· {{ $slots[0]['location'] }}</span>@endif</legend>
                            <div class="pill-choices">
                                @foreach ($slots as $slot)
                                    <label class="pill-choice"><input type="radio" name="start" value="{{ $slot['start']->format('Y-m-d H:i') }}" required @checked(old('start') === $slot['start']->format('Y-m-d H:i'))><span>{{ $slot['start']->format('g:i A') }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                    @empty
                        <p class="lede" style="margin: 0">No open times in the next three weeks. Please check back soon, or call the church office.</p>
                    @endforelse

                    @if ($times)
                        <fieldset class="notice-form__group" style="gap: 8px">
                            <legend>How would you like to meet?</legend>
                            <div class="pill-choices">
                                @foreach (\App\Models\Appointment::MODES as $key => $label)
                                    <label class="pill-choice"><input type="radio" name="mode" value="{{ $key }}" @checked(old('mode', 'in_person') === $key)><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="field">What's it about?
                            <select name="topic" required>
                                @foreach (\App\Models\Appointment::TOPICS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('topic') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">Anything the pastor should know first? (optional)
                            <textarea name="note" maxlength="2000" rows="4" placeholder="Only {{ $pastor->communityName() }} sees this.">{{ old('note') }}</textarea>
                        </label>
                        <button type="submit" class="btn btn--gold" style="align-self: flex-start">Ask for this time</button>
                    @endif
                </form>
            @endif
        @endif
    </section>
@endsection
