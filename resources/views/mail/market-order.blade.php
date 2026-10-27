<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.brand.market_name')) }}</p>
        @if ($forVendor)
            <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">New paid {{ $order->isService() ? 'booking' : 'order' }} {{ $order->code }}</h1>
            <p style="margin: 0 0 12px; line-height: 1.5">{{ $order->quantity }} × {{ $order->product->name }} for {{ $order->buyer_name }} ({{ $order->buyer_phone }}).</p>
            @if ($order->preferred_date)
                <p style="margin: 0 0 12px; line-height: 1.5"><strong>Wanted on:</strong> {{ $order->preferred_date->format('l j F Y') }}</p>
            @endif
            <p style="margin: 0 0 12px; line-height: 1.5"><strong>{{ $order->isService() ? 'Details' : 'Delivery or collection' }}:</strong> {{ $order->delivery_note }}</p>
            <p style="margin: 0 0 20px; line-height: 1.5">Your share, ₦{{ number_format($order->vendorShare() / 100) }} less Paystack's fee, settles to your bank account.</p>
            <a href="{{ route('vendor.orders.index') }}" style="display: inline-block; background: #0A1D3F; color: #FFFFFF; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Open your orders</a>
        @else
            <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">Thanks for your order, {{ $order->buyer_name }}</h1>
            <p style="margin: 0 0 12px; line-height: 1.5">{{ $order->quantity }} × {{ $order->product->name }} from {{ $order->shop->name }}: ₦{{ number_format($order->total / 100) }} paid.</p>
            <p style="margin: 0 0 20px; line-height: 1.5">{{ $order->shop->owner_name }} will contact you on {{ $order->buyer_phone }} {{ $order->isService() ? 'to confirm the time' : 'about delivery or collection' }}.</p>
            <a href="{{ $order->url() }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Track your order</a>
        @endif
        <p style="margin: 20px 0 0; font-size: 13px; color: #56627A">Order {{ $order->code }}</p>
    </div>
</body>
</html>
