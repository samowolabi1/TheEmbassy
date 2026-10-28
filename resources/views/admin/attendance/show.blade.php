@extends('layouts.admin', ['title' => $service->label(), 'active' => 'attendance'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.attendance.index') }}" class="box__link">← Attendance</a>
            <h1>{{ $service->label() }}</h1>
            <span class="page-head__sub">{{ $service->starts_at ? $service->starts_at->format('l j F Y · g:i A') : $service->held_on->format('l j F Y') }} · {{ $service->formatLabel() }} · {{ count($present) }} members present</span>
        </div>
        @if ($service->checkInIsOpen())
            <div class="actions">
                <a href="{{ route('admin.attendance.screen', $service) }}" class="btn btn--gold" target="_blank" rel="noopener">Show code screen</a>
            </div>
        @endif
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy"><span class="stat-tile__label">Checked in at church</span><span class="stat-tile__num">{{ $checkedIn->where('pivot.mode', 'in_person')->count() }}</span><span class="stat-tile__foot">Members, by phone</span></div>
        <div class="stat-tile"><span class="stat-tile__label">Checked in online</span><span class="stat-tile__num">{{ $checkedIn->where('pivot.mode', 'online')->count() }}</span><span class="stat-tile__foot">With the stream code</span></div>
        <div class="stat-tile"><span class="stat-tile__label">Headcount</span><span class="stat-tile__num">{{ number_format($service->total()) }}</span><span class="stat-tile__foot">{{ number_format($service->in_person) }} in person · {{ number_format($service->online) }} online, counted by ushers</span></div>
    </div>

    @if ($checkedIn->isNotEmpty())
        <section class="box box--flush">
            <div class="box__head"><h2>Checked themselves in</h2></div>
            <div class="table-wrap">
                <table class="table" style="min-width: 480px">
                    <thead><tr><th>MEMBER</th><th>HOW</th><th>TIME</th><th class="num">DISTANCE</th></tr></thead>
                    <tbody>
                        @foreach ($checkedIn as $m)
                            <tr>
                                <td style="font-weight: 700">{{ $m->name }}</td>
                                <td>{{ $m->pivot->mode === 'online' ? 'Online' : 'At church' }}</td>
                                <td style="color: var(--slate)">{{ \Illuminate\Support\Carbon::parse($m->pivot->checked_in_at)->format('g:i A') }}</td>
                                <td class="num">{{ $m->pivot->distance_m !== null ? $m->pivot->distance_m.' m' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head" style="padding-bottom: 0"><h2>Mark members present</h2><span class="box__note">For members without a phone or account</span></div>
            <form method="GET" action="{{ route('admin.attendance.show', $service) }}" class="box__head filters-bar" role="search">
                <label class="field filters-bar__search"><span class="sr-only">Search members</span>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Find a member">
                </label>
                <label class="sr-only" for="unit">Unit</label>
                <select id="unit" name="unit" class="select-sm">
                    <option value="">All units</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected((int) ($filters['unit'] ?? 0) === $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn--ghost btn--xs">Filter</button>
            </form>
            <form method="POST" action="{{ route('admin.attendance.mark', $service) }}">
                @csrf
                <div class="present-list">
                    @forelse ($members as $m)
                        <input type="hidden" name="shown[]" value="{{ $m->id }}">
                        <label><input type="checkbox" name="present[]" value="{{ $m->id }}" @checked(in_array($m->id, $present, true))>{{ $m->name }}</label>
                    @empty
                        <p class="empty-state">No members match.</p>
                    @endforelse
                </div>
                @if ($members->isNotEmpty())
                    <div style="padding: 0 20px 20px"><button type="submit" class="btn btn--navy">Save who was here</button></div>
                @endif
            </form>
        </section>

        <section class="box side-col">
            <h2>Headcount</h2>
            <form method="POST" action="{{ route('admin.attendance.update', $service) }}" class="form">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <label class="field">Date <input type="date" name="held_on" value="{{ $service->held_on->toDateString() }}" required></label>
                    <label class="field">Service
                        <select name="kind">
                            @foreach ($kinds as $key => $label)
                                <option value="{{ $key }}" @selected($service->kind === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @error('held_on') <span class="field-error">{{ $message }}</span> @enderror
                <div class="form-row">
                    <label class="field">Starts <input type="time" name="start_time" value="{{ $service->starts_at?->format('H:i') }}"></label>
                    <label class="field">Ends <input type="time" name="end_time" value="{{ $service->ends_at?->format('H:i') }}"></label>
                </div>
                @error('start_time') <span class="field-error">{{ $message }}</span> @enderror
                <label class="login__remember"><input type="checkbox" name="in_person_checkin" value="1" @checked($service->in_person_checkin)>Check-in at church</label>
                <label class="login__remember"><input type="checkbox" name="online_checkin" value="1" @checked($service->online_checkin)>Check-in online</label>
                <label class="field">Title <input type="text" name="title" value="{{ $service->title }}" maxlength="120"></label>
                <div class="form-row">
                    <label class="field">In person <input type="number" name="in_person" value="{{ $service->in_person }}" min="0" required></label>
                    <label class="field">Online <input type="number" name="online" value="{{ $service->online }}" min="0" required></label>
                </div>
                <label class="field">Notes <input type="text" name="notes" value="{{ $service->notes }}" maxlength="255"></label>
                <button type="submit" class="btn btn--navy">Save</button>
            </form>
            <form method="POST" action="{{ route('admin.attendance.destroy', $service) }}" data-confirm="Remove this service and its attendance?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--xs">Remove service</button>
            </form>
        </section>
    </div>
@endsection
