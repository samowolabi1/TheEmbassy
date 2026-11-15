@extends('layouts.admin', ['title' => 'Send announcement', 'active' => 'content'])

@php
    $whatsapp = 'https://wa.me/?text='.rawurlencode(old('message', $message));
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.content') }}" class="box__link">← Content &amp; announcements</a>
            <h1>Send "{{ $announcement->title }}"</h1>
            <span class="page-head__sub">Email members, send it on WhatsApp, notify everyone who installed the app, or share it to a WhatsApp group.</span>
        </div>
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="row">
        <form method="POST" action="{{ route('admin.announcements.send.store', $announcement) }}" class="box grow form" data-confirm="Send this now?">
            @csrf
            <label class="field">Message
                <textarea name="message" maxlength="480" required style="height: 140px" data-sms-text>{{ old('message', $message) }}</textarea>
                <span class="field__hint" data-sms-count></span>
            </label>

            <fieldset class="choice-group">
                <legend>Send by</legend>
                @php($labels = ['email' => ['Email to', 'member'], 'whatsapp' => ['WhatsApp to', 'member'], 'push' => ['App notification to', 'phone']])
                @foreach ($labels as $way => [$text, $noun])
                    <label class="login__remember">
                        <input type="checkbox" name="channels[]" value="{{ $way }}" @checked(in_array($way, old('channels', ['email']), true)) @disabled(! $ready[$way])>
                        {{ $text }} {{ number_format($reach[$way]) }} {{ \Illuminate\Support\Str::plural($noun, $reach[$way]) }}
                        @if ($way === 'whatsapp') <x-tip text="Meta charges the church per WhatsApp message it starts. Goes to members with a phone number who haven't turned WhatsApp off in their account." /> @endif
                        @unless ($ready[$way]) <span class="pill pill--draft">Off or not set up in Settings &amp; roles</span> @endunless
                    </label>
                @endforeach
            </fieldset>
            <span class="field__hint">Email goes to approved members with an email address. WhatsApp goes to those with a phone number who haven't turned it off. App notifications go to everyone who turned them on in the app.</span>

            @if ($history->isNotEmpty())
                <label class="login__remember"><input type="checkbox" name="again" value="1">Send it again (it has been sent before)</label>
            @endif

            <div class="actions">
                <button type="submit" class="btn btn--navy" @disabled(! in_array(true, $ready, true))>Send</button>
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
                            @if ($b->uses('email')) · {{ number_format($b->email_sent) }} emails @endif
                            @if ($b->uses('whatsapp')) · {{ number_format($b->whatsapp_sent) }} WhatsApp @if ($b->whatsapp_failed) ({{ $b->whatsapp_failed }} not sent) @endif @endif
                            @if ($b->uses('sms')) · {{ number_format($b->sms_sent) }} texts @endif
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
