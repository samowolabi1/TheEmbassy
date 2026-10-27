@php($product = $enquiry->product)
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.brand.market_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $enquiry->name }} is asking about {{ $product?->name ?? 'your shop' }}</h1>
        <p style="margin: 0 0 16px; line-height: 1.5; white-space: pre-line">{{ $enquiry->message }}</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px">
            <tr><td style="padding: 6px 0; color: #56627A">Phone</td><td style="padding: 6px 0; font-weight: bold">{{ $enquiry->phone }}</td></tr>
            @if ($enquiry->email)
                <tr><td style="padding: 6px 0; color: #56627A">Email</td><td style="padding: 6px 0; font-weight: bold">{{ $enquiry->email }}</td></tr>
            @endif
            @if ($enquiry->preferred_date)
                <tr><td style="padding: 6px 0; color: #56627A">Wanted for</td><td style="padding: 6px 0; font-weight: bold">{{ $enquiry->preferred_date->format('l j F Y') }}</td></tr>
            @endif
        </table>
        <p style="margin: 0 0 20px"><a href="{{ route('vendor.enquiries.index') }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Open your enquiries</a></p>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">Reply to the buyer directly. Payment is between you and them.</p>
    </div>
</body>
</html>
