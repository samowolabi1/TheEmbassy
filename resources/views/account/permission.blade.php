@extends('layouts.app', ['title' => 'Ask for permission', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">PERMISSION</span>
        <h1 class="display notices-hero__title">Away or running late?</h1>
        <p class="lede" style="max-width: 680px">Let your HOD and the pastors know before the service, and why. They'll see it straight away and answer you here.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        <form method="POST" action="{{ route('account.permission.store') }}" class="card notice-form__main permission-form" style="flex: none">
            @csrf
            <fieldset class="notice-form__group">
                <legend>What's happening?</legend>
                <div class="choice-cards">
                    @foreach (\App\Models\PermissionRequest::KINDS as $key => $label)
                        <label class="choice-card">
                            <input type="radio" name="kind" value="{{ $key }}" required @checked(old('kind', 'absent') === $key)>
                            <span class="choice-card__icon" aria-hidden="true">{{ $key === 'late' ? '⏰' : '🙏' }}</span>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('kind') <span class="field-error">{{ $message }}</span> @enderror
            </fieldset>

            <fieldset class="notice-form__group">
                <legend>Which service?</legend>
                <div class="grid-auto" style="--min: 220px; --gap: 14px">
                    <label class="field">Service
                        <select name="service_id">
                            <option value="">Not listed: I'll choose the day</option>
                            @foreach ($services as $s)
                                <option value="{{ $s->id }}" @selected((int) old('service_id', $loop->first ? $s->id : 0) === $s->id)>{{ $s->label() }} · {{ $s->held_on->format('D j M') }}{{ $s->starts_at ? ', '.$s->starts_at->format('g:i A') : '' }}</option>
                            @endforeach
                        </select>
                        @error('service_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Or the day (if it isn't listed)
                        <input type="date" name="on_date" value="{{ old('on_date') }}" min="{{ today()->toDateString() }}">
                        @error('on_date') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field only-late">If you're coming late: about what time?
                        <input type="time" name="arrive_at" value="{{ old('arrive_at') }}">
                        @error('arrive_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="field">Why?
                    <textarea name="reason" rows="3" required minlength="5" maxlength="1000" placeholder="e.g. I'm travelling to Ibadan for a family wedding.">{{ old('reason') }}</textarea>
                    <span class="field-hint">Your HOD and the pastors see this.</span>
                    @error('reason') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </fieldset>
            <button type="submit" class="btn btn--gold" style="align-self: flex-start">Ask for permission</button>
        </form>

        @if ($mine->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">What you've asked</h2>
                @foreach ($mine as $p)
                    <div class="package-row">
                        <span class="stack" style="gap: 2px; min-width: 0">
                            <strong>{{ $p->summary() }}</strong>
                            <span class="field-hint">"{{ $p->reason }}"</span>
                            @if ($p->answer_note)
                                <span class="field-hint"><strong>{{ $p->answeredBy?->communityName() }}:</strong> "{{ $p->answer_note }}"</span>
                            @elseif ($p->answeredBy)
                                <span class="field-hint">Answered by {{ $p->answeredBy->communityName() }}</span>
                            @endif
                        </span>
                        <span style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <span @class(['status-chip', 'status-chip--ok' => $p->status === 'granted', 'status-chip--no' => $p->status === 'declined'])>{{ $p->statusLabel() }}</span>
                            @if ($p->isCancellable())
                                <form method="POST" action="{{ route('account.permission.cancel', $p) }}" data-confirm="Cancel this request?" data-confirm-ok="Cancel it">
                                    @csrf
                                    <button type="submit" class="btn btn--outline btn--sm">Cancel</button>
                                </form>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
