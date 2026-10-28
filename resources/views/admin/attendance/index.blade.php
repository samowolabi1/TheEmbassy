@extends('layouts.admin', ['title' => 'Attendance', 'active' => 'attendance'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Attendance</h1>
            <span class="page-head__sub">Headcounts for each service, in person and online, and who was there</span>
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            <div class="box__head" style="padding: 0"><h2>Sunday attendance</h2><span class="box__note">Last 8 Sundays recorded</span></div>
            @include('admin.partials.attendance-chart', ['sundays' => $sundays])
        </section>

        <section class="box side-col">
            <h2>Record a service</h2>
            <form method="POST" action="{{ route('admin.attendance.store') }}" class="form">
                @csrf
                <div class="form-row">
                    <label class="field">Date
                        <input type="date" name="held_on" value="{{ old('held_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
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
                <label class="field">Title (optional) <input type="text" name="title" value="{{ old('title') }}" maxlength="120" placeholder="e.g. Thanksgiving night"></label>
                <div class="form-row">
                    <label class="field">In person <input type="number" name="in_person" value="{{ old('in_person', 0) }}" min="0" required></label>
                    <label class="field">Online <input type="number" name="online" value="{{ old('online', 0) }}" min="0" required></label>
                </div>
                <button type="submit" class="btn btn--navy">Save and mark members</button>
            </form>
        </section>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Services</h2></div>
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
