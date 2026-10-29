@extends('layouts.app', ['title' => $room->name.' · Community', 'active' => 'community'])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="community">
        <aside class="community__side">
            <a href="{{ route('community.index') }}" class="community__home">
                <x-crown :width="22" :height="18" /> Community
            </a>
            @include('community.partials.rooms')
        </aside>

        <section class="chat" data-chat
            data-updates-url="{{ route('community.updates', $room) }}"
            data-since="{{ $now }}"
            data-after="{{ $messages->last()?->id ?? 0 }}">
            <header class="chat__head">
                <a href="{{ route('community.index') }}" class="chat__back" aria-label="All rooms"><x-icon name="chevron-left" :size="22" /></a>
                <span class="chat__emoji" aria-hidden="true">{{ $room->emoji ?: '💬' }}</span>
                <span class="chat__title">
                    <h1>{{ $room->name }}</h1>
                    <span>{{ $room->unit ? $room->unit->name.' members only' : $room->description }}</span>
                </span>
            </header>

            <div class="chat__scroll" data-chat-scroll>
                @if ($hasEarlier)
                    <a href="{{ route('community.room', [$room, 'before' => $messages->first()->id]) }}" class="chat__earlier">Show earlier messages</a>
                @elseif (request('before'))
                    <a href="{{ route('community.room', $room) }}" class="chat__earlier">Back to the latest</a>
                @endif
                @if ($messages->isEmpty())
                    <p class="chat__empty" data-chat-empty>No messages yet. Say hello! 👋</p>
                @endif
                <ol class="chat__list" data-chat-list>
                    @php $day = null; @endphp
                    @foreach ($messages as $message)
                        @if ($day !== $message->created_at->toDateString())
                            @php $day = $message->created_at->toDateString(); @endphp
                            <li class="chat__day"><span>{{ $message->created_at->isToday() ? 'Today' : ($message->created_at->isYesterday() ? 'Yesterday' : $message->created_at->format('l j F')) }}</span></li>
                        @endif
                        @include('community.partials.message')
                    @endforeach
                </ol>
            </div>

            @if ($errors->any())
                <p class="chat__error" role="alert">{{ $errors->first() }}</p>
            @endif
            @if (session('status'))
                <p class="chat__note" role="status">{{ session('status') }}</p>
            @endif

            @if ($room->canPost($viewer))
                <form method="POST" action="{{ route('community.messages.store', $room) }}" enctype="multipart/form-data" class="composer" data-composer>
                    @csrf
                    <div class="composer__reply" data-reply-preview hidden>
                        <span>Replying to <strong data-reply-name></strong></span>
                        <button type="button" class="composer__x" data-reply-cancel aria-label="Cancel reply"><x-icon name="close" :size="16" /></button>
                        <input type="hidden" name="reply_to_id" value="" data-reply-input>
                    </div>
                    <details class="composer__poll" data-poll>
                        <summary class="composer__poll-toggle">📊 Start a poll</summary>
                        <label class="field">Poll question <input type="text" name="poll_question" maxlength="200" placeholder="e.g. Which song should we sing on Sunday?"></label>
                        <div class="composer__options">
                            @foreach (range(1, 4) as $i)
                                <input type="text" name="poll_options[]" maxlength="80" placeholder="Answer {{ $i }}{{ $i > 2 ? ' (optional)' : '' }}" aria-label="Answer {{ $i }}">
                            @endforeach
                        </div>
                        <label class="field">Closes in
                            <select name="poll_hours">
                                <option value="1">1 hour</option>
                                <option value="6">6 hours</option>
                                <option value="24" selected>1 day</option>
                                <option value="72">3 days</option>
                                <option value="168">1 week</option>
                            </select>
                        </label>
                    </details>
                    <div class="composer__row">
                        <label class="composer__tool" title="Add a photo">
                            <x-icon name="image" :size="20" /><span class="sr-only">Add a photo</span>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" data-composer-image>
                        </label>
                        <label class="sr-only" for="composer-body">Message</label>
                        <textarea id="composer-body" name="body" rows="1" maxlength="2000" placeholder="Message {{ $room->name }}" data-composer-body></textarea>
                        <button type="submit" class="composer__send" aria-label="Send">➤</button>
                    </div>
                    <span class="composer__file" data-composer-file></span>
                </form>
            @elseif ($viewer->isMutedInCommunity())
                <p class="chat__note">You can read but not post until {{ $viewer->community_muted_until->format('j M, g:i A') }}.@if ($viewer->community_mute_reason) Reason: {{ $viewer->community_mute_reason }}@endif</p>
            @else
                <p class="chat__note">🔒 Only moderators post in this room.</p>
            @endif
        </section>
    </div>
@endsection
