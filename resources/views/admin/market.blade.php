@extends('layouts.admin', ['title' => config('church.brand.market_name'), 'active' => 'market'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>{{ config('church.brand.market_name') }}</h1>
            <span class="page-head__sub">Member shops, orders and the church's commission</span>
        </div>
        <div class="actions">
            <a href="{{ route('market.index') }}" class="btn btn--outline">View public market</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Sales ({{ now()->format('M') }})</span>
            <span class="stat-tile__num">₦{{ number_format($stats['sales'] / 100) }}</span>
            <span class="stat-tile__foot">{{ $stats['orders'] }} {{ \Illuminate\Support\Str::plural('order', $stats['orders']) }}</span>
        </div>
        <div class="stat-tile" style="background: var(--gold); border-color: var(--gold)">
            <span class="stat-tile__label" style="color: var(--midnight)">Church commission</span>
            <span class="stat-tile__num" style="color: var(--midnight)">₦{{ number_format($stats['commission'] / 100) }}</span>
            <span class="stat-tile__foot" style="color: var(--midnight)">{{ rtrim(rtrim(number_format($commission, 2), '0'), '.') }}% of each order</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Active shops</span>
            <span class="stat-tile__num">{{ $shops->where('status', \App\Models\Shop::APPROVED)->count() }}</span>
            <span class="stat-tile__foot">{{ $stats['products'] }} products listed</span>
        </div>
    </div>

    <section class="box">
        <div class="box__head">
            <h2>Shop applications</h2>
            <span class="box__note">Approving creates the seller's Paystack payout account and emails them a link to set their password.</span>
        </div>
        @forelse ($applications as $shop)
            <div style="border: 1px solid var(--admin-line); border-radius: 20px; padding: 18px; display: flex; flex-direction: column; gap: 10px">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $shop->name }}</span>
                    <span class="pill pill--info">{{ $shop->category }}</span>
                    <span class="pill {{ $shop->cac_number ? 'pill--ok' : 'pill--draft' }}">{{ $shop->cac_number ? 'CAC '.$shop->cac_number : 'No CAC' }}</span>
                    <span class="prayer-card__when">Applied {{ $shop->created_at->diffForHumans() }}</span>
                </div>
                <p class="prayer-card__text">{{ $shop->description }}</p>
                <span class="prayer-card__contact">{{ $shop->owner_name }} · {{ $shop->email }} · {{ $shop->phone }} · {{ $shop->bank_name }} {{ $shop->maskedAccount() }}</span>
                <div class="list-item__actions" style="flex-wrap: wrap">
                    <form method="POST" action="{{ route('admin.market.approve', $shop) }}">
                        @csrf
                        <button type="submit" class="btn btn--navy btn--xs">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.market.decline', $shop) }}" style="display: flex; flex-wrap: wrap; gap: 8px">
                        @csrf
                        <label class="sr-only" for="decline-{{ $shop->id }}">Reason for declining</label>
                        <input id="decline-{{ $shop->id }}" type="text" name="reason" required maxlength="255" placeholder="Reason"
                            style="height: 36px; min-width: 220px; border-radius: 999px; border: 1px solid var(--admin-line); padding: 0 14px; font-size: 13px">
                        <button type="submit" class="btn btn--danger btn--xs">Decline</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="box__note" style="margin: 0">No applications waiting. Members apply at <a href="{{ route('market.apply') }}">{{ route('market.apply') }}</a>.</p>
        @endforelse
    </section>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Recent orders</h2></div>
            <div class="table-wrap">
                <table class="table" style="min-width: 600px">
                    <thead><tr><th>ORDER</th><th>SHOP</th><th>BUYER</th><th>STATUS</th><th class="num">TOTAL</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td style="font-family: ui-monospace, monospace; color: var(--slate)">{{ $order->code }}</td>
                                <td style="font-weight: 700">{{ $order->shop->name }}</td>
                                <td>{{ $order->buyer_name }}</td>
                                <td><span class="pill {{ $order->status === 'completed' ? 'pill--ok' : 'pill--info' }}">{{ $order->statusLabel() }}</span></td>
                                <td class="num" style="font-weight: 800; color: var(--navy)">₦{{ number_format($order->total / 100) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><p class="empty-state">No paid orders yet.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="box side-col">
            <h2>Market settings</h2>
            <form method="POST" action="{{ route('admin.market.settings') }}" class="form">
                @csrf
                <label class="field">Church commission per order
                    <span style="display: flex; align-items: center; gap: 8px">
                        <input type="number" name="commission" value="{{ old('commission', $commission) }}" min="0" max="30" step="0.5" style="width: 100px">
                        <span style="font-size: 15px">%</span>
                    </span>
                    <span class="field__hint">Applies to new orders. Taken by Paystack at payment, so nothing is paid out by hand.</span>
                    @error('commission') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="btn btn--navy">Save settings</button>
            </form>
        </section>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Shops</h2></div>
        @forelse ($shops as $shop)
            <div class="list-item">
                <span class="list-item__main">
                    <a href="{{ route('market.shop', $shop) }}" class="list-item__title">{{ $shop->name }}</a>
                    <span class="list-item__meta">{{ $shop->owner_name }} · {{ $shop->products_count }} {{ \Illuminate\Support\Str::plural('product', $shop->products_count) }} · {{ $shop->paid_orders_count }} {{ \Illuminate\Support\Str::plural('order', $shop->paid_orders_count) }}</span>
                </span>
                @if ($shop->status === \App\Models\Shop::SUSPENDED)
                    <span class="pill pill--warn">Suspended</span>
                @elseif (! $shop->subaccount_code)
                    <span class="pill pill--draft">No payout account</span>
                @endif
                <span class="list-item__actions">
                    @unless ($shop->subaccount_code)
                        <form method="POST" action="{{ route('admin.market.payout', $shop) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--xs">Set up payouts</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('admin.market.suspend', $shop) }}" @if ($shop->isApproved()) data-confirm="Suspend {{ $shop->name }}? Its products will be hidden." @endif>
                        @csrf
                        <button type="submit" class="btn {{ $shop->isApproved() ? 'btn--danger' : 'btn--ghost' }} btn--xs">{{ $shop->isApproved() ? 'Suspend' : 'Reinstate' }}</button>
                    </form>
                </span>
            </div>
        @empty
            <p class="empty-state">No shops yet.</p>
        @endforelse
    </section>
@endsection
