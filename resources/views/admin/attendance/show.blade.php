@extends('layouts.admin', ['title' => $service->label(), 'active' => 'attendance'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.attendance.index') }}" class="box__link">← Attendance</a>
            <h1>{{ $service->label() }}</h1>
            <span class="page-head__sub">{{ $service->held_on->format('l j F Y') }} · {{ number_format($service->total()) }} in total · {{ count($present) }} members marked present</span>
        </div>
    </header>

    <div class="row">
        <section class="box box--flush grow">
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
                    <label class="field">Date <input type="date" name="held_on" value="{{ $service->held_on->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
                    <label class="field">Service
                        <select name="kind">
                            @foreach ($kinds as $key => $label)
                                <option value="{{ $key }}" @selected($service->kind === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @error('held_on') <span class="field-error">{{ $message }}</span> @enderror
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
