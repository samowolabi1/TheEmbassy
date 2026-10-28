@extends('layouts.unit', ['title' => 'Overview', 'active' => 'overview'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>{{ $unit->name }}</h1>
            <span class="page-head__sub">HOD: {{ $unit->hod?->name ?? 'not set' }}@if ($unit->assistant) · Assistant: {{ $unit->assistant->name }}@endif</span>
        </div>
        <div class="actions">
            <a href="{{ route('unit.meetings.index', $unit) }}#new-meeting" class="btn btn--outline">Set up a meeting</a>
            <a href="{{ route('unit.messages', $unit) }}" class="btn btn--gold">Message the unit</a>
        </div>
    </header>

    <div class="stats">
        <a href="{{ route('unit.members', $unit) }}" class="stat-tile stat-tile--navy"><span class="stat-tile__label">Members</span><span class="stat-tile__num">{{ $memberCount }}</span><span class="stat-tile__foot">Active in the unit</span></a>
        <a href="{{ route('unit.attendance', $unit) }}" class="stat-tile"><span class="stat-tile__label">Sunday attendance</span><span class="stat-tile__num">{{ $attendance === null ? '—' : $attendance.'%' }}</span><span class="stat-tile__foot">Last 8 Sundays recorded</span></a>
        <a href="{{ route('unit.meetings.index', $unit) }}" class="stat-tile"><span class="stat-tile__label">Next meeting</span><span class="stat-tile__num" style="font-size: 22px">{{ $meetings->first()?->starts_at->format('D j M') ?? 'None set' }}</span><span class="stat-tile__foot">{{ $meetings->first()?->title ?? 'Schedule one for the unit' }}</span></a>
    </div>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Coming up on the rota</h2><a href="{{ route('unit.rota', $unit) }}" class="box__link">Rota</a></div>
            @forelse ($duties as $duty)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $duty->member->name }} · {{ $duty->role }}</span>
                        <span class="list-item__meta">{{ $duty->service->label() }} · {{ $duty->service->starts_at?->format('D j M, g:i A') ?? $duty->service->held_on->format('D j M') }}</span>
                    </span>
                </div>
            @empty
                <p class="empty-state">Nobody is on the rota yet.</p>
            @endforelse
        </section>

        <section class="box side-col">
            <h2>Check on</h2>
            <span class="box__note">Not at church for three weeks or more</span>
            @forelse ($away as $m)
                <div style="display: flex; justify-content: space-between; gap: 10px; font-size: 14px">
                    <strong>{{ $m->name }}</strong>
                    @if ($m->whatsappUrl())<a href="{{ $m->whatsappUrl() }}" class="box__link" target="_blank" rel="noopener">WhatsApp</a>@endif
                </div>
            @empty
                <span class="box__note">Everyone has been around lately.</span>
            @endforelse
            <h2 style="margin-top: 10px">Birthdays soon</h2>
            @forelse ($birthdays as $m)
                <span style="font-size: 14px"><strong>{{ $m->name }}</strong> · {{ $m->birthday->format('j M') }}</span>
            @empty
                <span class="box__note">None in the next two weeks.</span>
            @endforelse
        </section>
    </div>
@endsection
