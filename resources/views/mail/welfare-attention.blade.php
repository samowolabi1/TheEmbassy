@php($unit = \App\Models\Unit::ofKind('welfare'))
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">WELFARE · {{ $case->reference }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $heading }}</h1>
        <p style="margin: 0 0 16px; line-height: 1.6">{{ $case->categoryLabel() }}@if ($case->urgency === 'urgent') · <strong style="color: #a6531a">Urgent</strong>@endif @if ($case->amount_approved) · ₦{{ number_format($case->amount_approved / 100) }} recommended @elseif ($case->amount_requested) · ₦{{ number_format($case->amount_requested / 100) }} requested @endif</p>
        @if ($unit)
            <p style="margin: 0 0 16px"><a href="{{ route('unit.welfare.show', [$unit, $case]) }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Open the case</a></p>
        @endif
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">The details are kept in the app, not in email, to protect the person's privacy.</p>
    </div>
</body>
</html>
