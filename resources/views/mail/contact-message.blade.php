<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">WEBSITE MESSAGE</p>
        <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1D3F">{{ $contact->subject }}</h1>
        <p style="margin: 0 0 16px; line-height: 1.5; white-space: pre-line">{{ $contact->message }}</p>
        <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6"><strong>{{ $contact->name }}</strong><br>{{ $contact->email }}@if ($contact->phone)<br>{{ $contact->phone }}@endif</p>
        <p style="margin: 0"><a href="{{ route('admin.messages.index') }}" style="color: #0A1D3F; font-weight: bold">Open the messages inbox</a></p>
    </div>
</body>
</html>
