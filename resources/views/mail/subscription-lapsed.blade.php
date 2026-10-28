<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.brand.market_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">Your listings are hidden</h1>
        <p style="margin: 0 0 20px; line-height: 1.5">Hi {{ $shop->owner_name }}, the subscription for {{ $shop->name }} ended on {{ $until->format('j F') }}, so buyers can't see your listings right now. They're all still saved: renew and they're back straight away.</p>
        <p style="margin: 0"><a href="{{ route('vendor.subscription.index') }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Renew my subscription</a></p>
    </div>
</body>
</html>
