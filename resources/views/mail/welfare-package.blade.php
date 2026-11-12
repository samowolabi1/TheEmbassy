@php($a = $application)
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }} · WELFARE</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $a->package->title }}</h1>
        <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">Hi {{ \Illuminate\Support\Str::before($a->member->name, ' ') }},</p>
        @switch($a->status)
            @case('approved')
                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">Your application has been approved. Show this reference when you collect:</p>
                <p style="margin: 0 0 16px; font-size: 26px; font-weight: bold; letter-spacing: 0.08em; color: #0A1D3F">{{ $a->reference }}</p>
                @if ($a->package->collection_details)
                    <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6"><strong>Collection:</strong> {{ $a->package->collection_details }}</p>
                @endif
                @break
            @case('waitlist')
                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">You're on the waiting list. We'll let you know if a place opens up.</p>
                @break
            @default
                <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6">We aren't able to include you in this package this time. Please speak to the welfare team or a pastor if you need help; you can also ask for support from your account.</p>
        @endswitch
        @if ($a->decision_note)
            <p style="margin: 0 0 14px; font-size: 15px; line-height: 1.6; color: #56627A">"{{ $a->decision_note }}"</p>
        @endif
        <p style="margin: 0"><a href="{{ route('account.welfare') }}" style="color: #8A6304; font-weight: bold">See it in your account</a></p>
    </div>
</body>
</html>
