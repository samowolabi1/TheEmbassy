<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">One click to confirm</h1>
        <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6">Someone (hopefully you) asked for the weekly email from {{ config('church.name') }}: the declaration for the week, announcements, new messages and what's coming up. It arrives on Monday mornings.</p>
        <p style="margin: 0 0 24px"><a href="{{ $subscriber->confirmUrl() }}" style="display: inline-block; background: #0A1D3F; color: #FFFFFF; text-decoration: none; font-weight: bold; padding: 14px 24px; border-radius: 999px">Yes, send me the weekly email</a></p>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">If this wasn't you, ignore this email and you won't hear from us.</p>
    </div>
</body>
</html>
