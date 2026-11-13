@extends('layouts.admin', ['title' => 'Visiting hours', 'active' => 'appointments'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.appointments.index') }}" class="box__link">← Visits with a pastor</a>
            <h1>Visiting hours &amp; time off</h1>
            <span class="page-head__sub">When each pastor sees members. Members book the open times up to {{ \App\Services\Pastoral\Appointments::DAYS_AHEAD }} days ahead, at least {{ config('church.appointments.notice_hours', 12) }} hours before.</span>
        </div>
    </header>

    @if ($pastors->isEmpty())
        <p class="flash flash--error">No one has the Pastor or Pastor in charge role yet. Give it to them in Settings &amp; roles first.</p>
    @endif

    @foreach ($pastors as $pastor)
        <section class="box">
            <h2>{{ $pastor->communityName() }}</h2>
            @forelse ($hours->get($pastor->id, collect()) as $h)
                <div class="list-item" style="padding: 8px 0">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ \App\Models\PastorHour::DAYS[$h->weekday] }}s, {{ \Illuminate\Support\Carbon::parse($h->starts)->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse($h->ends)->format('g:i A') }}</span>
                        <span class="list-item__meta">{{ $h->slot_minutes }}-minute visits · {{ \App\Models\PastorHour::MODES[$h->mode] }}{{ $h->location ? ' · '.$h->location : '' }}</span>
                    </span>
                    <form method="POST" action="{{ route('admin.appointments.hours.destroy', $h) }}" data-confirm="Remove these hours?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--xs">Remove</button>
                    </form>
                </div>
            @empty
                <p class="box__note" style="margin: 0">No visiting hours yet, so members can't book {{ $pastor->communityName() }}.</p>
            @endforelse
            @foreach ($timeOff->get($pastor->id, collect()) as $t)
                <div class="list-item" style="padding: 8px 0">
                    <span class="list-item__main">
                        <span class="list-item__title">Away {{ $t->starts_on->format('j M') }}{{ $t->ends_on->ne($t->starts_on) ? ' to '.$t->ends_on->format('j M') : '' }}</span>
                        @if ($t->note)<span class="list-item__meta">{{ $t->note }}</span>@endif
                    </span>
                    <form method="POST" action="{{ route('admin.appointments.time-off.destroy', $t) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--ghost btn--xs">Remove</button>
                    </form>
                </div>
            @endforeach
        </section>
    @endforeach

    @if ($pastors->isNotEmpty())
        <div class="row">
            <section class="box grow">
                <h2>Add visiting hours</h2>
                <form method="POST" action="{{ route('admin.appointments.hours.store') }}" class="form">
                    @csrf
                    <div class="form-row">
                        <label class="field">Pastor
                            <select name="user_id" required>
                                @foreach ($pastors as $p)
                                    <option value="{{ $p->id }}" @selected((int) old('user_id', auth()->id()) === $p->id)>{{ $p->communityName() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">Day
                            <select name="weekday" required>
                                @foreach (\App\Models\PastorHour::DAYS as $i => $day)
                                    <option value="{{ $i }}" @selected((int) old('weekday', 2) === $i)>{{ $day }}s</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="form-row">
                        <label class="field">From <input type="time" name="starts" value="{{ old('starts', '10:00') }}" required></label>
                        <label class="field">To <input type="time" name="ends" value="{{ old('ends', '13:00') }}" required>
                            @error('ends') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Each visit
                            <select name="slot_minutes">
                                @foreach ([15, 20, 30, 45, 60, 90] as $m)
                                    <option value="{{ $m }}" @selected((int) old('slot_minutes', 30) === $m)>{{ $m }} minutes</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="form-row">
                        <label class="field">How
                            <select name="mode">
                                @foreach (\App\Models\PastorHour::MODES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">Where (optional) <input type="text" name="location" maxlength="150" placeholder="e.g. Pastor's office, church building"></label>
                    </div>
                    <div><button type="submit" class="btn btn--gold">Add hours</button></div>
                </form>
            </section>

            <section class="box" style="flex: 0 1 340px">
                <h2>Time off</h2>
                <form method="POST" action="{{ route('admin.appointments.time-off.store') }}" class="form">
                    @csrf
                    <label class="field">Pastor
                        <select name="user_id" required>
                            @foreach ($pastors as $p)
                                <option value="{{ $p->id }}" @selected(auth()->id() === $p->id)>{{ $p->communityName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">From <input type="date" name="starts_on" min="{{ today()->toDateString() }}" required>
                        @error('starts_on') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">To <input type="date" name="ends_on" min="{{ today()->toDateString() }}" required>
                        @error('ends_on') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Note (optional) <input type="text" name="note" maxlength="150" placeholder="e.g. Conference"></label>
                    <div><button type="submit" class="btn btn--navy">Save time off</button></div>
                </form>
            </section>
        </div>
    @endif
@endsection
