<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">FOLLOW-UP · {{ strtoupper($followUp->reasonLabel()) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">Please reach out to {{ $followUp->personName() }}</h1>
        @if ($followUp->note)<p style="margin: 0 0 12px; line-height: 1.5">{{ $followUp->note }}</p>@endif
        <p style="margin: 0 0 12px; line-height: 1.5">@if ($followUp->phone())Phone: <strong>{{ $followUp->phone() }}</strong> · @endif By {{ $followUp->due_on->format('l j F') }}</p>
        <p style="margin: 0"><a href="{{ route('admin.care.index') }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Open my follow-ups</a></p>
    </div>
</body>
</html>
