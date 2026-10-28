@extends('layouts.admin', ['title' => 'Giving & tokens', 'active' => 'finance'])

@php($naira = fn (int $kobo) => '₦'.number_format($kobo / 100))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Giving &amp; tokens</h1>
            <span class="page-head__sub">{{ $label }} · online gifts settle to the church's Paystack account</span>
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
            <a href="{{ route('admin.finance.export', ['period' => $period]) }}" class="btn btn--outline">Export report</a>
            <a href="#record-gift" class="btn btn--gold">Record cash offering</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Total in</span>
            <span class="stat-tile__num">{{ $naira($stats['total']) }}</span>
            <span class="stat-tile__foot">Giving {{ $naira($stats['giving']) }} + tokens {{ $naira($stats['tokens']) }}</span>
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
        <div class="stat-tile">
            <span class="stat-tile__label">Expenses</span>
            <span class="stat-tile__num">{{ $naira($stats['expenses']) }}</span>
            <span class="stat-tile__foot">{{ $stats['pendingExpenses'] }} awaiting approval</span>
        </div>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Funds</h2><span class="box__note">In {{ strtolower($periods[$period]) }} · balance is everything received since the fund was set up</span></div>
        <div class="fund-tiles">
            @foreach ($funds as $fund)
                <details class="fund-tile">
                    <summary>
                        <span class="fund-tile__name">{{ $fund->name }}@unless ($fund->is_active) <span class="pill pill--draft">Hidden</span>@endunless</span>
                        <span class="fund-tile__num">{{ $naira($fund->period_total) }}</span>
                        <span class="fund-tile__foot">
                            @if ($fund->target)
                                {{ min(100, (int) floor($fund->balance * 100 / $fund->target)) }}% of {{ $naira($fund->target) }}
                            @else
                                Balance {{ $naira($fund->balance) }}
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
                    <button type="submit" class="btn btn--navy btn--xs">Add fund</button>
                </form>
            </details>
        </div>
    </section>

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

        <section class="box side-col" id="record-tokens">
            <h2>Record tokens paid</h2>
            <form method="POST" action="{{ route('admin.tokens.store') }}" class="form">
                @csrf
                <label class="field">Member
                    <select name="member_id" required>
                        <option value="">Choose…</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected((int) old('member_id') === $m->id)>{{ $m->name }}{{ $m->phone ? ' · '.$m->phone : '' }}</option>
                        @endforeach
                    </select>
                    @error('member_id', 'tokens') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div class="form-row">
                    <label class="field">Months <input type="number" name="months" value="{{ old('months', 1) }}" min="1" max="36" required></label>
                    <label class="field">How
                        <select name="method">
                            @foreach ($methods as $key => $text)
                                <option value="{{ $key }}">{{ $text }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <span class="field__hint">Covers their next unpaid months, at their plan's price.</span>
                <button type="submit" class="btn btn--navy">Record</button>
            </form>
        </section>
    </div>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Token plans</h2></div>
            @foreach ($plans as $plan)
                <details class="list-item list-item--edit">
                    <summary>
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $plan->name }}@if ($plan->is_default) <span class="pill pill--info">Default</span>@endif</span>
                            <span class="list-item__meta">{{ $plan->description ? $plan->description.' · ' : '' }}{{ number_format($plan->members_count) }} {{ \Illuminate\Support\Str::plural('member', $plan->members_count) }}</span>
                        </span>
                        <strong style="color: var(--navy)">{{ $plan->amountLabel() }}</strong>
                    </summary>
                    <form method="POST" action="{{ route('admin.token-plans.update', $plan) }}" class="form">
                        @csrf
                        @method('PUT')
                        <div class="form-row">
                            <label class="field">Name <input type="text" name="name" value="{{ $plan->name }}" required maxlength="60"></label>
                            <label class="field">₦ a month <input type="number" name="amount" value="{{ $plan->amount / 100 }}" required min="0" step="1"></label>
                        </div>
                        <label class="field">Description <input type="text" name="description" value="{{ $plan->description }}" maxlength="255"></label>
                        <label class="login__remember"><input type="checkbox" name="is_default" value="1" @checked($plan->is_default)>New members start on this plan</label>
                        <div><button type="submit" class="btn btn--navy btn--xs">Save</button></div>
                    </form>
                </details>
            @endforeach
            <details class="list-item list-item--edit">
                <summary><span class="list-item__main"><span class="list-item__title">+ Add plan</span></span></summary>
                <form method="POST" action="{{ route('admin.token-plans.store') }}" class="form">
                    @csrf
                    <div class="form-row">
                        <label class="field">Name <input type="text" name="name" required maxlength="60"></label>
                        <label class="field">₦ a month <input type="number" name="amount" required min="0" step="1"></label>
                    </div>
                    @error('name', 'plan') <span class="field-error">{{ $message }}</span> @enderror
                    <div><button type="submit" class="btn btn--navy btn--xs">Add plan</button></div>
                </form>
            </details>
        </section>

        <section class="box box--flush side-col">
            <div class="box__head">
                <h2>In arrears</h2>
                <span class="box__note">{{ number_format($arrearsCount) }} {{ \Illuminate\Support\Str::plural('member', $arrearsCount) }} · {{ $naira($arrearsTotal) }} outstanding</span>
            </div>
            @forelse ($arrears as $m)
                <div class="list-item">
                    <span class="list-item__main">
                        <a href="{{ route('admin.members.show', $m) }}" class="list-item__title">{{ $m->name }}</a>
                        <span class="list-item__meta">{{ $m->tokenMonthsOwed() }} {{ \Illuminate\Support\Str::plural('month', $m->tokenMonthsOwed()) }}{{ $m->unit ? ' · '.$m->unit->name : '' }} · {{ $naira($m->tokenAmountOwed()) }}</span>
                    </span>
                    <span class="list-item__actions">
                        @if ($m->token_reminded_at && $m->token_reminded_at->gt(now()->subWeek()))
                            <span class="pill pill--ok">Reminded</span>
                        @elseif ($m->email || $m->user)
                            <form method="POST" action="{{ route('admin.tokens.remind') }}">
                                @csrf
                                <input type="hidden" name="member" value="{{ $m->id }}">
                                <button type="submit" class="btn btn--ghost btn--xs">Email<span class="sr-only"> {{ $m->name }}</span></button>
                            </form>
                        @endif
                        @if ($m->whatsappUrl())
                            <a href="{{ $m->whatsappUrl() }}?text={{ rawurlencode('Hi '.\Illuminate\Support\Str::before($m->name, ' ').', a gentle reminder from '.config('church.short_name').' that your tokens are '.$m->tokenMonthsOwed().' month(s) behind. Thank you!') }}" class="btn btn--ghost btn--xs" target="_blank" rel="noopener">WhatsApp</a>
                        @endif
                    </span>
                </div>
            @empty
                <p class="empty-state">Everyone is up to date.</p>
            @endforelse
            @if ($arrearsCount)
                <form method="POST" action="{{ route('admin.tokens.remind') }}" style="padding: 16px 20px" data-confirm="Email a reminder to everyone behind who hasn't had one this week?">
                    @csrf
                    <button type="submit" class="btn btn--navy btn--xs">Remind all by email</button>
                </form>
            @endif
        </section>
    </div>
@endsection
