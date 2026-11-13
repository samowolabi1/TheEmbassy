@extends('layouts.admin', ['title' => 'Feedback', 'active' => 'feedback'])

@php($faces = [1 => '😞', 2 => '🙁', 3 => '😐', 4 => '🙂', 5 => '😍'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Feedback &amp; suggestions</h1>
            <span class="page-head__sub">What members say about services, and their ideas for the church, from their accounts. Members who leave their name off are shown as "A member (no name)".</span>
        </div>
    </header>

    <nav class="tabs" aria-label="Feedback">
        <a href="{{ route('admin.feedback.index') }}" class="tab" @if ($tab === 'services') aria-current="page" @endif>Services</a>
        <a href="{{ route('admin.feedback.index', ['tab' => 'suggestions']) }}" class="tab" @if ($tab === 'suggestions') aria-current="page" @endif>Suggestions @if ($counts['new'])<span>{{ $counts['new'] }} new</span>@endif</a>
    </nav>

    @if ($tab === 'services')
        <section class="box box--flush">
            @forelse ($services as $service)
                @php($tally = \App\Http\Controllers\Admin\FeedbackController::tally($service->feedback))
                @php($best = collect($tally)->sortByDesc('well')->filter(fn ($t) => $t['well'] > 0)->keys()->take(2))
                @php($worst = collect($tally)->sortByDesc('improve')->filter(fn ($t) => $t['improve'] > 0)->keys()->take(2))
                <a href="{{ route('admin.feedback.service', $service) }}" class="list-item" style="color: var(--ink)">
                    <span class="avatar" aria-hidden="true">{{ $faces[(int) round($service->feedback_avg_rating)] ?? '·' }}</span>
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $service->label() }} · {{ $service->held_on->format('D j M') }}</span>
                        <span class="list-item__meta">
                            {{ number_format($service->feedback_avg_rating, 1) }} out of 5 from {{ $service->feedback_count }} {{ \Illuminate\Support\Str::plural('member', $service->feedback_count) }}
                            @if ($best->isNotEmpty()) · 👍 {{ $best->map(fn ($k) => \App\Models\ServiceFeedback::ASPECTS[$k])->join(', ') }}@endif
                            @if ($worst->isNotEmpty()) · 🔧 {{ $worst->map(fn ($k) => \App\Models\ServiceFeedback::ASPECTS[$k])->join(', ') }}@endif
                        </span>
                    </span>
                </a>
            @empty
                <p class="empty-state">No feedback yet. Members give it from My account › Feedback for a week after each service.</p>
            @endforelse
        </section>
    @else
        <nav class="tabs" aria-label="Suggestion status">
            <a href="{{ route('admin.feedback.index', ['tab' => 'suggestions']) }}" class="tab" @if (! $status) aria-current="page" @endif>Open <span>{{ $counts['open'] }}</span></a>
            @foreach (\App\Models\Suggestion::STATUSES as $key => $label)
                <a href="{{ route('admin.feedback.index', ['tab' => 'suggestions', 'status' => $key]) }}" class="tab" @if ($status === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <section class="box box--flush">
            @forelse ($suggestions as $s)
                <article @class(['prayer-card', 'prayer-card--unread' => $s->status === 'new'])>
                    <div class="prayer-card__head">
                        <span class="prayer-card__who">{{ $s->subject }}</span>
                        <span class="pill pill--info">{{ $s->categoryLabel() }}</span>
                        <span class="pill {{ match ($s->status) { 'new' => 'pill--gold', 'planned', 'done' => 'pill--ok', default => 'pill--draft' } }}">{{ $s->statusLabel() }}</span>
                        <span class="prayer-card__when">{{ $s->from() }} · {{ $s->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="prayer-card__text" style="white-space: pre-line">{{ $s->body }}</p>
                    @if ($s->response)
                        <p class="box__note" style="margin: 0"><strong>Answered by {{ $s->responder?->communityName() ?? 'staff' }}, {{ $s->responded_at?->format('j M') }}:</strong> {{ $s->response }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.feedback.respond', $s) }}" class="form" style="gap: 10px">
                        @csrf
                        <div class="form-row" style="align-items: end">
                            <label class="field" style="max-width: 220px">Where it's up to
                                <select name="status">
                                    @foreach (\App\Models\Suggestion::STATUSES as $key => $label)
                                        <option value="{{ $key }}" @selected($s->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">Answer the member (they're emailed)
                                <input type="text" name="response" value="{{ $s->response }}" maxlength="2000" placeholder="e.g. Thank you! We're starting a new believers' class in January.">
                            </label>
                            <div><button type="submit" class="btn btn--navy btn--xs">Save</button></div>
                        </div>
                    </form>
                </article>
            @empty
                <p class="empty-state">No suggestions here.</p>
            @endforelse
            @include('admin.partials.pager', ['paginator' => $suggestions])
        </section>
    @endif
@endsection
