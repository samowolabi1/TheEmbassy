@extends('layouts.admin', ['title' => TokenName::titleOne().' treasury', 'active' => 'tokens'])

@php($n = fn (int $x) => number_format($x))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>{{ TokenName::titleOne() }} treasury</h1>
            <span class="page-head__sub">
                {{ TokenName::title() }} are the app's own currency: 1 {{ TokenName::one() }} = {{ $rate }}. The pastor in charge creates them into the treasury;
                members earn them from it, spend them, and the subscriptions and tickets they pay for send them back.
                @unless ($super) Only the pastor in charge can create, give or take {{ TokenName::many() }} and change the amounts. @endunless
            </span>
        </div>
    </header>

    @foreach (['mint', 'burn', 'award', 'settings'] as $bag)
        @if ($errors->getBag($bag)->any())
            <p class="flash flash--error" role="alert">{{ $errors->getBag($bag)->first() }}</p>
        @endif
    @endforeach
    @if (! $books['ok'])
        <p class="flash flash--error" role="alert">The {{ TokenName::one() }} books don't add up: {{ implode(' ', $books['problems']) }}</p>
    @endif
    @if ($treasuryShort && $books['treasury'] < 100)
        <p class="flash flash--error" role="status">The treasury ran short, so some rewards weren't paid. {{ $super ? 'Create more '.TokenName::many().' below.' : 'Ask the pastor in charge to create more.' }}</p>
    @endif

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">In the treasury</span>
            <span class="stat-tile__num">{{ TokenName::icon() }} {{ $n($books['treasury']) }}</span>
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
                <h2>Give or take {{ TokenName::many() }} <x-tip text="Giving moves them from the treasury to the member. Taking back returns them to the treasury, for example when something was given by mistake." /></h2>
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
                        <label class="field">{{ TokenName::title() }} <input type="number" data-money="{{ TokenName::icon() }}" name="amount" value="{{ old('amount') }}" required min="1" step="1"></label>
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
                <h2>Create or destroy {{ TokenName::many() }} <x-tip text="Rewards are paid from the treasury. Create more when it runs low. Destroying removes unused ones from the treasury for good; members' balances are never touched." /></h2>
                <span class="box__note">New {{ TokenName::many() }} go into the treasury, ready to be earned. Destroying takes unused {{ TokenName::many() }} out of the treasury for good.</span>
                <form method="POST" action="{{ route('admin.tokens.mint') }}" class="form" data-confirm="Create these {{ TokenName::many() }} in the treasury?">
                    @csrf
                    <div class="form-row">
                        <label class="field">Create <input type="number" data-money="{{ TokenName::icon() }}" name="amount" required min="1" step="1" placeholder="e.g. 100000"></label>
                        <label class="field">Note <input type="text" name="note" required maxlength="150" placeholder="e.g. Rewards for December"></label>
                    </div>
                    <div><button type="submit" class="btn btn--gold btn--xs">Create {{ TokenName::many() }}</button></div>
                </form>
                <details class="list-item--edit">
                    <summary class="box__link" style="cursor: pointer">Destroy {{ TokenName::many() }}</summary>
                    <form method="POST" action="{{ route('admin.tokens.burn') }}" class="form" style="padding-top: 8px" data-confirm="Destroy these {{ TokenName::many() }} for good?">
                        @csrf
                        <div class="form-row">
                            <label class="field">Destroy <input type="number" data-money="{{ TokenName::icon() }}" name="amount" required min="1" max="{{ $books['treasury'] }}" step="1"></label>
                            <label class="field">Note <input type="text" name="note" required maxlength="150"></label>
                        </div>
                        <div><button type="submit" class="btn btn--danger btn--xs">Destroy {{ TokenName::many() }}</button></div>
                    </form>
                </details>
            </section>
        </div>

        <form method="POST" action="{{ route('admin.tokens.settings') }}" class="box form" id="settings">
            @csrf
            @method('PUT')
            <h2>What earns {{ TokenName::many() }}, and what a {{ TokenName::one() }} is worth</h2>
            <div class="form-row">
                <label class="field">Name for one
                    <input type="text" name="name_one" value="{{ old('name_one', TokenName::one()) }}" required maxlength="20">
                    <span class="field__hint">e.g. Crown: "You earned 1 Crown".</span>
                </label>
                <label class="field">Name for more than one
                    <input type="text" name="name_many" value="{{ old('name_many', TokenName::many()) }}" required maxlength="20">
                    <span class="field__hint">e.g. Crowns: "My Crowns", "Crown treasury". Changes it everywhere.</span>
                </label>
                <label class="field">Icon
                    <input type="text" name="name_icon" value="{{ old('name_icon', TokenName::icon()) }}" required maxlength="8">
                    <span class="field__hint">One emoji, e.g. 👑, ⭐ or 💎.</span>
                </label>
            </div>
            <div class="form-row">
                <label class="field">Value of 1 {{ TokenName::one() }}
                    <input type="number" data-money name="naira_per_token" value="{{ old('naira_per_token', config('tokens.naira_per_token')) }}" required min="0.01" step="0.01">
                    <span class="field__hint">Prices subscriptions and tickets in {{ TokenName::many() }}. Changing it doesn't change anyone's balance.</span>
                </label>
                <label class="field">Most a member can send in a day
                    <input type="number" data-money="{{ TokenName::icon() }}" name="daily_limit" value="{{ old('daily_limit', config('tokens.transfers.daily_limit')) }}" required min="1" step="1">
                </label>
            </div>
            <label class="login__remember"><input type="checkbox" name="enabled" value="1" @checked(config('tokens.enabled'))>Members earn {{ TokenName::many() }} for activities</label>
            <label class="login__remember"><input type="checkbox" name="transfers_enabled" value="1" @checked(config('tokens.transfers.enabled'))>Members can send {{ TokenName::many() }} to each other</label>
            <div class="grid-auto" style="--min: 220px; --gap: 12px 16px">
                @foreach ($rules as $key => $rule)
                    <label class="field">{{ $rule['label'] }}
                        <input type="number" data-money="{{ TokenName::icon() }}" name="rules[{{ $key }}]" value="{{ old('rules.'.$key, $rule['amount']) }}" required min="0" step="1">
                        <span class="field__hint">{{ $rule['text'] }}. 0 turns it off.</span>
                    </label>
                @endforeach
            </div>
            <div><button type="submit" class="btn btn--navy">Save</button></div>
        </form>
    @else
        <section class="box box--flush">
            <div class="box__head"><h2>What earns {{ TokenName::many() }}</h2><span class="box__note">Set by the pastor in charge</span></div>
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
                        <span class="list-item__title">Balance {{ TokenName::icon() }} {{ $n($account->balance) }} @if ($account->isFrozen()) <span class="pill pill--warn">On hold</span> @endif</span>
                        @if ($account->frozen_reason) <span class="list-item__meta">{{ $account->frozen_reason }}</span> @endif
                    </span>
                    @if ($super)
                        <form method="POST" action="{{ route('admin.tokens.freeze', $member) }}" style="display: flex; gap: 6px; flex-wrap: wrap" data-confirm="{{ $account->isFrozen() ? 'Release this wallet?' : 'Put this wallet on hold? They keep their '.TokenName::many().' but can\'t spend or send them.' }}">
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
                <p class="empty-state">No {{ TokenName::one() }} movements yet.</p>
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
                    <p class="empty-state">No {{ TokenName::many() }} with members yet.</p>
                @endforelse
            </section>
            @if ($frozen->isNotEmpty())
                <section class="box box--flush">
                    <div class="box__head"><h2>Wallets on hold <x-tip text="Members whose wallet is on hold keep their balance but can't spend or send until you release it." /></h2></div>
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
