@extends('layouts.admin', ['title' => 'Giving', 'active' => 'finance'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Giving</h1>
            <span class="page-head__sub">{{ $label }} · tithes and offerings come in by bank transfer; donations can also come through Paystack @unless ($seeGiving) · giving records are private to the administrator @endunless</span>
        </div>
        <div class="actions">
            <form method="GET" action="{{ route('admin.finance.index') }}">
                <label class="sr-only" for="period">Period</label>
                <select id="period" name="period" class="select-sm" onchange="this.form.submit()">
                    @foreach ($periods as $key => $text)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $text }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn--ghost btn--xs">Show</button></noscript>
            </form>
            @if ($seeGiving)
                <a href="{{ route('admin.finance.export', ['period' => $period]) }}" class="btn btn--outline">Export report</a>
            @endif
            <a href="#record-gift" class="btn btn--gold">Record cash offering</a>
        </div>
    </header>

    <div class="stats">
        @if ($seeGiving)
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Total in</span>
            <span class="stat-tile__num">{{ $naira($stats['total']) }}</span>
            <span class="stat-tile__foot">Recorded and online giving</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Online</span>
            <span class="stat-tile__num">{{ $naira($stats['online']) }}</span>
            <span class="stat-tile__foot">{{ $stats['total'] ? round($stats['online'] * 100 / $stats['total']) : 0 }}% of total</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Cash, cheque &amp; transfer</span>
            <span class="stat-tile__num">{{ $naira($stats['offline']) }}</span>
            <span class="stat-tile__foot">{{ $stats['services'] }} {{ \Illuminate\Support\Str::plural('day', $stats['services']) }} recorded</span>
        </div>
        @else
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Giving</span>
            <span class="stat-tile__num" style="font-size: 18px">Private</span>
            <span class="stat-tile__foot">Only the administrator sees giving</span>
        </div>
        @endif
        <div class="stat-tile">
            <span class="stat-tile__label">Expenses</span>
            <span class="stat-tile__num">{{ $naira($stats['expenses']) }}</span>
            <span class="stat-tile__foot">{{ $stats['pendingExpenses'] }} awaiting approval</span>
        </div>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Funds</h2><span class="box__note">@if ($seeGiving) In {{ strtolower($periods[$period]) }} · balance is everything received since the fund was set up @else What people can give towards @endif</span></div>
        <div class="fund-tiles">
            @foreach ($funds as $fund)
                <details class="fund-tile">
                    <summary>
                        <span class="fund-tile__name">{{ $fund->name }}@unless ($fund->is_active) <span class="pill pill--draft">Hidden</span>@endunless</span>
                        @if ($seeGiving)
                            <span class="fund-tile__num">{{ $naira($fund->period_total) }}</span>
                        @endif
                        <span class="fund-tile__foot">
                            {{ $fund->online ? 'Bank transfer or Paystack' : 'Bank transfer only' }}
                            @if ($seeGiving)
                                · @if ($fund->target) {{ min(100, (int) floor($fund->balance * 100 / $fund->target)) }}% of {{ $naira($fund->target) }} @else Balance {{ $naira($fund->balance) }} @endif
                            @endif
                        </span>
                    </summary>
                    <form method="POST" action="{{ route('admin.funds.update', $fund) }}" class="form" style="margin-top: 12px">
                        @csrf
                        @method('PUT')
                        <label class="field">Name <input type="text" name="name" value="{{ $fund->name }}" required maxlength="60"></label>
                        <label class="field">Target in ₦ (optional) <input type="number" name="target" value="{{ $fund->target ? $fund->target / 100 : '' }}" min="1" step="1"></label>
                        <label class="field">Description <input type="text" name="description" value="{{ $fund->description }}" maxlength="255"></label>
                        <label class="login__remember"><input type="checkbox" name="is_active" value="1" @checked($fund->is_active)>Offer on the Give page</label>
                        <label class="login__remember"><input type="checkbox" name="online" value="1" @checked($fund->online)>Can also be given through Paystack (donations; leave off for tithes and offerings)</label>
                        <button type="submit" class="btn btn--navy btn--xs">Save</button>
                    </form>
                </details>
            @endforeach
            <details class="fund-tile fund-tile--add">
                <summary><span class="fund-tile__name">+ Add a fund</span><span class="fund-tile__foot">e.g. a special appeal with a target</span></summary>
                <form method="POST" action="{{ route('admin.funds.store') }}" class="form" style="margin-top: 12px">
                    @csrf
                    <label class="field">Name <input type="text" name="name" required maxlength="60"></label>
                    @error('name', 'fund') <span class="field-error">{{ $message }}</span> @enderror
                    <label class="field">Target in ₦ (optional) <input type="number" name="target" min="1" step="1"></label>
                    <label class="login__remember"><input type="checkbox" name="online" value="1" checked>Can also be given through Paystack</label>
                    <button type="submit" class="btn btn--navy btn--xs">Add fund</button>
                </form>
            </details>
        </div>
    </section>

    @include('admin.partials.bank-accounts')

    @if ($seeGiving)
    <section class="box box--flush">
        <div class="box__head">
            <h2>Transactions</h2>
            <nav class="tabs" aria-label="Payment method" style="margin: 0">
                @foreach (['all' => 'All', 'online' => 'Online', 'cash' => 'Cash'] as $key => $text)
                    <a href="{{ route('admin.finance.index', ['period' => $period, 'method' => $key]) }}" class="tab" @if ($method === $key) aria-current="page" @endif>{{ $text }}</a>
                @endforeach
            </nav>
        </div>
        <div class="table-wrap">
            <table class="table" style="min-width: 720px">
                <thead><tr><th>REFERENCE</th><th>GIVER</th><th>FUND</th><th>METHOD</th><th>DATE</th><th class="num">AMOUNT</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($transactions as $gift)
                        <tr>
                            <td style="font-family: ui-monospace, monospace; color: var(--slate)">{{ $gift->code }}</td>
                            <td style="font-weight: 700">{{ $gift->name }}</td>
                            <td>{{ $gift->fund->name }}</td>
                            <td>{{ $gift->methodLabel() }}@if ($gift->counted_by)<span style="color: var(--slate)"> · counted by {{ $gift->counted_by }}</span>@endif</td>
                            <td style="color: var(--slate); white-space: nowrap">{{ $gift->given_on->format('j M') }}</td>
                            <td class="num" style="font-weight: 800; color: var(--navy)">{{ $naira($gift->amount) }}</td>
                            <td class="num">
                                @if ($gift->method !== 'online')
                                    <form method="POST" action="{{ route('admin.gifts.destroy', $gift) }}" data-confirm="Delete entry {{ $gift->code }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--ghost btn--xs">Delete<span class="sr-only"> {{ $gift->code }}</span></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty-state">Nothing recorded for this period.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $transactions])
    </section>
    @endif

    <div class="row">
        <section class="box grow" id="record-gift">
            <h2>Record cash offering</h2>
            <form method="POST" action="{{ route('admin.gifts.store') }}" class="form">
                @csrf
                <div class="form-row">
                    <label class="field">Fund
                        <select name="fund_id" required>
                            @foreach ($funds->where('is_active', true) as $fund)
                                <option value="{{ $fund->id }}" @selected((int) old('fund_id') === $fund->id)>{{ $fund->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Amount (₦)
                        <input type="number" name="amount" value="{{ old('amount') }}" required min="1" step="0.01">
                        @error('amount', 'gift') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="form-row">
                    <label class="field">How
                        <select name="method">
                            @foreach ($methods as $key => $text)
                                <option value="{{ $key }}" @selected(old('method') === $key)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Date
                        <input type="date" name="given_on" value="{{ old('given_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                        @error('given_on', 'gift') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="form-row">
                    <label class="field">From (optional)
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Sunday offering">
                    </label>
                    <label class="field">Counted by
                        <input type="text" name="counted_by" value="{{ old('counted_by') }}" maxlength="255" placeholder="Two names, e.g. Bro. Femi & Sis. Ada">
                    </label>
                </div>
                <div><button type="submit" class="btn btn--navy">Record</button></div>
            </form>
        </section>

    </div>


@endsection
