{{-- Once a day, the first page a member opens shows a verse. They switch it off in My details. --}}
@auth
    @php
        $todayVerse = app(\App\Services\Content\DailyVerse::class)->take(auth()->user());
    @endphp
    @if ($todayVerse)
        <dialog class="ask daily-verse" data-daily-verse aria-labelledby="daily-verse-title">
            <form method="dialog" class="ask__box">
                <span class="eyebrow" id="daily-verse-title">VERSE FOR TODAY</span>
                <blockquote class="daily-verse__text">{{ $todayVerse->text }}</blockquote>
                <cite class="daily-verse__ref">{{ $todayVerse->reference }} · KJV</cite>
                <div class="ask__actions">
                    <a href="{{ route('account.verses') }}" class="btn btn--outline">More verses</a>
                    <button type="submit" value="ok" class="btn btn--gold" autofocus>Amen</button>
                </div>
                <a href="{{ route('account') }}#details" class="daily-verse__off">Turn this off in My details</a>
            </form>
        </dialog>
    @endif
@endauth
