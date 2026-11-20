@extends('layouts.admin', ['title' => $programme->title, 'active' => 'empowerment'])

@section('content')
    @include('admin.empowerment._head', ['tab' => 'applications'])

    @if ($tracks->isNotEmpty())
        <section class="box" id="choices-summary">
            <h2>Choices <x-tip text="How many picked each choice (as any of their choices), how many you've accepted, and the places you set." /></h2>
            <div class="track-stats">
                @foreach ($tracks as $t)
                    <a href="{{ route('admin.empowerment.show', [$programme, 'track' => $t->id]) }}" @class(['track-stat', 'is-current' => $filters['track'] === $t->id])>
                        <strong>{{ $t->name }}</strong>
                        <span>{{ $t->applicants_count }} picked · {{ $t->accepted_count }} accepted{{ $t->slots ? ' of '.$t->slots : '' }}</span>
                        @if ($t->slots)<span class="progress" aria-hidden="true"><span style="width: {{ min(100, round($t->accepted_count / $t->slots * 100)) }}%"></span></span>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <nav class="tabs" aria-label="Status">
        <a href="{{ route('admin.empowerment.show', [$programme, 'track' => $filters['track']]) }}" class="tab" @if (! $filters['status']) aria-current="page" @endif>All <span>{{ $counts->sum() }}</span></a>
        @foreach (\App\Models\EmpowermentApplication::STATUSES as $key => $label)
            <a href="{{ route('admin.empowerment.show', [$programme, 'status' => $key, 'track' => $filters['track']]) }}" class="tab" @if ($filters['status'] === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>

    <section class="box box--flush">
        <form method="GET" class="toolbar" style="padding: 14px 18px; display: flex; flex-wrap: wrap; gap: 8px">
            @if ($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <label class="sr-only" for="emp-q">Search</label>
            <input id="emp-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, phone or reference" class="select-sm" style="flex: 1 1 200px">
            <label class="sr-only" for="emp-track">Choice</label>
            <select id="emp-track" name="track" class="select-sm">
                <option value="">Every choice</option>
                @foreach ($tracks as $t)<option value="{{ $t->id }}" @selected($filters['track'] === $t->id)>{{ $t->name }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn--ghost btn--xs">Filter</button>
        </form>
        @error('ids') <p class="field-error" style="margin: 0 18px 10px">{{ $message }}</p> @enderror
        <form method="POST" action="{{ route('admin.empowerment.bulk', $programme) }}" id="bulk">
            @csrf
            <div class="table-wrap">
                <table class="table" style="min-width: 820px">
                    <thead><tr><th style="width: 36px"><span class="sr-only">Select</span><input type="checkbox" data-check-all aria-label="Select all"></th><th>NAME</th><th class="num">AGE</th><th>PHONE</th><th>CHOICES</th><th>STATUS</th><th>APPLIED</th><th class="num"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                        @forelse ($applications as $a)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $a->id }}" aria-label="Select {{ $a->name }}"></td>
                                <td><a href="{{ route('admin.empowerment.application', [$programme, $a]) }}" style="font-weight: 700">{{ $a->name }}</a><br><span class="box__note" style="margin: 0">{{ $a->reference }}{{ $a->location ? ' · '.$a->location : '' }}</span></td>
                                <td class="num">{{ $a->age() ?? '—' }}</td>
                                <td>@if ($a->whatsappUrl())<a href="{{ $a->whatsappUrl() }}" target="_blank" rel="noopener">{{ $a->phone }}</a>@else{{ $a->phone }}@endif</td>
                                <td>@foreach ($a->choices as $c)<span class="box__note" style="margin: 0; display: block">{{ $c->pivot->rank }}. {{ $c->name }}</span>@endforeach</td>
                                <td><span @class(['pill', 'pill--info' => $a->status === 'new', 'pill--ok' => in_array($a->status, ['accepted', 'completed'], true), 'pill--warn' => in_array($a->status, ['shortlisted', 'waitlisted'], true), 'pill--draft' => $a->status === 'declined'])>{{ $a->statusLabel() }}</span></td>
                                <td style="color: var(--slate)">{{ $a->created_at->format('j M') }}</td>
                                <td class="num"><a href="{{ route('admin.empowerment.application', [$programme, $a]) }}" class="btn btn--ghost btn--xs">Open<span class="sr-only"> {{ $a->name }}</span></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><p class="empty-state">{{ $programme->is_published ? 'No applications here yet.' : 'No applications yet. Publish the programme so people can apply.' }}</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->isNotEmpty())
                <div class="bulk-bar">
                    <label for="bulk-status" class="box__note" style="margin: 0; font-weight: 700">With the ticked ones:</label>
                    <select id="bulk-status" name="status" class="select-sm">
                        @foreach (\App\Models\EmpowermentApplication::STATUSES as $key => $label)<option value="{{ $key }}">Mark {{ strtolower($label) }}</option>@endforeach
                    </select>
                    <label class="login__remember"><input type="checkbox" name="notify" value="1" checked>Let them know</label>
                    <button type="submit" class="btn btn--navy btn--xs">Apply</button>
                </div>
            @endif
        </form>
        @include('admin.partials.pager', ['paginator' => $applications])
    </section>
@endsection
