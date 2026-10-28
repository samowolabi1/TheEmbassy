@extends('layouts.unit', ['title' => 'Welfare cases', 'active' => 'welfare'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Welfare cases</h1>
            <span class="page-head__sub">Confidential: only the welfare HOD and assistant, pastors and each case's worker can see a case. You can approve up to ₦{{ number_format($limit) }}; above that a pastor approves.</span>
        </div>
        <div class="actions">
            <a href="{{ route('unit.welfare.export', $unit) }}" class="btn btn--outline">Export (no names)</a>
            <a href="{{ route('unit.welfare.create', $unit) }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Open a case</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy"><span class="stat-tile__label">Open cases</span><span class="stat-tile__num">{{ $stats['open'] }}</span><span class="stat-tile__foot">{{ $stats['urgent'] }} urgent</span></div>
        <div class="stat-tile"><span class="stat-tile__label">Given ({{ now()->format('M') }})</span><span class="stat-tile__num">{{ $naira($stats['spent']) }}</span><span class="stat-tile__foot">@if ($stats['budget']){{ round($stats['spent'] * 100 / max(1, $stats['budget'])) }}% of the {{ $naira($stats['budget']) }} budget @else No monthly budget set @endif</span></div>
        <div class="stat-tile"><span class="stat-tile__label">Given ({{ now()->year }})</span><span class="stat-tile__num">{{ $naira($stats['year']) }}</span><span class="stat-tile__foot">{{ $byCategory->sum() }} cases this year</span></div>
    </div>

    <nav class="tabs" aria-label="Cases">
        @foreach ($tabs as $key => $t)
            <a href="{{ route('unit.welfare.index', [$unit, 'tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $t['label'] }}@if ($t['count'] !== null) <span>{{ $t['count'] }}</span>@endif</a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($cases as $c)
            <a href="{{ route('unit.welfare.show', [$unit, $c]) }}" class="list-item" style="color: var(--ink)">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $c->personName() }} · {{ $c->summary }}</span>
                    <span class="list-item__meta">{{ $c->reference }} · {{ $c->categoryLabel() }} · opened {{ $c->created_at->format('j M') }}{{ $c->worker ? ' · '.$c->worker->name : '' }}{{ $c->next_check_on ? ' · check in '.$c->next_check_on->format('j M') : '' }}</span>
                </span>
                @if ($c->urgency === 'urgent' && $c->isOpen())<span class="pill pill--warn">Urgent</span>@endif
                <span class="pill {{ in_array($c->status, ['closed', 'declined']) ? 'pill--draft' : ($c->status === 'awaiting_pastor' ? 'pill--gold' : 'pill--info') }}">{{ $c->statusLabel() }}</span>
            </a>
        @empty
            <p class="empty-state">No cases here.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $cases])
    </section>

    @if ($byCategory->isNotEmpty())
        <section class="box">
            <h2>This year by category</h2>
            @php($top = max(1, $byCategory->max()))
            @foreach ($byCategory->sortDesc() as $category => $count)
                <div class="budget-line">
                    <span class="budget-line__head"><span>{{ config("welfare.categories.$category", $category) }}</span><span>{{ $count }}</span></span>
                    <div class="meter"><span style="width: {{ round($count * 100 / $top) }}%"></span></div>
                </div>
            @endforeach
        </section>
    @endif
@endsection
