{{-- Error pages stand alone (no database, no built files) so they still show when those are the problem. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('church.short_name', config('app.name')) }}</title>
    <style>
        :root { --navy: #0a1d3f; --gold: #e0aa1e; --midnight: #06132b; --slate: #56627a; --ivory: #fbf8f1; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: var(--ivory); color: var(--navy); font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        main { max-width: 520px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 16px; }
        .code { font-size: clamp(72px, 18vw, 120px); font-weight: 900; line-height: 1; letter-spacing: -0.04em; color: var(--gold); }
        h1 { margin: 0; font-size: clamp(26px, 6vw, 34px); letter-spacing: -0.02em; }
        p { margin: 0; font-size: 17px; line-height: 1.6; color: var(--slate); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 8px; }
        a.btn, button.btn { display: inline-flex; align-items: center; padding: 13px 22px; border-radius: 999px; border: 1px solid var(--navy); font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .btn--gold { background: var(--gold); border-color: var(--gold); color: var(--midnight); }
        .btn--ghost { background: transparent; color: var(--navy); }
        img { width: 72px; height: auto; }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset(config('church.brand.logo', 'logo.png')) }}" alt="{{ config('church.short_name', config('app.name')) }}">
        <span class="code">@yield('code')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @section('actions')
                <a href="{{ url('/') }}" class="btn btn--gold">Go to the home page</a>
                <a href="javascript:history.back()" class="btn btn--ghost">Go back</a>
            @show
        </div>
    </main>
</body>
</html>
