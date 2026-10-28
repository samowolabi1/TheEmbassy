@php
    $h2 = 'margin: 28px 0 12px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304';
    $item = 'display: block; padding: 14px 0; border-top: 1px solid #EDE6D6; text-decoration: none; color: #0B1526';
    $title = 'display: block; font-size: 16px; font-weight: bold; color: #0A1D3F; margin-bottom: 4px';
    $meta = 'display: block; font-size: 13px; color: #56627A; line-height: 1.5';
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $issue['subject'] }}</title></head>
<body style="margin: 0; padding: 24px 12px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }} · WEEK OF {{ strtoupper($issue['week_of']->format('j F')) }}</p>
        <h1 style="margin: 0 0 8px; font-size: 24px; line-height: 1.2; color: #0A1D3F">Your week with the Tribe</h1>
        <p style="margin: 0; font-size: 14px; color: #56627A">Sundays · {{ config('church.sunday_time') }} · {{ config('church.address') }}</p>

        @if ($issue['declaration'])
            <div style="margin: 24px 0 0; background: #0A1D3F; color: #FFFFFF; border-radius: 14px; padding: 22px">
                <p style="margin: 0 0 10px; font-size: 12px; font-weight: bold; letter-spacing: 0.12em; color: #E3AE24">THIS WEEK'S DECLARATION</p>
                @foreach ($issue['declaration']->lines as $line)
                    @if (trim((string) $line) !== '')
                        <p style="margin: 0 0 8px; font-size: 16px; line-height: 1.45; font-weight: bold">{{ $line }}</p>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($issue['announcements']->isNotEmpty())
            <p style="{{ $h2 }}">ANNOUNCEMENTS</p>
            @foreach ($issue['announcements'] as $a)
                <a href="{{ $a->url ?: route('home') }}" style="{{ $item }}">
                    <span style="{{ $title }}">{{ $a->title }}</span>
                    <span style="{{ $meta }}">{{ $a->body }}</span>
                </a>
            @endforeach
        @endif

        @if ($issue['events']->isNotEmpty())
            <p style="{{ $h2 }}">COMING UP</p>
            @foreach ($issue['events'] as $e)
                <a href="{{ route('events.show', $e) }}" style="{{ $item }}">
                    <span style="{{ $title }}">{{ $e->title }}</span>
                    <span style="{{ $meta }}">{{ $e->starts_at->format('D j M, g:i A') }}@if ($e->venue) · {{ $e->venue }}@endif</span>
                </a>
            @endforeach
        @endif

        @if ($issue['sermons']->isNotEmpty())
            <p style="{{ $h2 }}">CATCH UP ON THE WORD</p>
            @foreach ($issue['sermons'] as $s)
                <a href="{{ $s->url ?: route('watch') }}" style="{{ $item }}">
                    <span style="{{ $title }}">{{ $s->title }}</span>
                    <span style="{{ $meta }}">{{ $s->preached_on->format('j M') }}@if ($s->preacher) · {{ $s->preacher }}@endif @if ($s->scripture) · {{ $s->scripture }}@endif</span>
                </a>
            @endforeach
        @endif

        @if ($issue['posts']->isNotEmpty())
            <p style="{{ $h2 }}">NEW ON THE BLOG</p>
            @foreach ($issue['posts'] as $p)
                <a href="{{ $p->url() }}" style="{{ $item }}">
                    <span style="{{ $title }}">{{ $p->title }}</span>
                    <span style="{{ $meta }}">{{ $p->dek ?: $p->topic }} · {{ $p->readingTime() }}</span>
                </a>
            @endforeach
        @endif

        <p style="margin: 28px 0 0; text-align: center">
            <a href="{{ route('home') }}#prayer" style="display: inline-block; background: #E3AE24; color: #06132B; text-decoration: none; font-weight: bold; padding: 13px 22px; border-radius: 999px">Send a prayer request</a>
        </p>
    </div>
    <p style="max-width: 560px; margin: 16px auto 0; text-align: center; font-size: 12px; color: #56627A; line-height: 1.6">
        You're getting this because you subscribed on our website.
        <a href="{{ $unsubscribeUrl }}" style="color: #56627A">Unsubscribe</a>
    </p>
</body>
</html>
