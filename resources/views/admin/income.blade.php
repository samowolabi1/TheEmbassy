@extends('layouts.admin', ['title' => 'Income & summary', 'active' => 'income'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100))
@php($totalIn = $in->sum())
@php($totalOut = $out->sum())

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Income &amp; summary</h1>
            <span class="page-head__sub">Money in and out for {{ $month->format('F Y') }}, counting confirmed income and approved expenses. Online giving is kept separately under Giving.</span>
        </div>
        <div class="actions">
            <form method="GET" action="{{ route('admin.income.index') }}" style="display: flex; gap: 6px">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label class="sr-only" for="summary-month">Month</label>
                <input id="summary-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="select-sm">
                <button type="submit" class="btn btn--ghost btn--xs">Show</button>
            </form>
            <a href="{{ route('admin.income.export', ['month' => $month->format('Y-m')]) }}" class="btn btn--outline">Export {{ $month->format('M Y') }}</a>
            <a href="{{ route('admin.money.index') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Record</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile"><span class="stat-tile__label">Money in</span><span class="stat-tile__num">{{ $naira($totalIn) }}</span>@if ($pendingIn)<span class="stat-tile__foot">{{ $naira($pendingIn) }} still to confirm</span>@endif</div>
        <div class="stat-tile"><span class="stat-tile__label">Money out</span><span class="stat-tile__num">{{ $naira($totalOut) }}</span>@if ($pendingOut)<span class="stat-tile__foot">{{ $naira($pendingOut) }} still to approve</span>@endif</div>
        <div class="stat-tile"><span class="stat-tile__label">{{ $totalIn >= $totalOut ? 'Left over' : 'Short by' }}</span><span class="stat-tile__num" style="color: {{ $totalIn >= $totalOut ? 'var(--navy)' : '#b4462a' }}">{{ $naira(abs($totalIn - $totalOut)) }}</span></div>
    </div>

    <div class="row">
        <section class="box grow">
            <h2>Money in by category</h2>
            @forelse ($in as $category => $total)
                <div class="budget-line">
                    <span class="budget-line__head"><span>{{ $category }}</span><span>{{ $naira($total) }}</span></span>
                    <div class="meter" role="img" aria-label="{{ $totalIn ? round($total * 100 / $totalIn) : 0 }}% of money in"><span style="width: {{ $totalIn ? round($total * 100 / $totalIn) : 0 }}%"></span></div>
                </div>
            @empty
                <p class="box__note">No confirmed income this month.</p>
            @endforelse
        </section>
        <section class="box grow">
            <h2>Money out by category</h2>
            @forelse ($out as $category => $total)
                <div class="budget-line">
                    <span class="budget-line__head"><span>{{ $category }}</span><span>{{ $naira($total) }}</span></span>
                    <div class="meter" role="img" aria-label="{{ $totalOut ? round($total * 100 / $totalOut) : 0 }}% of money out"><span style="width: {{ $totalOut ? round($total * 100 / $totalOut) : 0 }}%"></span></div>
                </div>
            @empty
                <p class="box__note">No approved expenses this month. <a href="{{ route('admin.expenses.index') }}" class="box__link">Expenses &amp; budgets →</a></p>
            @endforelse
        </section>
    </div>

    <nav class="tabs" aria-label="Income status">
        @foreach ($tabs as $key => $t)
            <a href="{{ route('admin.income.index', ['tab' => $key, 'month' => $month->format('Y-m')]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $t['label'] }} <span>{{ number_format($t['count']) }}</span></a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($incomes as $i)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $i->title }}</span>
                    <span class="pill pill--info">{{ $i->category }}</span>
                    <strong style="color: var(--navy)">{{ $naira($i->amount) }}</strong>
                    <span class="prayer-card__when">{{ $i->received_on->format('j M Y') }} · {{ $i->methodLabel() }}</span>
                </div>
                @if ($i->description)
                    <p class="prayer-card__text">{{ $i->description }}</p>
                @endif
                <span class="prayer-card__contact">
                    Recorded by {{ $i->recorder?->name ?? 'someone no longer on staff' }}
                    @if ($i->decider) · {{ $i->status === 'confirmed' ? 'Confirmed' : 'Declined' }} by {{ $i->decider->name }}@endif
                    @if ($i->decline_reason) · "{{ $i->decline_reason }}"@endif
                </span>
                <div class="list-item__actions" style="flex-wrap: wrap">
                    @if ($i->hasReceipt())
                        <a href="{{ route('admin.income.receipt', $i) }}" class="btn btn--ghost btn--xs">View receipt</a>
                    @endif
                    @if ($i->status === 'pending' && ($i->recorded_by !== auth()->id() || auth()->user()->isAdmin()))
                        <form method="POST" action="{{ route('admin.income.confirm', $i) }}">
                            @csrf
                            <button type="submit" class="btn btn--navy btn--xs">Confirm</button>
                        </form>
                        <form method="POST" action="{{ route('admin.income.decline', $i) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                            @csrf
                            <label class="sr-only" for="decline-{{ $i->id }}">Reason</label>
                            <input id="decline-{{ $i->id }}" type="text" name="reason" required maxlength="255" placeholder="Reason" class="select-sm" style="min-width: 180px">
                            <button type="submit" class="btn btn--danger btn--xs">Decline</button>
                        </form>
                    @elseif ($i->status === 'pending')
                        <span class="box__note" style="margin: 0">Waiting for someone else to confirm</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'pending' ? 'Nothing waiting to be confirmed.' : 'Nothing here yet.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $incomes])
    </section>

    <section class="box" id="categories">
        <h2>Categories</h2>
        <p class="box__note">Staff choose from these when they record money. Renaming one updates past entries too. Hide one you no longer use; its history stays.</p>
        @if ($errors->categories->any())
            <p class="field-error">{{ $errors->categories->first() }}</p>
        @endif
        <div class="row">
            @foreach (['in' => 'Money in', 'out' => 'Money out'] as $kind => $label)
                <div class="grow stack" style="gap: 8px">
                    <h3 style="margin: 0">{{ $label }}</h3>
                    @foreach ($categories[$kind] ?? [] as $c)
                        <form method="POST" action="{{ route('admin.money-categories.update', $c) }}" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="cat-{{ $c->id }}">Name</label>
                            <input id="cat-{{ $c->id }}" type="text" name="name" value="{{ $c->name }}" maxlength="60" required class="select-sm" style="flex: 1; min-width: 160px">
                            <label class="login__remember" style="margin: 0"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)>In use</label>
                            <button type="submit" class="btn btn--ghost btn--xs">Save</button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ route('admin.money-categories.store') }}" style="display: flex; gap: 8px">
                        @csrf
                        <input type="hidden" name="kind" value="{{ $kind }}">
                        <label class="sr-only" for="new-{{ $kind }}">New category</label>
                        <input id="new-{{ $kind }}" type="text" name="name" maxlength="60" required placeholder="New category" class="select-sm" style="flex: 1">
                        <button type="submit" class="btn btn--navy btn--xs">Add</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
@endsection
