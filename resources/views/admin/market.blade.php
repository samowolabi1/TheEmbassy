@extends('layouts.admin', ['title' => config('church.brand.market_name'), 'active' => 'market'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>{{ config('church.brand.market_name') }}</h1>
            <span class="page-head__sub">Member shops, their subscriptions, prices and coupons</span>
        </div>
        <div class="actions">
            <a href="{{ route('market.index') }}" class="btn btn--outline">View public market</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile" style="background: var(--gold); border-color: var(--gold)">
            <span class="stat-tile__label" style="color: var(--midnight)">Subscriptions ({{ now()->format('M') }})</span>
            <span class="stat-tile__num" style="color: var(--midnight)">₦{{ number_format($stats['income'] / 100) }}</span>
            <span class="stat-tile__foot" style="color: var(--midnight)">{{ $stats['subscriptions'] }} {{ \Illuminate\Support\Str::plural('payment', $stats['subscriptions']) }}, after coupons</span>
        </div>
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Listed shops</span>
            <span class="stat-tile__num">{{ $stats['listed'] }}</span>
            <span class="stat-tile__foot">of {{ $shops->where('status', \App\Models\Shop::APPROVED)->count() }} approved</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Live listings</span>
            <span class="stat-tile__num">{{ $stats['products'] }}</span>
            <span class="stat-tile__foot">Products and services</span>
        </div>
    </div>

    <section class="box">
        <div class="box__head">
            <h2>Shop applications</h2>
            <span class="box__note">Approving emails the seller a link to set their password. Their listings show once they subscribe.</span>
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
                <span class="prayer-card__contact">{{ $shop->owner_name }} · {{ $shop->email }} · {{ $shop->phone }}</span>
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
        <section class="box grow">
            <div class="box__head">
                <h2>Subscription prices</h2>
                <span class="box__note">Sellers only see plans that are on and priced. Price changes apply to new subscriptions.</span>
            </div>
            <form method="POST" action="{{ route('admin.market.plans') }}" class="form">
                @csrf
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>PLAN</th><th>PRICE (₦)</th><th>OFFERED</th></tr></thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr>
                                    <td style="font-weight: 700">{{ $plan->name }}</td>
                                    <td>
                                        <label class="sr-only" for="plan-price-{{ $plan->id }}">{{ $plan->name }} price in naira</label>
                                        <input id="plan-price-{{ $plan->id }}" type="number" name="plans[{{ $plan->id }}][price]" value="{{ old('plans.'.$plan->id.'.price', $plan->price ? $plan->price / 100 : '') }}" min="0" step="1" placeholder="0" style="width: 100%; max-width: 130px">
                                    </td>
                                    <td>
                                        <input type="hidden" name="plans[{{ $plan->id }}][is_active]" value="0">
                                        <label style="display: inline-flex; gap: 8px; align-items: center"><input type="checkbox" name="plans[{{ $plan->id }}][is_active]" value="1" @checked($plan->is_active)> On</label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('plans.*') <span class="field-error">{{ $message }}</span> @enderror
                <div><button type="submit" class="btn btn--navy">Save prices</button></div>
            </form>
        </section>

        <section class="box side-col">
            <h2>New coupon</h2>
            <form method="POST" action="{{ route('admin.market.coupons.store') }}" class="form">
                @csrf
                <label class="field">Code
                    <input type="text" name="code" value="{{ old('code') }}" required maxlength="40" autocomplete="off" placeholder="WELCOME50" style="text-transform: uppercase">
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Discount
                    <select name="percent_off" required>
                        @foreach (config('market.coupon_percents') as $percent)
                            <option value="{{ $percent }}" @selected((int) old('percent_off') === $percent)>{{ $percent }}% off{{ $percent === 100 ? ' (free)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('percent_off') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Uses allowed (optional)
                    <input type="number" name="max_uses" value="{{ old('max_uses') }}" min="1" placeholder="No limit">
                    @error('max_uses') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Last day (optional)
                    <input type="date" name="expires_at" value="{{ old('expires_at') }}" min="{{ today()->toDateString() }}">
                    @error('expires_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="btn btn--navy">Create coupon</button>
            </form>
        </section>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Coupons</h2></div>
        @forelse ($coupons as $coupon)
            <div class="list-item">
                <span class="list-item__main">
                    <span class="list-item__title" style="font-family: ui-monospace, monospace">{{ $coupon->code }}</span>
                    <span class="list-item__meta">{{ $coupon->percent_off }}% off · used {{ $coupon->uses_count }}{{ $coupon->max_uses ? ' of '.$coupon->max_uses : '' }} · {{ $coupon->expires_at ? 'until '.$coupon->expires_at->format('j M Y') : 'no end date' }}</span>
                </span>
                @if (! $coupon->is_active)
                    <span class="pill pill--draft">Off</span>
                @elseif ($coupon->expires_at?->isPast())
                    <span class="pill pill--draft">Expired</span>
                @elseif ($coupon->max_uses && $coupon->uses_count >= $coupon->max_uses)
                    <span class="pill pill--draft">Used up</span>
                @else
                    <span class="pill pill--ok">Live</span>
                @endif
                <span class="list-item__actions">
                    <form method="POST" action="{{ route('admin.market.coupons.toggle', $coupon) }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--xs">{{ $coupon->is_active ? 'Switch off' : 'Switch on' }}<span class="sr-only"> {{ $coupon->code }}</span></button>
                    </form>
                </span>
            </div>
        @empty
            <p class="empty-state">No coupons yet.</p>
        @endforelse
    </section>

    <section class="box box--flush">
        <div class="box__head"><h2>Shops</h2></div>
        @forelse ($shops as $shop)
            <div class="list-item">
                <span class="list-item__main">
                    <a href="{{ route('market.shop', $shop) }}" class="list-item__title">{{ $shop->name }}</a>
                    <span class="list-item__meta">{{ $shop->owner_name }} · {{ $shop->products_count }} {{ \Illuminate\Support\Str::plural('listing', $shop->products_count) }} · {{ $shop->phone }}</span>
                </span>
                @php($until = $shop->subscribed_until ? \Illuminate\Support\Carbon::parse($shop->subscribed_until) : null)
                @if ($shop->status === \App\Models\Shop::SUSPENDED)
                    <span class="pill pill--warn">Suspended</span>
                @elseif ($until?->isFuture())
                    <span class="pill pill--ok">Paid to {{ $until->format('j M Y') }}</span>
                @elseif ($until)
                    <span class="pill pill--draft">Lapsed {{ $until->format('j M') }}</span>
                @else
                    <span class="pill pill--draft">Not subscribed</span>
                @endif
                <span class="list-item__actions">
                    <form method="POST" action="{{ route('admin.market.suspend', $shop) }}" @if ($shop->isApproved()) data-confirm="Suspend {{ $shop->name }}? Its listings will be hidden." @endif>
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
