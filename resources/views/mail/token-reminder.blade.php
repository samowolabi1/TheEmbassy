<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">Hi {{ \Illuminate\Support\Str::before($member->name, ' ') }}</h1>
        <p style="margin: 0 0 12px; line-height: 1.5">Thank you for being part of the family. Our records show your tokens are {{ $member->tokenMonthsOwed() }} {{ \Illuminate\Support\Str::plural('month', $member->tokenMonthsOwed()) }} behind (₦{{ number_format($member->tokenAmountOwed() / 100) }} on the {{ $member->tokenPlan->name }} plan).</p>
        @if ($member->user)
            <p style="margin: 0 0 20px"><a href="{{ route('account.tokens') }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Pay tokens online</a></p>
        @else
            <p style="margin: 0 0 20px; line-height: 1.5">You can pay at the church office on Sunday, or <a href="{{ route('join') }}" style="color: #0A1D3F; font-weight: bold">create an account</a> to pay online.</p>
        @endif
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">Already paid, or finding things tight right now? Reply to this email or speak to the church office. We're here for you.</p>
    </div>
</body>
</html>
