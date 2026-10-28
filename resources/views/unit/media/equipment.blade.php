@extends('layouts.unit', ['title' => 'Equipment', 'active' => 'equipment'])

@php($cond = ['good' => 'pill--ok', 'fair' => 'pill--info', 'faulty' => 'pill--warn'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Equipment</h1>
            <span class="page-head__sub">{{ $equipment->count() }} items{{ $value ? ' · worth ₦'.number_format($value / 100) : '' }} · {{ $loans->count() }} out · {{ $issues->count() }} faults open</span>
        </div>
        @if ($canLead)<div class="actions"><a href="#new-item" class="btn btn--gold"><x-icon name="plus" :size="16" />Add an item</a></div>@endif
    </header>

    <nav class="tabs" aria-label="Category">
        <a href="{{ route('unit.equipment.index', $unit) }}" class="tab" @if (! $category) aria-current="page" @endif>All</a>
        @foreach (\App\Models\Equipment::CATEGORIES as $key => $label)
            <a href="{{ route('unit.equipment.index', [$unit, 'category' => $key]) }}" class="tab" @if ($category === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($issues->isNotEmpty())
        <section class="box box--flush">
            <div class="box__head"><h2>Faults to fix</h2></div>
            @foreach ($issues as $issue)
                <article class="prayer-card">
                    <div class="prayer-card__head"><span class="prayer-card__who">{{ $issue->equipment->name }}</span><span class="prayer-card__when">Reported {{ $issue->created_at->diffForHumans() }} by {{ $issue->reporter?->name ?? 'someone' }}</span></div>
                    <p class="prayer-card__text">{{ $issue->description }}</p>
                    @if ($canLead)
                        <form method="POST" action="{{ route('unit.equipment.resolve', [$unit, $issue]) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                            @csrf
                            <label class="sr-only" for="fix-{{ $issue->id }}">What was done</label>
                            <input id="fix-{{ $issue->id }}" type="text" name="resolution" required maxlength="255" placeholder="What was done" class="select-sm" style="min-width: 200px">
                            <label class="sr-only" for="cost-{{ $issue->id }}">Cost</label>
                            <input id="cost-{{ $issue->id }}" type="number" name="cost" min="0" step="1" placeholder="Cost ₦ (optional)" class="select-sm" style="width: 150px">
                            <button type="submit" class="btn btn--navy btn--xs">Mark fixed</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <section class="box box--flush">
        @forelse ($equipment as $item)
            <details class="list-item list-item--edit">
                <summary>
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $item->name }}@if ($item->tag) <span style="font-family: ui-monospace, monospace; font-weight: 500; color: var(--slate)">{{ $item->tag }}</span>@endif</span>
                        <span class="list-item__meta">{{ \App\Models\Equipment::CATEGORIES[$item->category] }}{{ $item->location ? ' · '.$item->location : '' }}@if ($item->currentLoan) · with {{ $item->currentLoan->member->name }}{{ $item->currentLoan->due_on ? ', due '.$item->currentLoan->due_on->format('j M') : '' }}@endif</span>
                    </span>
                    @if ($item->currentLoan?->isOverdue())<span class="pill pill--warn">Overdue</span>@endif
                    <span class="pill {{ $cond[$item->condition] ?? 'pill--info' }}">{{ ucfirst($item->condition) }}</span>
                    <span class="pill pill--draft">{{ \App\Models\Equipment::STATUSES[$item->status] }}</span>
                </summary>
                <div class="stack" style="gap: 14px">
                    @if ($item->serial || $item->notes)<p class="box__note" style="margin: 0">{{ $item->serial ? 'Serial '.$item->serial.'. ' : '' }}{{ $item->notes }}</p>@endif
                    <form method="POST" action="{{ route('unit.equipment.report', [$unit, $item]) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                        @csrf
                        <label class="sr-only" for="report-{{ $item->id }}">What's wrong with {{ $item->name }}</label>
                        <input id="report-{{ $item->id }}" type="text" name="description" required maxlength="2000" placeholder="What's wrong?" class="select-sm" style="flex: 1 1 220px">
                        <button type="submit" class="btn btn--ghost btn--xs">Report a fault</button>
                    </form>
                    @if ($canLead)
                        @if ($item->status === 'available')
                            <form method="POST" action="{{ route('unit.equipment.out', [$unit, $item]) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                                @csrf
                                <label class="sr-only" for="out-{{ $item->id }}">Who's taking it</label>
                                <select id="out-{{ $item->id }}" name="member_id" required class="select-sm">
                                    <option value="">Who's taking it?</option>
                                    @foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                                </select>
                                <label class="sr-only" for="due-{{ $item->id }}">Due back</label>
                                <input id="due-{{ $item->id }}" type="date" name="due_on" min="{{ today()->toDateString() }}" class="select-sm">
                                <button type="submit" class="btn btn--navy btn--xs">Check out</button>
                            </form>
                        @elseif ($item->status === 'checked_out')
                            <form method="POST" action="{{ route('unit.equipment.in', [$unit, $item]) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                                @csrf
                                <label class="sr-only" for="cond-{{ $item->id }}">Condition</label>
                                <select id="cond-{{ $item->id }}" name="condition" class="select-sm">
                                    <option value="good">Returned in good condition</option>
                                    <option value="fair">Fair (some wear)</option>
                                    <option value="faulty">Faulty</option>
                                </select>
                                <label class="sr-only" for="fault-{{ $item->id }}">Fault</label>
                                <input id="fault-{{ $item->id }}" type="text" name="fault" maxlength="2000" placeholder="If faulty, what's wrong" class="select-sm">
                                <button type="submit" class="btn btn--navy btn--xs">Check in</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('unit.equipment.update', [$unit, $item]) }}" class="form">
                            @csrf
                            @method('PUT')
                            @include('unit.media.equipment-fields', ['item' => $item])
                            <div class="form-row">
                                <label class="field">Status
                                    <select name="status" @disabled($item->status === 'checked_out')>
                                        @foreach (['available' => 'Available', 'repair' => 'Being repaired', 'retired' => 'Retired'] as $key => $label)
                                            <option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div><button type="submit" class="btn btn--ghost btn--xs">Save</button></div>
                        </form>
                    @endif
                </div>
            </details>
        @empty
            <p class="empty-state">No equipment listed yet.</p>
        @endforelse
    </section>

    @if ($canLead)
        <section class="box" id="new-item">
            <h2>Add an item</h2>
            <form method="POST" action="{{ route('unit.equipment.store', $unit) }}" class="form">
                @csrf
                @include('unit.media.equipment-fields', ['item' => new \App\Models\Equipment(['category' => 'camera', 'condition' => 'good'])])
                <div><button type="submit" class="btn btn--navy">Add</button></div>
            </form>
        </section>
    @endif
@endsection
