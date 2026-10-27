@extends('layouts.app', ['title' => 'Your order', 'active' => 'market'])

@section('content')
    <section class="container notices-hero section--last" style="max-width: 760px">
        <div class="card notice-status stack" style="gap: 16px">
            <span class="eyebrow">ORDER {{ $order->code }}</span>
            <h1 class="h2">{{ $order->statusLabel() }}</h1>
            @if (session('payment_error'))
                <p class="notice-alert" role="alert" style="margin: 0">{{ session('payment_error') }}</p>
            @endif
            <p class="lede" style="margin: 0">
                @switch($order->status)
                    @case(\App\Models\MarketOrder::AWAITING_PAYMENT)
                        @if ($payment?->status === \App\Models\Payment::FAILED)
                            Your payment didn't go through. You can try again below.
                        @else
                            We haven't received your payment yet. If you've just paid, give it a minute and refresh this page.
                        @endif
                        @break
                    @case(\App\Models\MarketOrder::PAID)
                        Thanks! {{ $order->shop->owner_name }} has your order and will call you on {{ $order->buyer_phone }} about delivery or collection.
                        @break
                    @case(\App\Models\MarketOrder::READY)
                        Your order is ready or on its way.
                        @break
                    @case(\App\Models\MarketOrder::COMPLETED)
                        Delivered. Enjoy, and thank you for supporting the family.
                        @break
                    @default
                        This order was cancelled.
                @endswitch
            </p>
            <dl class="notice-status__facts">
                <dt>Item</dt><dd>{{ $order->quantity }} × {{ $order->product->name }}</dd>
                <dt>Shop</dt><dd><a href="{{ route('market.shop', $order->shop) }}">{{ $order->shop->name }}</a> · {{ $order->shop->phone }}</dd>
                <dt>Total</dt><dd>₦{{ number_format($order->total / 100) }}</dd>
                <dt>Delivery</dt><dd style="white-space: pre-line">{{ $order->delivery_note }}</dd>
            </dl>
            @if ($order->status === \App\Models\MarketOrder::AWAITING_PAYMENT && $paymentsEnabled && $order->product->canBuy())
                <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('market.orders.pay', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn--gold btn--md">Pay ₦{{ number_format($order->total / 100) }}</button>
                </form>
            @endif
            <p class="field-hint" style="margin: 0">Keep this page's link to check on your order.</p>
        </div>
    </section>
@endsection
