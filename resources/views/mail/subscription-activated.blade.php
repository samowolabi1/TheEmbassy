@php($shop = $subscription->shop)
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.brand.market_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $shop->name }} is subscribed</h1>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px">
            <tr><td style="padding: 6px 0; color: #56627A">Plan</td><td style="padding: 6px 0; font-weight: bold">{{ $subscription->plan->name }}</td></tr>
            <tr><td style="padding: 6px 0; color: #56627A">Covers</td><td style="padding: 6px 0; font-weight: bold">{{ $subscription->starts_at->format('j M Y') }} to {{ $subscription->ends_at->format('j M Y') }}</td></tr>
            @if ($subscription->discount)
                <tr><td style="padding: 6px 0; color: #56627A">Coupon</td><td style="padding: 6px 0; font-weight: bold">{{ $subscription->coupon?->code }} (−₦{{ number_format($subscription->discount / 100) }})</td></tr>
            @endif
            <tr><td style="padding: 6px 0; color: #56627A">Paid</td><td style="padding: 6px 0; font-weight: bold">₦{{ number_format($subscription->amount / 100) }}</td></tr>
        </table>
        <p style="margin: 0 0 20px"><a href="{{ route('market.shop', $shop) }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">See your shop</a></p>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">Your listings show in the market until {{ $subscription->ends_at->format('j F Y') }}. We'll keep this as your receipt.</p>
    </div>
</body>
</html>
