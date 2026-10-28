@extends('layouts.admin', ['title' => 'Expenses & budgets', 'active' => 'expenses'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Expenses &amp; budgets</h1>
            <span class="page-head__sub">Log spending with a receipt. Someone other than the person who logged it approves.</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.expenses.export', ['month' => $month->format('Y-m')]) }}" class="btn btn--outline">Export {{ $month->format('M Y') }}</a>
            <a href="#new-expense" class="btn btn--gold"><x-icon name="plus" :size="16" />New expense</a>
        </div>
    </header>

    <nav class="tabs" aria-label="Expense status">
        @foreach ($tabs as $key => $t)
            <a href="{{ route('admin.expenses.index', ['tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $t['label'] }} <span>{{ number_format($t['count']) }}</span></a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($expenses as $e)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $e->title }}</span>
                    <span class="pill pill--info">{{ $e->category }}</span>
                    <strong style="color: var(--navy)">{{ $naira($e->amount) }}</strong>
                    <span class="prayer-card__when">{{ $e->spent_on->format('j M Y') }}</span>
                </div>
                @if ($e->description)
                    <p class="prayer-card__text">{{ $e->description }}</p>
                @endif
                <span class="prayer-card__contact">
                    Logged by {{ $e->requester?->name ?? 'someone no longer on staff' }}
                    @if ($e->decider) · {{ $e->status === 'approved' ? 'Approved' : 'Declined' }} by {{ $e->decider->name }}@endif
                    @if ($e->decline_reason) · "{{ $e->decline_reason }}"@endif
                </span>
                <div class="list-item__actions" style="flex-wrap: wrap">
                    @if ($e->hasReceipt())
                        <a href="{{ route('admin.expenses.receipt', $e) }}" class="btn btn--ghost btn--xs">View receipt</a>
                    @else
                        <span class="pill pill--warn">No receipt</span>
                    @endif
                    @if ($e->status === 'pending' && ($e->requested_by !== auth()->id() || auth()->user()->isAdmin()))
                        <form method="POST" action="{{ route('admin.expenses.approve', $e) }}">
                            @csrf
                            <button type="submit" class="btn btn--navy btn--xs">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.expenses.decline', $e) }}" style="display: flex; gap: 8px; flex-wrap: wrap">
                            @csrf
                            <label class="sr-only" for="decline-{{ $e->id }}">Reason</label>
                            <input id="decline-{{ $e->id }}" type="text" name="reason" required maxlength="255" placeholder="Reason" class="select-sm" style="min-width: 180px">
                            <button type="submit" class="btn btn--danger btn--xs">Decline</button>
                        </form>
                    @elseif ($e->status === 'pending')
                        <span class="box__note" style="margin: 0">Waiting for someone else to approve</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'pending' ? 'Nothing waiting for approval.' : 'Nothing here yet.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $expenses])
    </section>

    <div class="row">
        <section class="box grow" id="new-expense">
            <h2>New expense</h2>
            <form method="POST" action="{{ route('admin.expenses.store') }}" enctype="multipart/form-data" class="form">
                @csrf
                <label class="field">What for
                    <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Generator diesel for Sunday">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="form-row">
                    <label class="field">Category
                        <select name="category" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Amount (₦)
                        <input type="number" name="amount" value="{{ old('amount') }}" required min="1" step="0.01">
                        @error('amount') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Date
                        <input type="date" name="spent_on" value="{{ old('spent_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                        @error('spent_on') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="field">Notes (optional)
                    <textarea name="description" maxlength="1000" style="height: 80px">{{ old('description') }}</textarea>
                </label>
                <label class="field">Receipt (photo or PDF, up to 5 MB)
                    <input type="file" name="receipt" accept="image/*,.pdf">
                    @error('receipt') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div><button type="submit" class="btn btn--navy">Log expense</button></div>
            </form>
        </section>

        <section class="box side-col">
            <div class="box__head" style="padding: 0">
                <h2>Budget · {{ $month->format('M Y') }}</h2>
                <form method="GET" action="{{ route('admin.expenses.index') }}" style="display: flex; gap: 6px">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="sr-only" for="budget-month">Month</label>
                    <input id="budget-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="select-sm">
                    <button type="submit" class="btn btn--ghost btn--xs">Show</button>
                </form>
            </div>
            <form method="POST" action="{{ route('admin.budgets.update', ['month' => $month->format('Y-m')]) }}" class="form">
                @csrf
                @method('PUT')
                @foreach ($lines as $i => $line)
                    @php($pct = $line['budget'] ? min(100, (int) round($line['spent'] * 100 / $line['budget'])) : 0)
                    <div class="budget-line">
                        <label class="budget-line__head" for="budget-{{ $i }}">
                            <span>{{ $line['category'] }}</span>
                            <span @class(['budget-line__over' => $line['budget'] && $line['spent'] > $line['budget']])>{{ $naira($line['spent']) }}@if ($line['budget']) of {{ $naira($line['budget']) }}@endif</span>
                        </label>
                        @if ($line['budget'])
                            <div class="meter" role="img" aria-label="{{ $pct }}% of budget used"><span style="width: {{ $pct }}%"></span></div>
                        @endif
                        <input id="budget-{{ $i }}" type="number" name="budgets[{{ $i }}]" value="{{ $line['budget'] ? $line['budget'] / 100 : '' }}" min="0" step="1" placeholder="No budget" class="select-sm" style="width: 100%">
                    </div>
                @endforeach
                <button type="submit" class="btn btn--navy btn--xs">Save budgets</button>
            </form>
        </section>
    </div>
@endsection
