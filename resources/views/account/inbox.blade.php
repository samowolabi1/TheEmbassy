@extends('layouts.app', ['title' => 'Messages', 'active' => null])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="container section section--last inbox">
        <a href="{{ route('account') }}" class="back"><x-icon name="chevron-left" :size="16" />My account</a>
        <div class="stack" style="gap: 8px">
            <span class="eyebrow">MESSAGES</span>
            <h1 class="display inbox__title">From the church</h1>
            <p class="lede" style="margin: 0">Announcements, messages the church sends out, and news from your unit, from the last {{ \App\Services\Messaging\Inbox::DAYS }} days.</p>
        </div>

        <div class="card inbox__settings">
            <span>
                <strong>Get them on your phone too.</strong>
                {{ $smsOn ? 'Texts are on for church announcements.' : 'Texts are off.' }}
                <a href="{{ route('account') }}#details" class="accent-bronze">Change</a>
            </span>
            <span class="app-actions">
                <button type="button" class="btn btn--navy btn--sm" data-push-toggle data-on-label="Turn on notifications" data-off-label="Turn off notifications" hidden>Turn on notifications</button>
                <span class="app-actions__status" data-push-status aria-live="polite"></span>
            </span>
        </div>

        <ol class="inbox__list">
            @forelse ($items as $item)
                <li id="{{ $item['key'] }}" @class(['inbox__item', 'inbox__item--new' => ! $lastRead || $item['at']->gt($lastRead)])>
                    <span class="inbox__icon" aria-hidden="true">{{ ['broadcast' => '📣', 'announcement' => '📌', 'unit' => '👥'][$item['kind']] }}</span>
                    <span class="inbox__body">
                        <span class="inbox__meta">
                            {{ $item['from'] }} · <time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->isToday() ? $item['at']->format('g:i A') : $item['at']->format('j M') }}</time>
                            @if (! $lastRead || $item['at']->gt($lastRead)) <span class="inbox__new">New</span> @endif
                        </span>
                        <strong class="inbox__subject">{{ $item['title'] }}</strong>
                        <span class="inbox__text">{!! nl2br(e($item['body'])) !!}</span>
                        @if ($item['url'] && ! in_array($item['url'], [route('home'), route('account.inbox')], true))
                            <a href="{{ $item['url'] }}" class="accent-bronze inbox__link">Open link →</a>
                        @endif
                    </span>
                </li>
            @empty
                <li class="card" style="padding: 24px; list-style: none"><p class="lede" style="margin: 0">No messages yet.</p></li>
            @endforelse
        </ol>
    </section>
@endsection
