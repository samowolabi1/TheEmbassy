<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">WELFARE · {{ $case->reference }}</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $case->status === 'declined' ? 'About your request' : 'Your request has been approved' }}</h1>
        @if ($case->status === 'declined')
            <p style="margin: 0 0 16px; line-height: 1.6">Thank you for reaching out. We're not able to help with this request right now: {{ $case->decision_note }}. You're still in our prayers, and the welfare team is here if things change.</p>
        @else
            <p style="margin: 0 0 16px; line-height: 1.6">The welfare team has approved support for "{{ $case->summary }}"@if ($case->amount_approved) of ₦{{ number_format($case->amount_approved / 100) }}@endif. Someone from the team will be in touch about the next steps.</p>
        @endif
        <p style="margin: 0"><a href="{{ route('account.welfare') }}" style="color: #0A1D3F; font-weight: bold">See your requests</a></p>
    </div>
</body>
</html>
