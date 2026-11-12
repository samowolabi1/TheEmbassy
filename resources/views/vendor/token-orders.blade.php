@extends('layouts.vendor', ['title' => 'Token orders', 'active' => 'token-orders'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Token orders</h1>
            <span class="page-head__sub">Members earn tokens for taking part in church life and can spend them on listings you set a token price for. Their tokens are held when they order and become yours when you hand it over.</span>
        </div>
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Your tokens</span>
            <span class="stat-tile__num">🪙 {{ number_format($balance) }}</span>
            <span class="stat-tile__foot">Use them for your <a href="{{ route('vendor.subscription.index') }}" style="color: inherit; text-decoration: underline">subscription</a> (1 token = ₦{{ (int) config('tokens.naira_per_token', 10) }})</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Waiting to hand over</span>
            <span class="stat-tile__num">{{ $pending }}</span>
            <span class="stat-tile__foot">Cancelled after {{ \App\Models\TokenOrder::EXPIRES_AFTER_DAYS }} days</span>
        </div>
    </div>

    <section class="box form">
        <h2>Hand over an order</h2>
        <form method="POST" action="{{ route('vendor.token-orders.complete') }}" class="form-row" style="align-items: end">
            @csrf
            <label class="field">Code the buyer shows
                <input type="text" name="code" required maxlength="12" placeholder="TK-XXXXXX" autocapitalize="characters" autocomplete="off">
            </label>
            <div class="field"><button type="submit" class="btn btn--navy">Mark handed over</button></div>
        </form>
    </section>

    <nav class="tabs" aria-label="Orders">
        <a href="{{ route('vendor.token-orders.index') }}" class="tab" @if ($show === 'pending') aria-current="page" @endif>To hand over <span>{{ $pending }}</span></a>
        <a href="{{ route('vendor.token-orders.index', ['show' => 'done']) }}" class="tab" @if ($show === 'done') aria-current="page" @endif>Done</a>
    </nav>

    <section class="box box--flush">
        @forelse ($orders as $order)
            <div class="list-item">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $order->product_name }} <span class="box__note">{{ $order->code }}</span></span>
                    <span class="list-item__meta">
                        {{ $order->buyer?->communityName() }}@if ($order->buyer?->member?->phone) · {{ $order->buyer->member->phone }}@endif
                        · 🪙 {{ number_format($order->tokens) }} · {{ $order->created_at->format('j M, g:i A') }}
                        @if ($order->note) · "{{ $order->note }}" @endif
                    </span>
                </span>
                @if ($order->status === 'pending')
                    <span class="list-item__actions">
                        <form method="POST" action="{{ route('vendor.token-orders.complete') }}">
                            @csrf
                            <input type="hidden" name="code" value="{{ $order->code }}">
                            <button type="submit" class="btn btn--navy btn--xs">Handed over</button>
                        </form>
                        <form method="POST" action="{{ route('vendor.token-orders.decline', $order) }}" data-confirm="Decline this order? The buyer gets their tokens back.">
                            @csrf
                            <button type="submit" class="btn btn--danger btn--xs">Decline</button>
                        </form>
                    </span>
                @else
                    <span @class(['pill', 'pill--live' => $order->status === 'completed', 'pill--draft' => $order->status !== 'completed'])>{{ $order->status === 'completed' ? 'Handed over' : 'Cancelled' }}</span>
                @endif
            </div>
        @empty
            <p class="empty-state">{{ $show === 'pending' ? 'No orders waiting.' : 'Nothing here yet.' }} Set a token price on a listing to accept tokens.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $orders])
    </section>
@endsection
