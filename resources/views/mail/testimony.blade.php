<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }} · TESTIMONY</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $headline }}</h1>
        <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">{{ $body }}</p>
        <p style="margin: 0 0 14px; font-size: 14px; line-height: 1.6; color: #56627A">"{{ $testimony->title }}"</p>
        <p style="margin: 0">
            @if ($testimony->status === 'published' && $testimony->share_publicly)
                <a href="{{ route('testimonies.show', $testimony) }}" style="color: #8A6304; font-weight: bold">Read it on the website</a>
            @else
                <a href="{{ route('account.testimonies') }}" style="color: #8A6304; font-weight: bold">See it in your account</a>
            @endif
        </p>
    </div>
</body>
</html>
