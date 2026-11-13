@extends('layouts.app', ['title' => 'Prayer', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">PRAYER</span>
        <h1 class="display notices-hero__title">How can we pray with you?</h1>
        <p class="lede" style="max-width: 680px">Send a prayer request to the pastors and prayer team. Follow it here, hear back from them, and tell us when God answers.</p>
        <a href="{{ route('account.prayer.wall') }}" class="btn btn--outline btn--sm" style="align-self: flex-start">🙏 Pray for others on the prayer wall{{ $wallCount ? ' ('.$wallCount.')' : '' }}</a>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        @if (session('answered'))
            <div class="card notice-form__main" style="flex: none; background: var(--wash)">
                <h2 class="h2 h2--sm" style="margin: 0">Tell the church what God did?</h2>
                <p class="lede" style="margin: 0">Your answered prayer could encourage someone who's still waiting.</p>
                <a href="{{ route('account.testimonies', ['prayer' => session('answered')]) }}" class="btn btn--gold" style="align-self: flex-start">Share it as a testimony</a>
            </div>
        @endif

        <form method="POST" action="{{ route('account.prayer.store') }}" class="card notice-form__main" style="flex: none">
            @csrf
            <fieldset class="notice-form__group">
                <legend>New prayer request</legend>
                <label class="field">What would you like prayer for?
                    <select name="category" required>
                        @foreach (\App\Models\PrayerRequest::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Your request
                    <textarea name="request" required maxlength="5000" rows="5" placeholder="Share as much or as little as you like">{{ old('request') }}</textarea>
                    @error('request') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <fieldset class="notice-form__group" style="gap: 8px">
                    <legend>Who can see it</legend>
                    @foreach (\App\Models\PrayerRequest::AUDIENCES as $key => $label)
                        <label class="check"><input type="radio" name="audience" value="{{ $key }}" @checked(old('audience', 'team') === $key)>{{ $label }}</label>
                    @endforeach
                    <label class="check" style="margin-left: 26px"><input type="checkbox" name="anonymous" value="1" @checked(old('anonymous'))>On the wall, don't show my name</label>
                    <span class="field-hint">On the prayer wall, members see your first name (or no name) and your request, and can tell you they've prayed. It stays there for {{ \App\Models\PrayerRequest::WALL_DAYS }} days.</span>
                </fieldset>
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Send prayer request</button>
            </fieldset>
        </form>

        @if ($mine->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your prayer requests</h2>
                @foreach ($mine as $p)
                    <div class="package-row" style="flex-direction: column; align-items: stretch">
                        <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap">
                            <span class="field-hint">{{ $p->categoryLabel() ?? 'Prayer' }} · {{ $p->created_at->format('j M Y') }} · {{ \App\Models\PrayerRequest::AUDIENCES[$p->audience()] }}</span>
                            <span style="display: flex; gap: 8px; flex-wrap: wrap">
                                @if ($p->answered_at)
                                    <span class="status-chip status-chip--ok">Answered 🎉</span>
                                @elseif ($p->read_at)
                                    <span class="status-chip">Prayed for</span>
                                @else
                                    <span class="status-chip">Sent</span>
                                @endif
                                @if ($p->on_wall && $p->prayed_count)
                                    <span class="status-chip">🙏 {{ $p->prayed_count }} {{ \Illuminate\Support\Str::plural('person', $p->prayed_count) }} prayed</span>
                                @endif
                            </span>
                        </div>
                        <p style="margin: 0; white-space: pre-line">{{ $p->request }}</p>
                        @if ($p->answer_note)
                            <p style="margin: 0"><strong>What God did:</strong> {{ $p->answer_note }}</p>
                        @endif
                        @foreach ($p->replies as $r)
                            <div class="prayer-reply">
                                <strong>{{ $r->author?->communityName() ?? 'The prayer team' }}</strong> <span class="field-hint">{{ $r->created_at->format('j M') }}</span>
                                <p style="margin: 4px 0 0; white-space: pre-line">{{ $r->body }}</p>
                            </div>
                        @endforeach
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                            @unless ($p->answered_at)
                                <details>
                                    <summary class="btn btn--outline btn--sm" style="display: inline-flex">God answered 🎉</summary>
                                    <form method="POST" action="{{ route('account.prayer.answered', $p) }}" class="notice-form__group" style="margin-top: 10px">
                                        @csrf
                                        <label class="field">What happened? (optional)
                                            <input type="text" name="answer_note" maxlength="500">
                                        </label>
                                        <button type="submit" class="btn btn--gold btn--sm" style="align-self: flex-start">Mark as answered</button>
                                    </form>
                                </details>
                            @endunless
                            <form method="POST" action="{{ route('account.prayer.destroy', $p) }}" onsubmit="return confirm('Remove this prayer request?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn--outline btn--sm">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
