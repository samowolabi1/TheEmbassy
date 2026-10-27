@php($event = $order->event)
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }}</p>
        <h1 style="margin: 0 0 16px; font-size: 24px; color: #0A1D3F">You're booked for {{ $event->title }}</h1>
        <p style="margin: 0 0 8px">Hi {{ $order->name }},</p>
        <p style="margin: 0 0 16px; line-height: 1.5">
            {{ $order->tickets->count() }} {{ \Illuminate\Support\Str::plural('ticket', $order->tickets->count()) }} for
            {{ $event->starts_at->format('l j F, g:i A') }} at {{ $event->venue }}.
        </p>
        <p style="margin: 0 0 20px">
            <a href="{{ $order->url() }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">Show my tickets</a>
        </p>
        <p style="margin: 0; font-size: 13px; color: #56627A; line-height: 1.5">Show the QR code on each ticket at the door. Booking reference {{ $order->code }}.</p>
    </div>
</body>
</html>
