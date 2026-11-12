@extends('layouts.admin', ['title' => 'Tokens', 'active' => 'tokens'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Tokens</h1>
            <span class="page-head__sub">Free rewards members earn for taking part, and spend in the {{ config('church.brand.market_name') }} or on shop subscriptions. Nobody pays money for tokens.</span>
        </div>
        @if (auth()->user()->canManage('settings'))
            <div class="actions"><a href="{{ route('admin.settings.edit') }}#tokens" class="btn btn--outline">Change the amounts</a></div>
        @endif
    </header>

    @unless ($enabled)
        <p class="flash flash--error" role="status">Earning tokens is switched off in Settings &amp; roles › Tokens.</p>
    @endunless

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Held by everyone</span>
            <span class="stat-tile__num">🪙 {{ number_format($stats['held']) }}</span>
            <span class="stat-tile__foot">≈ ₦{{ number_format($stats['held'] * (int) config('tokens.naira_per_token', 10)) }} towards subscriptions</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Earned ({{ now()->format('M') }})</span>
            <span class="stat-tile__num">{{ number_format($stats['earned']) }}</span>
            <span class="stat-tile__foot">Attendance, quizzes, community and more</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Spent ({{ now()->format('M') }})</span>
            <span class="stat-tile__num">{{ number_format($stats['spent']) }}</span>
            <span class="stat-tile__foot">{{ $stats['orders'] }} market {{ \Illuminate\Support\Str::plural('order', $stats['orders']) }} waiting for the seller</span>
        </div>
    </div>

    <div class="row">
        <section class="box form grow" id="award">
            <h2>Give or take tokens</h2>
            <form method="POST" action="{{ route('admin.tokens.award') }}" class="form">
                @csrf
                <div class="form-row">
                    <label class="field">Member
                        <select name="member_id" required>
                            <option value="">Choose…</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}" @selected((int) old('member_id', $member?->id) === $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                        @error('member_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">Tokens
                        <input type="number" name="amount" value="{{ old('amount') }}" required step="1" placeholder="e.g. 50, or -20 to take away">
                        @error('amount') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="field">Reason (they see this)
                    <input type="text" name="reason" value="{{ old('reason') }}" required maxlength="150" placeholder="e.g. Volunteered at the outreach">
                    @error('reason') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <div><button type="submit" class="btn btn--navy">Save</button></div>
            </form>
        </section>

        <section class="box box--flush side-col">
            <div class="box__head"><h2>Ways to earn</h2></div>
            @foreach ($rules as $rule)
                <div class="list-item">
                    <span class="list-item__main"><span class="list-item__title">{{ $rule['label'] }}</span></span>
                    <strong>{{ $rule['amount'] ? '+'.$rule['amount'] : 'Off' }}</strong>
                </div>
            @endforeach
        </section>
    </div>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head">
                <h2>{{ $member ? 'Tokens: '.$member->name : 'Recent activity' }}</h2>
                @if ($member) <a href="{{ route('admin.tokens.index') }}" class="box__link">Everyone</a> @endif
            </div>
            @forelse ($recent as $t)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $t->user?->communityName() }}</span>
                        <span class="list-item__meta">{{ $t->description }} · {{ $t->created_at->format('j M, g:i A') }}@if ($t->creator) · by {{ $t->creator->name }}@endif</span>
                    </span>
                    <strong style="color: {{ $t->amount > 0 ? '#1d7a46' : 'var(--slate)' }}">{{ $t->amount > 0 ? '+' : '−' }}{{ number_format(abs($t->amount)) }}</strong>
                </div>
            @empty
                <p class="empty-state">No tokens yet.</p>
            @endforelse
            @include('admin.partials.pager', ['paginator' => $recent])
        </section>

        <section class="box box--flush side-col">
            <div class="box__head"><h2>Top earners ({{ now()->format('M') }})</h2></div>
            @forelse ($top as $row)
                <div class="list-item">
                    <span class="list-item__main"><span class="list-item__title">{{ $loop->iteration }}. {{ $row->user?->communityName() }}</span></span>
                    <strong>{{ number_format($row->total) }}</strong>
                </div>
            @empty
                <p class="empty-state">Nobody yet this month.</p>
            @endforelse
        </section>
    </div>
@endsection
