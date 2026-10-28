@extends('layouts.unit', ['title' => 'Meetings', 'active' => 'meetings'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Meetings</h1>
            <span class="page-head__sub">Rehearsals, planning and prayer meetings, at church or online</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="box__head"><h2>Coming up</h2></div>
        @forelse ($upcoming as $m)
            <div class="list-item">
                <span class="list-item__main">
                    <a href="{{ route('unit.meetings.show', [$unit, $m]) }}" class="list-item__title">{{ $m->title }}</a>
                    <span class="list-item__meta">{{ $m->starts_at->format('D j M, g:i A') }}{{ $m->location ? ' · '.$m->location : '' }}{{ $m->online_url ? ' · Online' : '' }}</span>
                </span>
                @if ($m->checkInIsOpen())<span class="pill pill--ok">Check-in open</span>@endif
            </div>
        @empty
            <p class="empty-state">Nothing scheduled.</p>
        @endforelse
    </section>

    <section class="box" id="new-meeting">
        <h2>Set up a meeting</h2>
        <form method="POST" action="{{ route('unit.meetings.store', $unit) }}" class="form">
            @csrf
            <label class="field">Title <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Rehearsal for Thanksgiving Sunday">@error('title') <span class="field-error">{{ $message }}</span> @enderror</label>
            <div class="form-row">
                <label class="field">Starts <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>@error('starts_at') <span class="field-error">{{ $message }}</span> @enderror</label>
                <label class="field">Ends (optional) <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">@error('ends_at') <span class="field-error">{{ $message }}</span> @enderror</label>
            </div>
            <div class="form-row">
                <label class="field">Where (optional) <input type="text" name="location" value="{{ old('location') }}" maxlength="255" placeholder="e.g. Hall B"></label>
                <label class="field">Online link (optional) <input type="url" name="online_url" value="{{ old('online_url') }}" maxlength="500" placeholder="https://meet.google.com/…">@error('online_url') <span class="field-error">{{ $message }}</span> @enderror</label>
            </div>
            <label class="field">Agenda <textarea name="agenda" maxlength="5000" style="height: 100px">{{ old('agenda') }}</textarea></label>
            <label class="login__remember"><input type="checkbox" name="notify" value="1" checked>Email the unit about it</label>
            <div><button type="submit" class="btn btn--navy">Schedule meeting</button></div>
        </form>
    </section>

    <section class="box box--flush">
        <div class="box__head"><h2>Past meetings</h2></div>
        @forelse ($past as $m)
            <div class="list-item">
                <span class="list-item__main">
                    <a href="{{ route('unit.meetings.show', [$unit, $m]) }}" class="list-item__title">{{ $m->title }}</a>
                    <span class="list-item__meta">{{ $m->starts_at->format('D j M Y') }} · {{ $m->attendees_count }} present{{ $m->minutes ? ' · minutes taken' : '' }}</span>
                </span>
            </div>
        @empty
            <p class="empty-state">No past meetings yet.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $past])
    </section>
@endsection
