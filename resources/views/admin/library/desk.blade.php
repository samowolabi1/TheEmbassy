@extends('layouts.admin', ['title' => 'Library', 'active' => 'library'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Library</h1>
            <span class="page-head__sub">{{ number_format($stats['books']) }} books · {{ number_format($stats['copies']) }} copies. Members reserve online; hand the book over here when they collect it, and mark it returned when it's back. Loans last {{ \App\Services\Library\Lending::loanDays() }} days.</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.library.books') }}" class="btn btn--ghost">Books</a>
            <a href="{{ route('admin.library.books.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a book</a>
        </div>
    </header>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Loans">
            @foreach (\App\Http\Controllers\Admin\LibraryController::TABS as $key => $label)
                <a href="{{ route('admin.library.desk', ['tab' => $key, 'q' => $q ?: null]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $label }}@isset($counts[$key]) <span>{{ $counts[$key] }}</span>@endisset</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.library.desk') }}" class="search-inline">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="sr-only" for="loan-q">Find a member or book</label>
            <input id="loan-q" type="search" name="q" value="{{ $q }}" placeholder="Member, phone or book" class="select-sm">
        </form>
    </div>

    <section class="box box--flush">
        @forelse ($loans as $loan)
            <div class="list-item">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $loan->book->title }}</span>
                    <span class="list-item__meta">
                        {{ $loan->member->name }}@if ($loan->member->phone) · <a href="tel:{{ preg_replace('/\s+/', '', $loan->member->phone) }}">{{ $loan->member->phone }}</a>@endif
                        @if ($loan->book->shelf) · {{ $loan->book->shelf }}@endif
                        @switch($loan->status)
                            @case('requested') · reserved {{ $loan->updated_at->diffForHumans() }} @break
                            @case('waiting') · waiting since {{ $loan->created_at->format('j M') }} (number {{ $loan->queuePosition() }}) @break
                            @case('on_loan') · lent {{ $loan->lent_at->format('j M') }}, due {{ $loan->due_on->format('D j M') }}{{ $loan->renewals ? ' (renewed)' : '' }} @break
                            @case('returned') · returned {{ $loan->returned_at->format('j M Y') }} @break
                            @case('cancelled') · cancelled{{ $loan->note ? ': '.$loan->note : '' }} @break
                        @endswitch
                    </span>
                </span>
                @if ($loan->isOverdue())
                    <span class="pill pill--warn">{{ $loan->due_on->diffInDays(today()) }} days late</span>
                @endif
                <span class="list-item__actions">
                    @if ($loan->status === 'requested')
                        <form method="POST" action="{{ route('admin.library.lend', $loan) }}">
                            @csrf
                            <button type="submit" class="btn btn--gold btn--xs">Hand over</button>
                        </form>
                    @endif
                    @if ($loan->status === 'on_loan')
                        <form method="POST" action="{{ route('admin.library.receive', $loan) }}">
                            @csrf
                            <button type="submit" class="btn btn--navy btn--xs">Returned</button>
                        </form>
                        <form method="POST" action="{{ route('admin.library.extend', $loan) }}" style="display: flex; gap: 6px">
                            @csrf
                            <label class="sr-only" for="extend-{{ $loan->id }}">More time</label>
                            <select id="extend-{{ $loan->id }}" name="days" class="select-sm">
                                <option value="7">+1 week</option>
                                <option value="14">+2 weeks</option>
                                <option value="28">+4 weeks</option>
                            </select>
                            <button type="submit" class="btn btn--ghost btn--xs">Give more time</button>
                        </form>
                    @endif
                    @if (in_array($loan->status, ['requested', 'waiting'], true))
                        <form method="POST" action="{{ route('admin.library.cancel', $loan) }}" data-confirm="Cancel this reservation?">
                            @csrf
                            <button type="submit" class="btn btn--danger btn--xs">Cancel</button>
                        </form>
                    @endif
                </span>
            </div>
        @empty
            <p class="empty-state">{{ match ($tab) { 'collect' => 'Nothing waiting to be handed over.', 'overdue' => 'No overdue books.', default => 'Nothing here.' } }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $loans])
    </section>
@endsection
