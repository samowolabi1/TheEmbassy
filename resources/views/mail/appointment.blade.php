@php($a = $appointment)
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }} · VISIT WITH A PASTOR</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $headline }}</h1>
        <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">{{ $body }}</p>
        @if ($a->call_link && ! $forStaff && $a->status === 'confirmed')
            <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6"><strong>Join the call:</strong> <a href="{{ $a->call_link }}" style="color: #8A6304">{{ $a->call_link }}</a></p>
        @endif
        <p style="margin: 0 0 14px; font-size: 14px; line-height: 1.6; color: #56627A">{{ $a->when() }} · {{ \App\Models\Appointment::MODES[$a->mode] }} · {{ $a->reference }}</p>
        <p style="margin: 0"><a href="{{ $link }}" style="color: #8A6304; font-weight: bold">{{ $forStaff ? 'Open appointments' : 'See it in your account' }}</a></p>
    </div>
</body>
</html>
