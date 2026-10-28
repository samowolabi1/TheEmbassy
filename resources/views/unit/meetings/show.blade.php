@extends('layouts.unit', ['title' => $meeting->title, 'active' => 'meetings'])

@php($dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '')

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.meetings.index', $unit) }}" class="box__link">← Meetings</a>
            <h1>{{ $meeting->title }}</h1>
            <span class="page-head__sub">{{ $meeting->starts_at->format('l j F Y, g:i A') }} · {{ count($present) }} present</span>
        </div>
        <div class="actions">
            @if ($meeting->online_url)<a href="{{ $meeting->online_url }}" class="btn btn--outline" target="_blank" rel="noopener">Join call</a>@endif
            @if ($meeting->checkInIsOpen())<a href="{{ route('unit.meetings.screen', [$unit, $meeting]) }}" class="btn btn--gold" target="_blank" rel="noopener">Show check-in code</a>@endif
        </div>
    </header>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Who was there</h2><span class="box__note">Members can check in themselves with the code, or tick them here</span></div>
            <form method="POST" action="{{ route('unit.meetings.mark', [$unit, $meeting]) }}">
                @csrf
                <div class="present-list">
                    @foreach ($members as $m)
                        <label><input type="checkbox" name="present[]" value="{{ $m->id }}" @checked(in_array($m->id, $present, true))>{{ $m->name }}</label>
                    @endforeach
                </div>
                <div style="padding: 0 20px 20px"><button type="submit" class="btn btn--navy">Save attendance</button></div>
            </form>
        </section>

        <section class="box side-col">
            <h2>Details &amp; minutes</h2>
            <form method="POST" action="{{ route('unit.meetings.update', [$unit, $meeting]) }}" class="form">
                @csrf
                @method('PUT')
                <label class="field">Title <input type="text" name="title" value="{{ $meeting->title }}" required maxlength="120"></label>
                <label class="field">Starts <input type="datetime-local" name="starts_at" value="{{ $dt($meeting->starts_at) }}" required></label>
                <label class="field">Ends <input type="datetime-local" name="ends_at" value="{{ $dt($meeting->ends_at) }}"></label>
                <label class="field">Where <input type="text" name="location" value="{{ $meeting->location }}" maxlength="255"></label>
                <label class="field">Online link <input type="url" name="online_url" value="{{ $meeting->online_url }}" maxlength="500"></label>
                <label class="field">Agenda <textarea name="agenda" maxlength="5000" style="height: 90px">{{ $meeting->agenda }}</textarea></label>
                <label class="field">Minutes <textarea name="minutes" maxlength="20000" style="height: 160px" placeholder="Decisions, action points and who's doing them">{{ $meeting->minutes }}</textarea></label>
                <button type="submit" class="btn btn--navy">Save</button>
            </form>
            <form method="POST" action="{{ route('unit.meetings.destroy', [$unit, $meeting]) }}" data-confirm="Remove this meeting?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--xs">Remove meeting</button>
            </form>
        </section>
    </div>
@endsection
