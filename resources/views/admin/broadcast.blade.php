@extends('layouts.admin', ['title' => 'Send announcement', 'active' => 'content'])

@php
    $whatsapp = 'https://wa.me/?text='.rawurlencode(old('message', $message));
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.content') }}" class="box__link">← Content &amp; announcements</a>
            <h1>Send "{{ $announcement->title }}"</h1>
            <span class="page-head__sub">Text members, notify everyone who installed the app, or share it to WhatsApp.</span>
        </div>
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="row">
        <form method="POST" action="{{ route('admin.announcements.send.store', $announcement) }}" class="box grow form" data-confirm="Send this now? Text messages are charged to the church's Termii balance.">
            @csrf
            <label class="field">Message
                <textarea name="message" maxlength="480" required style="height: 140px" data-sms-text>{{ old('message', $message) }}</textarea>
                <span class="field__hint" data-sms-count></span>
            </label>

            <fieldset class="choice-group">
                <legend>Send by</legend>
                <label class="login__remember">
                    <input type="checkbox" name="channels[]" value="sms" @checked(in_array('sms', old('channels', []), true)) @disabled(! $smsReady)>
                    Text message to {{ number_format($reach['sms']) }} {{ \Illuminate\Support\Str::plural('member', $reach['sms']) }}
                    @unless ($smsReady) <span class="pill pill--draft">Set up Termii in Settings &amp; roles</span> @endunless
                </label>
                <label class="login__remember">
                    <input type="checkbox" name="channels[]" value="push" @checked(in_array('push', old('channels', ['push']), true)) @disabled(! $pushReady)>
                    App notification to {{ number_format($reach['push']) }} {{ \Illuminate\Support\Str::plural('phone', $reach['push']) }} and browsers
                    @unless ($pushReady) <span class="pill pill--draft">Run php artisan push:keys</span> @endunless
                </label>
            </fieldset>
            <span class="field__hint">Texts go to active members with a phone number who haven't turned them off in their account. Notifications go to everyone who turned them on in the app.</span>

            @if ($history->isNotEmpty())
                <label class="login__remember"><input type="checkbox" name="again" value="1">Send it again (it has been sent before)</label>
            @endif

            <div class="actions">
                <button type="submit" class="btn btn--navy" @disabled(! $smsReady && ! $pushReady)>Send</button>
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn--outline" data-whatsapp-share><x-icon name="whatsapp" :size="16" />Share to WhatsApp</a>
            </div>
            <span class="field__hint">"Share to WhatsApp" opens WhatsApp with the message ready to post in the church groups, channel or status.</span>
        </form>

        <section class="box box--flush side-col">
            <div class="box__head"><h2>Sent</h2></div>
            @forelse ($recent as $b)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $b->title }}</span>
                        <span class="list-item__meta">
                            {{ $b->created_at->format('j M, g:i A') }} · {{ $b->creator?->name ?? 'Staff' }}
                            @if ($b->uses('sms')) · {{ number_format($b->sms_sent) }} texts @if ($b->sms_failed) ({{ $b->sms_failed }} not sent) @endif @endif
                            @if ($b->uses('push')) · {{ number_format($b->push_sent) }} notifications @endif
                        </span>
                        @if ($b->sms_error)
                            <span class="field-error">Texts stopped: {{ $b->sms_error }}</span>
                        @endif
                    </span>
                    <span @class(['pill', 'pill--live' => $b->status === 'sent', 'pill--scheduled' => $b->status !== 'sent'])>{{ $b->status === 'sent' ? 'Sent' : 'Sending' }}</span>
                </div>
            @empty
                <p class="empty-state">Nothing sent yet.</p>
            @endforelse
        </section>
    </div>
@endsection
