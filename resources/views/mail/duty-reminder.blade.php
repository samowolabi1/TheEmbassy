<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper($duty->unit->name) }} ROTA</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">You're serving: {{ $duty->role }}</h1>
        <p style="margin: 0 0 16px; line-height: 1.6">{{ $duty->service->label() }}, <strong>{{ $duty->service->starts_at ? $duty->service->starts_at->format('l j F, g:i A') : $duty->service->held_on->format('l j F') }}</strong>.</p>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">Can't make it? Let your HOD know as soon as you can so they can find cover.</p>
    </div>
</body>
</html>
