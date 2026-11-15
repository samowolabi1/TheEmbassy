<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper($kind === 'seller' ? config('church.brand.market_name') : config('church.short_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $headline }}</h1>
        <div style="font-size: 15px; line-height: 1.65">{!! nl2br(e($body)) !!}</div>
        @if ($url)
            <p style="margin: 22px 0 0"><a href="{{ $url }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">{{ $kind === 'seller' ? 'Open your shop' : 'Open the app' }}</a></p>
        @endif
        <p style="margin: 24px 0 0; font-size: 12px; color: #56627A">From {{ config('church.name') }}{{ config('church.email') ? ' · reply to '.config('church.email') : '' }}</p>
    </div>
</body>
</html>
