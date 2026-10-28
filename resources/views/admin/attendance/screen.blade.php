<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    {{-- Reload when the code changes. --}}
    <meta http-equiv="refresh" content="{{ $secondsLeft + 1 }}">
    <title>Check in · {{ $service->label() }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=Plus+Jakarta+Sans:wght@500;700&display=swap">
    @include('partials.theme')
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0A1D3F; color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; text-align: center; }
        .wrap { display: flex; flex-wrap: wrap; gap: 6vw; align-items: center; justify-content: center; padding: 4vw; }
        .eyebrow { color: #E0AA1E; font-weight: 700; letter-spacing: 0.14em; font-size: clamp(14px, 1.6vw, 22px); }
        h1 { margin: 8px 0 0; font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(28px, 4vw, 56px); }
        .code { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: clamp(64px, 13vw, 200px); letter-spacing: 0.08em; line-height: 1; margin: 3vh 0; }
        .hint { font-size: clamp(16px, 1.8vw, 26px); color: #C9D6EE; }
        .qr { background: #fff; border-radius: 24px; padding: 16px; width: min(320px, 70vw); }
        .qr svg { width: 100%; height: auto; display: block; }
        .bar { height: 6px; background: rgba(255,255,255,.15); border-radius: 99px; overflow: hidden; margin-top: 3vh; }
        .bar span { display: block; height: 100%; background: #E0AA1E; width: {{ round($secondsLeft * 100 / 60) }}%; }
    </style>
</head>
<body>
    <div class="wrap">
        <div>
            <div class="eyebrow">{{ strtoupper(config('church.short_name')) }}</div>
            <h1>{{ $service->label() }}</h1>
            <div class="code" aria-label="Check-in code {{ implode(' ', str_split($code)) }}">{{ substr($code, 0, 3) }} {{ substr($code, 3) }}</div>
            <div class="hint">Check in at <strong>{{ preg_replace('#^https?://#', '', route('check-in')) }}</strong> and enter this code</div>
            <div class="bar" aria-hidden="true"><span></span></div>
            <div class="hint" style="font-size: 14px; margin-top: 8px">A new code every minute</div>
        </div>
        <div class="qr" role="img" aria-label="QR code to check in">{!! $qr !!}</div>
    </div>
</body>
</html>
