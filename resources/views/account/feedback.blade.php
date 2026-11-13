@extends('layouts.app', ['title' => 'Feedback', 'active' => null])

@php($faces = [1 => '😞 Poor', 2 => '🙁 Fair', 3 => '😐 Good', 4 => '🙂 Very good', 5 => '😍 Excellent'])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">FEEDBACK</span>
        <h1 class="display notices-hero__title">Help us serve you better.</h1>
        <p class="lede" style="max-width: 680px">Tell us how services went, and share ideas for the church. The pastors and church office read everything. You can leave your name off.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif

        <div class="card notice-form__main" style="flex: none">
            <h2 class="h2 h2--sm" style="margin: 0">How was the service?</h2>
            @forelse ($services as $service)
                @php($mine = $given->get($service->id))
                <details class="feedback-service" id="service-{{ $service->id }}" @if ($focus === $service->id || ($loop->first && ! $mine && in_array($service->id, $attended, true))) open @endif>
                    <summary>
                        <span class="stack" style="gap: 2px">
                            <strong>{{ $service->label() }}</strong>
                            <span class="field-hint">{{ $service->held_on->format('l j F') }}{{ in_array($service->id, $attended, true) ? ' · you were there' : '' }}</span>
                        </span>
                        <span class="status-chip {{ $mine ? 'status-chip--ok' : '' }}">{{ $mine ? 'Thank you · '.\Illuminate\Support\Str::before($faces[$mine->rating], ' ') : 'Give feedback' }}</span>
                    </summary>
                    <form method="POST" action="{{ route('account.feedback.rate', $service) }}" class="notice-form__group" style="margin-top: 14px">
                        @csrf
                        <fieldset class="notice-form__group" style="gap: 8px">
                            <legend>Overall</legend>
                            <div class="pill-choices">
                                @foreach ($faces as $n => $label)
                                    <label class="pill-choice"><input type="radio" name="rating" value="{{ $n }}" required @checked((int) old('rating', $mine?->rating) === $n)><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset class="notice-form__group" style="gap: 8px">
                            <legend>What blessed you or went well?</legend>
                            <div class="pill-choices">
                                @foreach (\App\Models\ServiceFeedback::ASPECTS as $key => $label)
                                    <label class="pill-choice"><input type="checkbox" name="went_well[]" value="{{ $key }}" @checked(in_array($key, $mine?->went_well ?? [], true))><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset class="notice-form__group" style="gap: 8px">
                            <legend>What could be better?</legend>
                            <div class="pill-choices">
                                @foreach (\App\Models\ServiceFeedback::ASPECTS as $key => $label)
                                    <label class="pill-choice"><input type="checkbox" name="could_improve[]" value="{{ $key }}" @checked(in_array($key, $mine?->could_improve ?? [], true))><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="field">Anything else? (optional)
                            <textarea name="comment" maxlength="2000" rows="3" placeholder="What God did for you, or how we can do better">{{ $mine?->comment }}</textarea>
                        </label>
                        <label class="check"><input type="checkbox" name="anonymous" value="1" @checked($mine?->anonymous)>Don't show my name</label>
                        <button type="submit" class="btn btn--gold" style="align-self: flex-start">{{ $mine ? 'Update my feedback' : 'Send feedback' }}</button>
                    </form>
                </details>
            @empty
                <p class="lede" style="margin: 0">No services in the last {{ \App\Models\ServiceFeedback::DAYS }} days. Come back after the next one.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('account.feedback.suggest') }}" class="card notice-form__main" style="flex: none" id="suggest">
            @csrf
            <fieldset class="notice-form__group">
                <legend>Share a suggestion</legend>
                <div class="form-row">
                    <label class="field">About
                        <select name="category" required>
                            @foreach (\App\Models\Suggestion::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">In a few words
                        <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="120" placeholder="e.g. A Bible study for new believers">
                        @error('subject') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="field">Your idea or concern
                    <textarea name="body" required maxlength="3000" rows="5">{{ old('body') }}</textarea>
                    @error('body') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="check"><input type="checkbox" name="anonymous" value="1" @checked(old('anonymous'))>Don't show my name (you'll still see the answer here)</label>
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Send suggestion</button>
            </fieldset>
        </form>

        @if ($suggestions->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your suggestions</h2>
                @foreach ($suggestions as $s)
                    <div class="package-row" style="flex-direction: column; align-items: stretch">
                        <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap">
                            <span class="stack" style="gap: 2px">
                                <strong>{{ $s->subject }}</strong>
                                <span class="field-hint">{{ $s->categoryLabel() }} · {{ $s->created_at->format('j M Y') }}{{ $s->anonymous ? ' · without your name' : '' }}</span>
                            </span>
                            <span class="status-chip {{ in_array($s->status, ['planned', 'done'], true) ? 'status-chip--ok' : '' }}">{{ $s->statusLabel() }}</span>
                        </div>
                        @if ($s->response)
                            <div class="prayer-reply">
                                <strong>{{ $s->responder?->communityName() ?? 'The church office' }}</strong> <span class="field-hint">{{ $s->responded_at?->format('j M') }}</span>
                                <p style="margin: 4px 0 0; white-space: pre-line">{{ $s->response }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
