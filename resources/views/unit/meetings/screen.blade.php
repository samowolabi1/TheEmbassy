<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="refresh" content="{{ $secondsLeft + 1 }}">
    <title>Check in · {{ $meeting->title }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=Plus+Jakarta+Sans:wght@500;700&display=swap">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0A1D3F; color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; text-align: center; padding: 16px; box-sizing: border-box; }
        .eyebrow { color: #E0AA1E; font-weight: 700; letter-spacing: 0.14em; }
        h1 { margin: 8px 0 0; font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(26px, 4vw, 52px); }
        .code { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: clamp(64px, 14vw, 200px); letter-spacing: 0.08em; margin: 3vh 0; }
        .hint { font-size: clamp(16px, 1.8vw, 24px); color: #C9D6EE; }
    </style>
</head>
<body>
    <div>
        <div class="eyebrow">{{ strtoupper($unit->name) }}</div>
        <h1>{{ $meeting->title }}</h1>
        <div class="code" aria-label="Check-in code {{ implode(' ', str_split($code)) }}">{{ substr($code, 0, 3) }} {{ substr($code, 3) }}</div>
        <div class="hint">Open your account page on {{ preg_replace('#^https?://#', '', route('account')) }} and enter this code. A new code every minute.</div>
    </div>
</body>
</html>
