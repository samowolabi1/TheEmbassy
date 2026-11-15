@extends('layouts.admin', ['title' => $campaign->exists ? 'Edit message' : 'New message', 'active' => 'outreach'])

@php
    $a = old('audience', $campaign->audience ?? []);
    $type = $a['type'] ?? 'members';
    $channels = old('channels', $campaign->channels ?? ['email']);
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.outreach.index') }}" class="box__link">← Message centre</a>
            <h1>{{ $campaign->exists ? 'Edit message' : 'New message' }}</h1>
        </div>
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $campaign->exists ? route('admin.outreach.update', $campaign) : route('admin.outreach.store') }}" class="row" data-outreach-form data-reach-url="{{ route('admin.outreach.reach') }}">
        @csrf
        @if ($campaign->exists) @method('PUT') @endif

        <div class="stack grow" style="gap: 20px">
            <section class="box form">
                <h2>Who it's for <x-tip text="The list is fixed when the message starts going out. Each person gets it once, even if they're both a member and a seller." /></h2>
                <fieldset class="choice-group" style="border: 0; padding: 0; margin: 0">
                    <legend class="sr-only">Who it's for</legend>
                    <div class="choices">
                        @foreach (\App\Services\Messaging\Campaigns::AUDIENCES as $key => $label)
                            <label class="choice"><input type="radio" name="audience[type]" value="{{ $key }}" @checked($type === $key) data-audience-type><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="form-row" data-for-audience="members">
                    <label class="field">Which members
                        <select name="audience[member_status]">
                            @foreach (\App\Services\Messaging\Campaigns::MEMBER_STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected(($a['member_status'] ?? 'active') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Unit
                        <select name="audience[unit_id]">
                            <option value="">Any unit</option>
                            @foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) ($a['unit_id'] ?? 0) === $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">House fellowship
                        <select name="audience[fellowship_id]">
                            <option value="">Any fellowship</option>
                            @foreach ($fellowships as $f)<option value="{{ $f->id }}" @selected((int) ($a['fellowship_id'] ?? 0) === $f->id)>{{ $f->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">Gender
                        <select name="audience[gender]">
                            <option value="">Everyone</option>
                            <option value="female" @selected(($a['gender'] ?? '') === 'female')>Women</option>
                            <option value="male" @selected(($a['gender'] ?? '') === 'male')>Men</option>
                        </select>
                    </label>
                    <label class="field">Birthday in
                        <select name="audience[birthday_month]">
                            <option value="">Any month</option>
                            @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected((int) ($a['birthday_month'] ?? 0) === $m)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <div class="form-row" data-for-audience="sellers">
                    <label class="field">Which sellers
                        <select name="audience[seller_status]">
                            @foreach (\App\Services\Messaging\Campaigns::SELLER_STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected(($a['seller_status'] ?? 'all') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <p class="box__note" data-for-audience="everyone" style="margin: 0">Approved members, sellers whose shop is listed now, and staff.</p>

                <div class="outreach-reach" aria-live="polite" data-reach>
                    <strong data-reach-people>{{ number_format($reach['people']) }}</strong> <span>people</span>
                    <span class="outreach-reach__ways">· <span data-reach-email>{{ number_format($reach['email']) }}</span> by email · <span data-reach-whatsapp>{{ number_format($reach['whatsapp']) }}</span> by WhatsApp · <span data-reach-app>{{ number_format($reach['app']) }}</span> with the app</span>
                </div>
            </section>

            <section class="box form">
                <h2>The message</h2>
                <label class="field">Subject
                    <input type="text" name="subject" value="{{ old('subject', $campaign->subject) }}" required maxlength="160" placeholder="e.g. Thanksgiving service this Sunday">
                    @error('subject') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Message
                    <textarea name="body" required maxlength="5000" rows="9" placeholder="Hi {first_name},&#10;&#10;…">{{ old('body', $campaign->body) }}</textarea>
                    <span class="field__hint">Fill-ins: <code>{first_name}</code> <code>{name}</code> <code>{church}</code>, replaced for each person. WhatsApp and app notifications send it as plain text.</span>
                    @error('body') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </section>
        </div>

        <aside class="stack side-col" style="gap: 20px">
            <section class="box form">
                <h2>Send by</h2>
                @foreach ($ways as $key => $w)
                    <label class="login__remember">
                        <input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, $channels, true)) @disabled(! $w['ready'])>
                        {{ $w['label'] }}
                        @if ($key === 'whatsapp') <x-tip text="Meta charges per WhatsApp message the church starts. Members who turned WhatsApp off in their account are skipped." /> @endif
                        @unless ($w['ready']) <span class="pill pill--draft">Off or not set up</span> @endunless
                    </label>
                @endforeach
                <span class="field__hint">Members can also read it in Messages in the app.</span>
            </section>

            <section class="box form">
                <h2>When</h2>
                <label class="field">Schedule for later (optional)
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $campaign->scheduled_at?->format('Y-m-d\TH:i')) }}" min="{{ now()->format('Y-m-d\TH:i') }}">
                    @error('scheduled_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="stack" style="gap: 8px">
                    <button type="submit" name="action" value="send" class="btn btn--gold" data-confirm-send>Send now</button>
                    <button type="submit" name="action" value="schedule" class="btn btn--navy">Schedule</button>
                    <button type="submit" name="action" value="test" class="btn btn--ghost" formnovalidate>Send me a test</button>
                    <button type="submit" name="action" value="draft" class="btn btn--ghost" formnovalidate>Save draft</button>
                </div>
            </section>
            @if ($campaign->exists)
                <button type="submit" form="delete-draft" class="btn btn--danger btn--xs" style="align-self: flex-start">Delete draft</button>
            @endif
        </aside>
    </form>
    @if ($campaign->exists)
        <form method="POST" action="{{ route('admin.outreach.destroy', $campaign) }}" id="delete-draft" data-confirm="Delete this draft?" hidden>@csrf @method('DELETE')</form>
    @endif
@endsection
