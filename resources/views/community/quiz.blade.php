@extends('layouts.app', ['title' => $quiz->title.' · Bible quiz', 'active' => 'community'])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container section section--last quiz-page">
        <a href="{{ route('community.index') }}" class="back"><x-icon name="chevron-left" :size="16" />Community</a>
        <div class="stack" style="gap: 8px">
            <span class="eyebrow">BIBLE QUIZ</span>
            <h1 class="display quiz-page__title">{{ $quiz->title }}</h1>
            @if ($quiz->description)
                <p class="lede" style="margin: 0">{{ $quiz->description }}</p>
            @endif
            <p class="quiz-page__meta">{{ $quiz->questions->count() }} questions · {{ $quiz->seconds_per_question }} seconds each · {{ $quiz->isOpen() ? 'closes '.$quiz->closes_at->format('D j M, g:i A') : 'closed' }} · {{ $players }} {{ \Illuminate\Support\Str::plural('player', $players) }}</p>
        </div>

        @if (session('status'))
            <p class="quiz-result" role="status">{{ session('status') }}</p>
        @endif

        <div class="quiz-grid">
            <div class="card quiz-card">
                @if ($playing)
                    <form method="POST" action="{{ route('community.quiz.submit', $quiz) }}" data-quiz
                        data-seconds="{{ $quiz->seconds_per_question }}"
                        data-deadline="{{ max(0, $quiz->timeLimit() - 30 - (int) $attempt->started_at->diffInSeconds(now())) }}">
                        @csrf
                        <div class="quiz-timer" data-quiz-timer aria-live="polite" hidden></div>
                        @foreach ($quiz->questions as $question)
                            <fieldset class="quiz-q" data-quiz-q>
                                <legend><span class="quiz-q__num">{{ $loop->iteration }}/{{ $loop->count }}</span> {{ $question->question }}</legend>
                                @foreach ($question->options as $i => $option)
                                    <label class="quiz-q__option">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $i }}">
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach
                        <button type="submit" class="btn btn--navy" data-quiz-submit>Submit answers</button>
                    </form>
                @elseif (! $attempt && $quiz->isOpen())
                    <h2 class="h2 h2--sm">Ready?</h2>
                    <p class="lede" style="margin: 0">You get one go. Each question has {{ $quiz->seconds_per_question }} seconds, and the clock starts when you press Start. Highest score wins; the fastest time breaks a tie.</p>
                    <form method="POST" action="{{ route('community.quiz.start', $quiz) }}">
                        @csrf
                        <button type="submit" class="btn btn--gold">Start the quiz</button>
                    </form>
                @elseif ($attempt?->finished_at)
                    <h2 class="h2 h2--sm">Your score: {{ $attempt->score }}/{{ $quiz->questions->count() }}</h2>
                    <p class="lede" style="margin: 0">Finished in {{ gmdate('i:s', (int) $attempt->seconds) }}.
                        {{ $reveal ? 'Here are the answers.' : 'The answers show here once the quiz closes on '.$quiz->closes_at->format('D j M').'.' }}</p>
                    @if ($reveal)
                        <ol class="quiz-review">
                            @foreach ($quiz->questions as $question)
                                @php $given = $attempt->answers[$question->id] ?? null; @endphp
                                <li>
                                    <strong>{{ $question->question }}</strong>
                                    <span class="quiz-review__answer">{{ $question->options[$question->answer] ?? '' }}@if ($question->reference) · {{ $question->reference }}@endif</span>
                                    @if ($given !== $question->answer)
                                        <span class="quiz-review__yours">You said: {{ $given === null ? 'no answer' : ($question->options[$given] ?? '') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                @else
                    <p class="lede" style="margin: 0">This quiz has closed.</p>
                @endif
            </div>

            <div class="card quiz-card">
                <h2 class="h2 h2--sm">🏆 Leaderboard</h2>
                @forelse ($leaderboard as $row)
                    <div @class(['leader-row', 'leader-row--me' => $row->user_id === auth()->id()])>
                        <span class="leader-row__rank">{{ $loop->iteration === 1 ? '🥇' : ($loop->iteration === 2 ? '🥈' : ($loop->iteration === 3 ? '🥉' : $loop->iteration)) }}</span>
                        <span class="leader-row__name">{{ $row->user?->communityName() }}</span>
                        <span class="leader-row__score">{{ $row->score }}/{{ $quiz->questions->count() }} · {{ gmdate('i:s', (int) $row->seconds) }}</span>
                    </div>
                @empty
                    <p class="lede" style="margin: 0; color: var(--slate)">Nobody has played yet. Be the first!</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
