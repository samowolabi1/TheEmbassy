@extends('layouts.admin', ['title' => 'Attendance', 'active' => 'attendance'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Attendance</h1>
            <span class="page-head__sub">Schedule services so members can check themselves in at church or online, and record each service's headcount</span>
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            <div class="box__head" style="padding: 0"><h2>Sunday attendance</h2><span class="box__note">Last 8 Sundays recorded</span></div>
            @include('admin.partials.attendance-chart', ['sundays' => $sundays])
        </section>

        <section class="box side-col">
            <h2>Add a service</h2>
            <form method="POST" action="{{ route('admin.attendance.store') }}" class="form">
                @csrf
                <div class="form-row">
                    <label class="field">Date
                        <input type="date" name="held_on" value="{{ old('held_on', today()->toDateString()) }}" required>
                    </label>
                    <label class="field">Service
                        <select name="kind">
                            @foreach ($kinds as $key => $label)
                                <option value="{{ $key }}" @selected(old('kind', today()->isSunday() ? 'sunday' : 'midweek') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @error('held_on') <span class="field-error">{{ $message }}</span> @enderror
                <div class="form-row">
                    <label class="field">Starts <input type="time" name="start_time" value="{{ old('start_time', '09:00') }}"></label>
                    <label class="field">Ends <input type="time" name="end_time" value="{{ old('end_time', '11:00') }}"></label>
                </div>
                @error('start_time') <span class="field-error">{{ $message }}</span> @enderror
                @error('end_time') <span class="field-error">{{ $message }}</span> @enderror
                <label class="field">Title (optional) <input type="text" name="title" value="{{ old('title') }}" maxlength="120" placeholder="e.g. Thanksgiving night"></label>
                <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                    <legend style="margin-bottom: 6px">Members check themselves in</legend>
                    <label class="login__remember"><input type="checkbox" name="in_person_checkin" value="1" @checked(old('in_person_checkin', true))>At church (within the venue radius)</label>
                    <label class="login__remember"><input type="checkbox" name="online_checkin" value="1" @checked(old('online_checkin', true))>Online (with the code on the live stream)</label>
                </fieldset>
                <label class="field">Repeat weekly for
                    <select name="repeat">
                        @foreach ([0 => 'Just this once', 4 => '4 more weeks', 8 => '8 more weeks', 12 => '12 more weeks'] as $weeks => $label)
                            <option value="{{ $weeks }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if (! is_numeric(config('church.checkin.latitude')))
                    <span class="field__hint" style="color: #a6531a">Set the church's location in Settings &amp; roles before in-person check-in can work.</span>
                @endif
                <button type="submit" class="btn btn--navy">Save service</button>
            </form>
        </section>
    </div>

    @if ($upcoming->isNotEmpty())
        <section class="box box--flush">
            <div class="box__head"><h2>Today and coming up</h2><span class="box__note">Members check in at {{ route('check-in') }} from {{ (int) config('church.checkin.opens_before') }} minutes before the start</span></div>
            @foreach ($upcoming as $service)
                <div class="list-item">
                    <span class="list-item__main">
                        <a href="{{ route('admin.attendance.show', $service) }}" class="list-item__title">{{ $service->label() }}</a>
                        <span class="list-item__meta">{{ $service->starts_at->format('D j M · g:i A') }} · {{ $service->formatLabel() }}</span>
                    </span>
                    @if ($service->checkInIsOpen())
                        <span class="pill pill--ok">Check-in open</span>
                        <a href="{{ route('admin.attendance.screen', $service) }}" class="btn btn--navy btn--xs" target="_blank" rel="noopener">Show code screen</a>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    <section class="box box--flush">
        <div class="box__head"><h2>Past services</h2></div>
        <div class="table-wrap">
            <table class="table" style="min-width: 600px">
                <thead><tr><th>DATE</th><th>SERVICE</th><th class="num">IN PERSON</th><th class="num">ONLINE</th><th class="num">MEMBERS MARKED</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td style="white-space: nowrap; font-weight: 700">{{ $service->held_on->format('D j M Y') }}</td>
                            <td>{{ $service->label() }}</td>
                            <td class="num">{{ number_format($service->in_person) }}</td>
                            <td class="num">{{ number_format($service->online) }}</td>
                            <td class="num">{{ number_format($service->members_count) }}</td>
                            <td class="num"><a href="{{ route('admin.attendance.show', $service) }}" class="btn btn--ghost btn--xs">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="empty-state">No services recorded yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $services])
    </section>
@endsection
