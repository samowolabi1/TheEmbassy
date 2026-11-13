<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }} · PRAYER</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">We're praying with you</h1>
        <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">Hi {{ \App\Models\Member::firstNameOf($prayer->member?->name) }}, {{ $reply->author?->communityName() ?? 'the prayer team' }} wrote about your prayer request:</p>
        <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6; white-space: pre-line; border-left: 3px solid #E0A82E; padding-left: 12px">{{ $reply->body }}</p>
        <p style="margin: 0"><a href="{{ route('account.prayer') }}" style="color: #8A6304; font-weight: bold">See your prayer requests</a></p>
    </div>
</body>
</html>
