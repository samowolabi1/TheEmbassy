@extends('layouts.admin', ['title' => 'Token treasury', 'active' => 'tokens'])

@php($n = fn (int $x) => number_format($x))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Token treasury</h1>
            <span class="page-head__sub">
                Tokens are the app's own currency: 1 token = {{ $rate }}. The pastor in charge creates them into the treasury;
                members earn them from it, spend them, and the subscriptions and tickets they pay for send them back.
                @unless ($super) Only the pastor in charge can create, give or take tokens and change the amounts. @endunless
            </span>
        </div>
    </header>

    @foreach (['mint', 'burn', 'award', 'settings'] as $bag)
        @if ($errors->getBag($bag)->any())
            <p class="flash flash--error" role="alert">{{ $errors->getBag($bag)->first() }}</p>
        @endif
    @endforeach
    @if (! $books['ok'])
        <p class="flash flash--error" role="alert">The token books don't add up: {{ implode(' ', $books['problems']) }}</p>
    @endif
    @if ($treasuryShort && $books['treasury'] < 100)
        <p class="flash flash--error" role="status">The treasury ran short, so some rewards weren't paid. {{ $super ? 'Create more tokens below.' : 'Ask the pastor in charge to create more.' }}</p>
    @endif

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">In the treasury</span>
            <span class="stat-tile__num">🪙 {{ $n($books['treasury']) }}</span>
            <span class="stat-tile__foot">Ready to pay out as rewards</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">With members and sellers</span>
            <span class="stat-tile__num">{{ $n($books['members']) }}</span>
            <span class="stat-tile__foot">≈ {{ \App\Services\Tokens\TokenValue::nairaLabel($books['members']) }} · {{ $n($books['escrow']) }} held for {{ $orders }} {{ \Illuminate\Support\Str::plural('order', $orders) }}</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Total in existence</span>
            <span class="stat-tile__num">{{ $n($books['supply']) }}</span>
            <span class="stat-tile__foot">{{ $n($books['minted']) }} created, {{ $n($books['burned']) }} destroyed · {{ $books['ok'] ? '✓ books balance' : '✗ check needed' }}</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">This month</span>
            <span class="stat-tile__num">{{ $n($flows['out']) }} out</span>
            <span class="stat-tile__foot">{{ $n($flows['back']) }} back to the treasury · {{ $n($flows['market']) }} spent with sellers · {{ $n($flows['sent']) }} sent between members</span>
        </div>
    </div>

    @if ($super)
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
                        </label>
                        <label class="field">Tokens <input type="number" name="amount" value="{{ old('amount') }}" required min="1" step="1"></label>
                    </div>
                    <fieldset class="choice-group">
                        <legend class="sr-only">Give or take</legend>
                        <div class="choices">
                            <label class="choice"><input type="radio" name="direction" value="give" checked><span>Give from the treasury</span></label>
                            <label class="choice"><input type="radio" name="direction" value="take"><span>Take back into the treasury</span></label>
                        </div>
                    </fieldset>
                    <label class="field">Reason (they see this)
                        <input type="text" name="reason" value="{{ old('reason') }}" required maxlength="150" placeholder="e.g. Volunteered at the outreach">
                    </label>
                    <div><button type="submit" class="btn btn--navy">Save</button></div>
                </form>
            </section>

            <section class="box form side-col">
                <h2>Create or destroy tokens</h2>
                <span class="box__note">New tokens go into the treasury, ready to be earned. Destroying takes unused tokens out of the treasury for good.</span>
                <form method="POST" action="{{ route('admin.tokens.mint') }}" class="form" data-confirm="Create these tokens in the treasury?">
                    @csrf
                    <div class="form-row">
                        <label class="field">Create <input type="number" name="amount" required min="1" step="1" placeholder="e.g. 100000"></label>
                        <label class="field">Note <input type="text" name="note" required maxlength="150" placeholder="e.g. Rewards for December"></label>
                    </div>
                    <div><button type="submit" class="btn btn--gold btn--xs">Create tokens</button></div>
                </form>
                <details class="list-item--edit">
                    <summary class="box__link" style="cursor: pointer">Destroy tokens</summary>
                    <form method="POST" action="{{ route('admin.tokens.burn') }}" class="form" style="padding-top: 8px" data-confirm="Destroy these tokens for good?">
                        @csrf
                        <div class="form-row">
                            <label class="field">Destroy <input type="number" name="amount" required min="1" max="{{ $books['treasury'] }}" step="1"></label>
                            <label class="field">Note <input type="text" name="note" required maxlength="150"></label>
                        </div>
                        <div><button type="submit" class="btn btn--danger btn--xs">Destroy tokens</button></div>
                    </form>
                </details>
            </section>
        </div>

        <form method="POST" action="{{ route('admin.tokens.settings') }}" class="box form" id="settings">
            @csrf
            @method('PUT')
            <h2>What earns tokens, and what a token is worth</h2>
            <div class="form-row">
                <label class="field">Value of 1 token (₦)
                    <input type="number" name="naira_per_token" value="{{ old('naira_per_token', config('tokens.naira_per_token')) }}" required min="0.01" step="0.01">
                    <span class="field__hint">Prices subscriptions and tickets in tokens. Changing it doesn't change anyone's balance.</span>
                </label>
                <label class="field">Most a member can send in a day
                    <input type="number" name="daily_limit" value="{{ old('daily_limit', config('tokens.transfers.daily_limit')) }}" required min="1" step="1">
                </label>
            </div>
            <label class="login__remember"><input type="checkbox" name="enabled" value="1" @checked(config('tokens.enabled'))>Members earn tokens for activities</label>
            <label class="login__remember"><input type="checkbox" name="transfers_enabled" value="1" @checked(config('tokens.transfers.enabled'))>Members can send tokens to each other</label>
            <div class="grid-auto" style="--min: 220px; --gap: 12px 16px">
                @foreach ($rules as $key => $rule)
                    <label class="field">{{ $rule['label'] }}
                        <input type="number" name="rules[{{ $key }}]" value="{{ old('rules.'.$key, $rule['amount']) }}" required min="0" step="1">
                        <span class="field__hint">{{ $rule['text'] }}. 0 turns it off.</span>
                    </label>
                @endforeach
            </div>
            <div><button type="submit" class="btn btn--navy">Save</button></div>
        </form>
    @else
        <section class="box box--flush">
            <div class="box__head"><h2>What earns tokens</h2><span class="box__note">Set by the pastor in charge</span></div>
            <div class="list-grid">
                @foreach ($rules as $rule)
                    <div class="list-item"><span class="list-item__main"><span class="list-item__title">{{ $rule['label'] }}</span></span><strong>{{ $rule['amount'] ? '+'.$rule['amount'] : 'Off' }}</strong></div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head">
                <h2>{{ $member ? 'Tokens: '.$member->name : 'Every movement' }}</h2>
                <form method="GET" style="display: flex; gap: 8px; flex-wrap: wrap">
                    @if ($member) <input type="hidden" name="member" value="{{ $member->id }}"> @endif
                    <label class="sr-only" for="kind">Kind</label>
                    <select id="kind" name="kind" class="select-sm" onchange="this.form.submit()">
                        <option value="">All kinds</option>
                        @foreach (\App\Models\TokenTransfer::KINDS as $key => $label)
                            <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($member) <a href="{{ route('admin.tokens.index') }}" class="btn btn--ghost btn--xs">Everyone</a> @endif
                </form>
            </div>
            @if ($member && $account)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">Balance 🪙 {{ $n($account->balance) }} @if ($account->isFrozen()) <span class="pill pill--warn">On hold</span> @endif</span>
                        @if ($account->frozen_reason) <span class="list-item__meta">{{ $account->frozen_reason }}</span> @endif
                    </span>
                    @if ($super)
                        <form method="POST" action="{{ route('admin.tokens.freeze', $member) }}" style="display: flex; gap: 6px; flex-wrap: wrap" data-confirm="{{ $account->isFrozen() ? 'Release this wallet?' : 'Put this wallet on hold? They keep their tokens but can\'t spend or send them.' }}">
                            @csrf
                            @unless ($account->isFrozen()) <input type="text" name="reason" maxlength="200" placeholder="Reason" class="select-sm"> @endunless
                            <button type="submit" class="btn btn--ghost btn--xs">{{ $account->isFrozen() ? 'Release wallet' : 'Put on hold' }}</button>
                        </form>
                    @endif
                </div>
            @endif
            @forelse ($recent as $t)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $t->from?->label() ?? 'Created' }} → {{ $t->to?->label() ?? 'Destroyed' }}</span>
                        <span class="list-item__meta">{{ $t->kindLabel() }} · {{ $t->description }} · {{ $t->created_at->format('j M, g:i A') }}@if ($t->creator) · by {{ $t->creator->name }}@endif</span>
                    </span>
                    <strong>{{ $n($t->amount) }}</strong>
                </div>
            @empty
                <p class="empty-state">No token movements yet.</p>
            @endforelse
            @include('admin.partials.pager', ['paginator' => $recent])
        </section>

        <div class="side-col stack" style="gap: 16px">
            <section class="box box--flush">
                <div class="box__head"><h2>Biggest wallets</h2></div>
                @forelse ($top as $acc)
                    <a href="{{ $acc->user?->member ? route('admin.tokens.index', ['member' => $acc->user->member->id]) : '#' }}" class="list-item" style="color: var(--ink)">
                        <span class="list-item__main"><span class="list-item__title">{{ $loop->iteration }}. {{ $acc->label() }}</span></span>
                        <strong>{{ $n($acc->balance) }}</strong>
                    </a>
                @empty
                    <p class="empty-state">No tokens with members yet.</p>
                @endforelse
            </section>
            @if ($frozen->isNotEmpty())
                <section class="box box--flush">
                    <div class="box__head"><h2>Wallets on hold</h2></div>
                    @foreach ($frozen as $acc)
                        <a href="{{ route('admin.tokens.index', ['member' => $acc->user?->member?->id]) }}" class="list-item" style="color: var(--ink)">
                            <span class="list-item__main"><span class="list-item__title">{{ $acc->label() }}</span><span class="list-item__meta">{{ $acc->frozen_reason ?: 'No reason given' }}</span></span>
                        </a>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
@endsection
