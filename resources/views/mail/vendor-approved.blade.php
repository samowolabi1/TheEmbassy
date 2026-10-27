<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.brand.market_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $shop->name }} is approved</h1>
        <p style="margin: 0 0 12px; line-height: 1.5">Hi {{ $shop->owner_name }}, welcome to {{ config('church.brand.market_name') }}. Set your password, choose a subscription and add your products or services.</p>
        <p style="margin: 0 0 20px"><a href="{{ $setPasswordUrl }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Set your password</a></p>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">This link expires in an hour. If it does, use "Forgot password" on the vendor sign-in page.</p>
    </div>
</body>
</html>
