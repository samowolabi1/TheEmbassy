<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.brand.market_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">New order with tokens</h1>
        <p style="margin: 0 0 12px; font-size: 15px; line-height: 1.6"><strong>{{ $order->buyer->communityName() }}</strong> wants <strong>{{ $order->product_name }}</strong> for {{ number_format($order->tokens) }} tokens.</p>
        @if ($order->note)
            <p style="margin: 0 0 12px; font-size: 15px; line-height: 1.6; color: #56627A">"{{ $order->note }}"</p>
        @endif
        <p style="margin: 0 0 12px; font-size: 15px; line-height: 1.6">Their tokens are held. When you hand it over, mark order <strong>{{ $order->code }}</strong> as handed over in your seller page and the tokens become yours. If you can't fulfil it, decline it and they get them back.</p>
        <p style="margin: 0 0 20px"><a href="{{ route('vendor.token-orders.index') }}" style="display: inline-block; background: #0A1D3F; color: #FFFFFF; text-decoration: none; font-weight: bold; padding: 12px 22px; border-radius: 999px">See token orders</a></p>
        <p style="margin: 0; font-size: 13px; color: #56627A">Orders not handed over within {{ \App\Models\TokenOrder::EXPIRES_AFTER_DAYS }} days are cancelled and the tokens returned.</p>
    </div>
</body>
</html>
