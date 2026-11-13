@extends('layouts.unit', ['title' => 'Requests', 'active' => 'requests'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Requests</h1>
            <span class="page-head__sub">What other units and the church office have asked for. {{ $counts['open'] }} open{{ $counts['mine'] ? ', '.$counts['mine'].' with you' : '' }}.</span>
        </div>
        <div class="actions"><a href="{{ route('unit.library.index', $unit) }}" class="btn btn--ghost">File library</a></div>
    </header>

    <nav class="tabs" aria-label="Requests">
        @foreach (\App\Http\Controllers\Unit\MediaRequestController::TABS as $key => $label)
            <a href="{{ route('unit.requests.index', [$unit, 'tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $label }}{{ $key === 'open' && $counts['open'] ? ' ('.$counts['open'].')' : '' }}{{ $key === 'mine' && $counts['mine'] ? ' ('.$counts['mine'].')' : '' }}</a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($requests as $r)
            <a href="{{ route('unit.requests.show', [$unit, $r]) }}" class="list-item" style="color: var(--ink)">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $r->title }}</span>
                    <span class="list-item__meta">{{ $r->typeLabel() }} · {{ $r->fromLabel() }} · {{ $r->requester?->member?->name ?? $r->requester?->name }}{{ $r->assignee ? ' · with '.$r->assignee->name : '' }} · {{ $r->reference }}</span>
                </span>
                <span class="pill {{ $r->isOverdue() ? 'pill--warn' : ($r->isUrgent() ? 'pill--gold' : 'pill--draft') }}">{{ $r->isOverdue() ? 'Overdue · ' : '' }}Due {{ $r->needed_by->format('D j M') }}</span>
                <span class="pill {{ match ($r->status) { 'new' => 'pill--gold', 'in_progress' => 'pill--info', 'delivered', 'completed' => 'pill--ok', default => 'pill--draft' } }}">{{ \App\Models\MediaRequest::STATUSES[$r->status] }}</span>
            </a>
        @empty
            <p class="empty-state">{{ $tab === 'open' ? 'Nothing waiting. Unit heads and the church office ask from "Ask the media team" on their account.' : 'Nothing here.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $requests])
    </section>
@endsection
