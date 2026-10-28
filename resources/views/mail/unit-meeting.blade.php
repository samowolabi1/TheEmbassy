<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper($meeting->unit->name) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $meeting->title }}</h1>
        <p style="margin: 0 0 12px; line-height: 1.6"><strong>{{ $meeting->starts_at->format('l j F, g:i A') }}</strong>@if ($meeting->location)<br>{{ $meeting->location }}@endif</p>
        @if ($meeting->agenda)<p style="margin: 0 0 16px; line-height: 1.5; white-space: pre-line">{{ $meeting->agenda }}</p>@endif
        @if ($meeting->online_url)<p style="margin: 0 0 20px"><a href="{{ $meeting->online_url }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Join online</a></p>@endif
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">Check in from <a href="{{ route('account') }}">your account page</a> with the code your HOD shares at the meeting.</p>
    </div>
</body>
</html>
