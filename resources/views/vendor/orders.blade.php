@extends('layouts.vendor', ['title' => 'Orders', 'active' => 'orders'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Orders</h1>
            <span class="page-head__sub">Paid orders and bookings for {{ $shop->name }}. Call the buyer to arrange delivery or confirm the booking.</span>
        </div>
        <div class="actions">
            <a href="{{ route('vendor.products.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a listing</a>
        </div>
    </header>

    <div class="stats">
        <div class="stat-tile stat-tile--navy">
            <span class="stat-tile__label">Your earnings this month</span>
            <span class="stat-tile__num">₦{{ number_format($earnedThisMonth / 100) }}</span>
            <span class="stat-tile__foot">Before Paystack's fee; paid to your bank</span>
        </div>
        <div class="stat-tile">
            <span class="stat-tile__label">Open orders</span>
            <span class="stat-tile__num">{{ $openCount }}</span>
            <span @class(['stat-tile__foot', 'text-warn' => $openCount > 0])>{{ $openCount ? 'Waiting on you' : 'All caught up' }}</span>
        </div>
    </div>

    <nav class="tabs" aria-label="Filter orders">
        <a href="{{ route('vendor.orders.index') }}" class="tab" @if ($open) aria-current="page" @endif>Open</a>
        <a href="{{ route('vendor.orders.index', ['show' => 'done']) }}" class="tab" @unless ($open) aria-current="page" @endunless>Done</a>
    </nav>

    <section class="box box--flush">
        @forelse ($orders as $order)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $order->quantity }} × {{ $order->product->name }}</span>
                    <span class="pill {{ $order->status === \App\Models\MarketOrder::PAID ? 'pill--gold' : ($order->status === \App\Models\MarketOrder::READY ? 'pill--info' : 'pill--ok') }}">{{ $order->statusLabel() }}</span>
                    <span class="prayer-card__when">{{ $order->code }} · paid {{ $order->paid_at->diffForHumans() }}</span>
                </div>
                <span class="prayer-card__contact"><strong>{{ $order->buyer_name }}</strong> · <a href="tel:{{ preg_replace('/[^\d+]/', '', $order->buyer_phone) }}">{{ $order->buyer_phone }}</a> · {{ $order->buyer_email }}</span>
                @if ($order->preferred_date)
                    <span class="prayer-card__contact"><strong>Wanted on:</strong> {{ $order->preferred_date->format('l j F Y') }}</span>
                @endif
                <p class="prayer-card__text"><strong>{{ $order->isService() ? 'Details' : 'Delivery' }}:</strong> {{ $order->delivery_note }}</p>
                <span class="box__note">Total ₦{{ number_format($order->total / 100) }} · your share ₦{{ number_format($order->vendorShare() / 100) }} before Paystack's fee</span>
                @if (in_array($order->status, [\App\Models\MarketOrder::PAID, \App\Models\MarketOrder::READY], true))
                    <div class="list-item__actions" style="flex-wrap: wrap">
                        @foreach ($order->vendorSteps() as $step => $label)
                            @continue($step === $order->status)
                            <form method="POST" action="{{ route('vendor.orders.update', $order) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $step }}">
                                <button type="submit" class="btn {{ $step === \App\Models\MarketOrder::COMPLETED ? 'btn--navy' : 'btn--ghost' }} btn--xs">Mark as {{ strtolower($label) }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <p class="empty-state">{{ $open ? 'No open orders.' : 'Nothing here yet.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $orders])
    </section>
@endsection
