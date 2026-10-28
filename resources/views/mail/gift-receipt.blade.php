<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">Thank you, {{ $gift->name }}</h1>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px">
            <tr><td style="padding: 6px 0; color: #56627A">Towards</td><td style="padding: 6px 0; font-weight: bold">{{ $gift->fund->name }}</td></tr>
            <tr><td style="padding: 6px 0; color: #56627A">Amount</td><td style="padding: 6px 0; font-weight: bold">₦{{ number_format($gift->amount / 100) }}</td></tr>
            <tr><td style="padding: 6px 0; color: #56627A">Date</td><td style="padding: 6px 0; font-weight: bold">{{ $gift->given_on->format('j F Y') }}</td></tr>
            <tr><td style="padding: 6px 0; color: #56627A">Reference</td><td style="padding: 6px 0; font-weight: bold">{{ $gift->code }}</td></tr>
        </table>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">"Each of you should give what you have decided in your heart to give." 2 Corinthians 9:7. Keep this email as your receipt.</p>
    </div>
</body>
</html>
