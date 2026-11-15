@extends('layouts.admin', ['title' => 'Reminders & greetings', 'active' => 'reminders'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Reminders &amp; greetings</h1>
            <span class="page-head__sub">Messages the app sends by itself: a reminder before each service, a thank-you to the people who came, a request for their feedback, and renewal reminders for {{ config('church.brand.market_name') }} sellers. Choose when each goes, how it's sent and what it says.</span>
        </div>
    </header>

    <p class="box__note" style="margin: 0">
        Ways of sending, church-wide:
        @foreach ($ways as $w)
            <span @class(['pill', 'pill--live' => $w['on'] && $w['ready'], 'pill--draft' => ! ($w['on'] && $w['ready'])])>{{ $w['label'] }}: {{ ! $w['on'] ? 'off' : ($w['ready'] ? 'on' : 'on, not set up') }}</span>
        @endforeach
        @if (auth()->user()->canManage('settings')) <a href="{{ route('admin.settings.edit') }}#notifications" class="box__link">Change in Settings &amp; roles</a> @endif
    </p>

    <div class="stats">
        @foreach ($kinds as $k)
            <a href="#{{ $k['key'] }}" class="stat-tile">
                <span class="stat-tile__label">{{ $k['label'] }}</span>
                <span class="stat-tile__num">{{ number_format($k['sent']) }}</span>
                <span class="stat-tile__foot">{{ $k['settings']['enabled'] ? 'On' : 'Off' }} · sent in the last 30 days</span>
            </a>
        @endforeach
    </div>

    <div class="row">
        <div class="stack grow" style="gap: 20px">
            @foreach ($kinds as $k)
                @php($s = $k['settings'])
                <form method="POST" action="{{ route('admin.reminders.update', $k['key']) }}" class="box form reminder-card" id="{{ $k['key'] }}">
                    @csrf
                    @method('PUT')
                    <div class="reminder-card__head">
                        <h2>{{ $k['label'] }}
                            <x-tip :text="match ($k['key']) {
                                'service' => 'Sent to every approved member before each service on the Attendance page, so they remember to come or join online.',
                                'greeting' => 'Sent after a service to the members who checked in or were marked present, to thank them for coming.',
                                'feedback' => 'Sent after a service to the members who were there and haven\'t yet said how it was. It opens Feedback in their account.',
                                default => 'Sent to sellers before their subscription ends, so their shop stays on the market. A last reminder the day before and a note when it has ended are optional.',
                            }" />
                        </h2>
                        <label class="switch"><input type="checkbox" name="enabled" value="1" @checked($s['enabled'])><span>{{ $s['enabled'] ? 'On' : 'Off' }}</span></label>
                    </div>
                    @if ($errors->getBag($k['key'])->any())
                        <p class="field-error" role="alert" style="margin: 0">{{ $errors->getBag($k['key'])->first() }}</p>
                    @endif

                    <label class="field reminder-card__timing">When
                        <span class="reminder-card__when">
                            <input type="number" name="timing" value="{{ old('timing', $s['timing']) }}" min="{{ $k['key'] === 'market' ? 1 : 0 }}" max="{{ $k['max'] }}" required class="select-sm" style="width: 90px">
                            <span>{{ $k['unit'] }}</span>
                        </span>
                    </label>

                    <fieldset class="reminder-card__ways">
                        <legend>Send by</legend>
                        <label class="login__remember"><input type="checkbox" name="email" value="1" @checked($s['email'])>Email</label>
                        <label class="login__remember"><input type="checkbox" name="whatsapp" value="1" @checked($s['whatsapp'])>WhatsApp <x-tip text="Meta charges the church per WhatsApp message it starts (much less than a text). Only goes to people with a phone number who haven't turned WhatsApp off in their account, and only while WhatsApp is switched on in Settings &amp; roles." /></label>
                        <label class="login__remember"><input type="checkbox" name="push" value="1" @checked($s['push'])>App notification <x-tip text="Free. Reaches members who turned on notifications in the app." /></label>
                    </fieldset>
                    @if ($k['key'] !== 'market')
                        <span class="field__hint">It also appears in each member's Messages in the app.</span>
                    @endif

                    @if ($k['key'] === 'market')
                        <label class="login__remember"><input type="checkbox" name="final" value="1" @checked($s['final'])>Also remind them the day before it ends</label>
                        <label class="login__remember"><input type="checkbox" name="lapsed" value="1" @checked($s['lapsed'])>Tell them when it has ended and their shop is hidden</label>
                    @endif

                    <label class="field">Message
                        <textarea name="text" maxlength="450" rows="3" required data-reminder-text data-sample='@json($k['sample'])'>{{ old('text', $s['text']) }}</textarea>
                        <span class="field__hint">Fill-ins: @foreach ($k['placeholders'] as $p)<code>{{ '{'.$p.'}' }}</code>{{ $loop->last ? '' : ' ' }}@endforeach</span>
                    </label>
                    <div class="reminder-card__preview" aria-live="polite">
                        <span class="reminder-card__preview-label">How it reads</span>
                        <span data-reminder-preview>{{ \App\Services\Messaging\Reminders::render($s['text'], $k['sample']) }}</span>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn--navy btn--sm">Save</button>
                        <button type="submit" class="btn btn--ghost btn--sm" form="test-{{ $k['key'] }}">Send me a test</button>
                        <x-tip text="Sends this message, as last saved, to you only, by the ways ticked above that are switched on." />
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.reminders.test', $k['key']) }}" id="test-{{ $k['key'] }}" hidden>@csrf</form>
            @endforeach
        </div>

        <aside class="stack side-col" style="gap: 20px">
            <section class="box box--flush">
                <div class="box__head"><h2>Coming up</h2></div>
                @forelse ($upcoming as $u)
                    <div class="list-item">
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $u['service']->label() }}</span>
                            <span class="list-item__meta">{{ $u['service']->starts_at->format('D j M, g:i A') }} · {{ $u['sent'] ? 'reminder sent '.$u['sent']->format('g:i A') : 'reminder '.($u['remind_at']->isPast() ? 'goes out shortly' : 'at '.$u['remind_at']->format('D g:i A')) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="empty-state">No services scheduled. Add them on <a href="{{ route('admin.attendance.index') }}">Attendance</a>.</p>
                @endforelse
            </section>

            <section class="box box--flush">
                <div class="box__head"><h2>Subscriptions ending soon</h2></div>
                @forelse ($ending as $e)
                    <div class="list-item">
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $e['shop']->name }}</span>
                            <span class="list-item__meta">Ends {{ $e['until']->format('D j M') }} · {{ $e['shop']->reminded_for?->equalTo($e['until']) ? 'reminded' : 'not reminded yet' }}</span>
                        </span>
                    </div>
                @empty
                    <p class="empty-state">None in the next two weeks.</p>
                @endforelse
            </section>

            <section class="box box--flush">
                <div class="box__head"><h2>Recently sent</h2></div>
                @forelse ($recent as $r)
                    <div class="list-item">
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $r->title }}</span>
                            <span class="list-item__meta">{{ \App\Services\Messaging\Reminders::KINDS[$r->kind]['label'] ?? $r->kind }} · {{ $r->people }} {{ \Illuminate\Support\Str::plural('person', $r->people) }} · {{ \Illuminate\Support\Carbon::parse($r->sent_at)->diffForHumans() }}</span>
                        </span>
                    </div>
                @empty
                    <p class="empty-state">Nothing sent yet.</p>
                @endforelse
            </section>
        </aside>
    </div>
@endsection
