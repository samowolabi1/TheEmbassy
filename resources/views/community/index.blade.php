@extends('layouts.app', ['title' => 'Community', 'active' => 'community'])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container community-home">
        <div class="stack" style="gap: 10px">
            <span class="eyebrow">THE TRIBE COMMUNITY</span>
            <h1 class="display community-home__title">Your people, all week.</h1>
            <p class="lede" style="margin: 0; max-width: 620px">Chat, pray for each other, share wins and have fun with the Tribe between Sundays. Only members can see this.</p>
        </div>

        <div class="community-home__grid">
            <div class="card community-home__rooms">
                <h2 class="h2 h2--sm" style="padding: 20px 20px 4px">Rooms</h2>
                @include('community.partials.rooms')
            </div>

            <div class="stack" style="gap: 16px">
                @if ($quiz)
                    <div class="card community-card bg-navy on-dark">
                        <span class="eyebrow eyebrow--gold">BIBLE QUIZ</span>
                        <h2 class="community-card__title">{{ $quiz->title }}</h2>
                        <p class="community-card__text">{{ $quiz->questions_count }} questions · {{ $quiz->seconds_per_question }} seconds each · closes {{ $quiz->closes_at->format('D j M, g:i A') }}</p>
                        @if ($quizAttempt?->finished_at)
                            <p class="community-card__text"><strong>You scored {{ $quizAttempt->score }}/{{ $quiz->questions_count }}.</strong></p>
                            <a href="{{ route('community.quiz', $quiz) }}" class="btn btn--gold" style="align-self: flex-start">See the leaderboard</a>
                        @else
                            <a href="{{ route('community.quiz', $quiz) }}" class="btn btn--gold" style="align-self: flex-start">{{ $quizAttempt ? 'Carry on' : 'Play now' }}</a>
                        @endif
                    </div>
                @endif

                <div class="card community-card">
                    <span class="eyebrow">🎂 BIRTHDAYS THIS WEEK</span>
                    @forelse ($birthdays as $b)
                        <div class="birthday">
                            <span class="birthday__name">{{ $b['member']->name }}</span>
                            <span @class(['birthday__day', 'birthday__day--today' => $b['today']])>{{ $b['today'] ? 'Today! 🎉' : $b['date']->format('D j M') }}</span>
                        </div>
                    @empty
                        <p class="community-card__text" style="color: var(--slate)">No birthdays this week. Add yours in <a href="{{ route('account') }}" class="accent-bronze">your account</a>.</p>
                    @endforelse
                    @if ($birthdays->where('today', true)->isNotEmpty() && $rooms->first())
                        <a href="{{ route('community.room', $rooms->first()) }}" class="btn btn--outline btn--sm" style="align-self: flex-start">Wish them well in {{ $rooms->first()->name }}</a>
                    @endif
                </div>

                <div class="card community-card">
                    <span class="eyebrow">HOUSE RULES</span>
                    <ul class="community-rules">
                        <li>Be kind. Build each other up (1 Thessalonians 5:11).</li>
                        <li>Keep it clean: no insults, gossip or anything you wouldn't say in church.</li>
                        <li>No selling here. Use the {{ config('church.brand.market_name') }} for that.</li>
                        <li>Keep private things private: don't share someone's number or story without asking.</li>
                        <li>See something wrong? Tap "Report" and a moderator will look at it.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
@endsection
