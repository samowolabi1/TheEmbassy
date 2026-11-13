<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Studio overlay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@500;600;700&family=Cinzel:wght@700&display=swap">
    {{-- Made for OBS: add as a browser source, 1920 × 1080. The background is see-through. --}}
    <style>
        :root { --navy: #0a1d3f; --navy-2: #13306a; --gold: #e0a82e; --white: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; width: 1920px; height: 1080px; overflow: hidden; background: transparent; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--white); }
        .layer { position: absolute; transition: opacity 0.5s ease, transform 0.5s ease; opacity: 0; pointer-events: none; }
        .layer.on { opacity: 1; transform: none; }

        .scene { inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 28px; text-align: center;
            background: radial-gradient(circle at 50% 40%, var(--navy-2), var(--navy) 70%); }
        .scene__logo { width: 180px; height: 180px; border-radius: 50%; background: var(--white); display: grid; place-items: center; box-shadow: 0 0 0 8px var(--gold); }
        .scene__logo img { width: 130px; height: auto; }
        .scene__title { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 120px; line-height: 1; margin: 0; letter-spacing: -0.02em; }
        .scene__text { font-size: 40px; font-weight: 600; max-width: 1400px; margin: 0; color: #dfe6f5; }
        .scene__count { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 160px; color: var(--gold); font-variant-numeric: tabular-nums; line-height: 1; }
        .scene__church { position: absolute; bottom: 60px; font-family: 'Cinzel', serif; font-size: 32px; letter-spacing: 0.12em; color: var(--gold); }

        .lower { left: 90px; bottom: 120px; transform: translateX(-40px); display: flex; align-items: stretch; filter: drop-shadow(0 12px 30px rgba(0, 0, 0, 0.35)); }
        .lower__bar { width: 14px; background: var(--gold); border-radius: 8px 0 0 8px; }
        .lower__box { background: linear-gradient(90deg, var(--navy), var(--navy-2)); padding: 22px 44px 24px 30px; border-radius: 0 16px 16px 0; }
        .lower__name { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 54px; line-height: 1.05; }
        .lower__title { font-size: 30px; font-weight: 600; color: var(--gold); margin-top: 6px; }

        .verse { left: 50%; bottom: 120px; width: 1500px; transform: translate(-50%, 40px); background: rgba(10, 29, 63, 0.92); border-top: 6px solid var(--gold); border-radius: 18px; padding: 30px 48px 34px; filter: drop-shadow(0 12px 30px rgba(0, 0, 0, 0.35)); }
        .verse.on { transform: translate(-50%, 0); }
        .verse__ref { font-family: 'Cinzel', serif; font-size: 30px; color: var(--gold); letter-spacing: 0.08em; }
        .verse__text { font-size: 42px; line-height: 1.35; font-weight: 600; margin-top: 10px; }

        .with-ticker .lower, .with-ticker .verse { bottom: 170px; }
        .ticker { left: 0; right: 0; bottom: 0; height: 76px; transform: translateY(76px); display: flex; background: var(--navy); border-top: 4px solid var(--gold); }
        .ticker__label { flex: none; display: grid; place-items: center; padding: 0 30px; background: var(--gold); color: var(--navy); font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 28px; letter-spacing: 0.06em; }
        .ticker__track { flex: 1; overflow: hidden; display: flex; align-items: center; }
        .ticker__text { white-space: nowrap; font-size: 32px; font-weight: 600; padding-left: 100%; animation: scroll 28s linear infinite; }
        @keyframes scroll { to { transform: translateX(-100%); } }

        .logo { top: 50px; right: 60px; width: 120px; height: 120px; border-radius: 50%; background: var(--white); display: grid; place-items: center; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3); }
        .logo img { width: 90px; height: auto; }
    </style>
</head>
<body>
    <div class="layer scene" data-layer="scene">
        <div class="scene__logo"><img src="{{ asset(config('church.brand.logo')) }}" alt=""></div>
        <h1 class="scene__title" data-scene-title></h1>
        <div class="scene__count" data-countdown hidden></div>
        <p class="scene__text" data-scene-text></p>
        <div class="scene__church">{{ mb_strtoupper(config('church.name')) }}</div>
    </div>
    <div class="layer lower" data-layer="lower">
        <div class="lower__bar"></div>
        <div class="lower__box"><div class="lower__name" data-lower-name></div><div class="lower__title" data-lower-title></div></div>
    </div>
    <div class="layer verse" data-layer="verse">
        <div class="verse__ref" data-verse-ref></div>
        <div class="verse__text" data-verse-text></div>
    </div>
    <div class="layer ticker" data-layer="ticker">
        <div class="ticker__label">{{ mb_strtoupper(config('church.short_name')) }}</div>
        <div class="ticker__track"><div class="ticker__text" data-ticker-text></div></div>
    </div>
    <div class="layer logo" data-layer="logo"><img src="{{ asset(config('church.brand.logo')) }}" alt=""></div>

    <script>
        (() => {
            const stateUrl = @json(route('studio.overlay.state', $token));
            let state = @json($state);
            let version = -1;
            const $ = (sel) => document.querySelector(sel);
            const layer = (name, on) => $(`[data-layer="${name}"]`).classList.toggle('on', !!on);

            function apply(s) {
                if (s.version === version) return;
                version = s.version;
                state = s;
                layer('scene', s.scene);
                $('[data-scene-title]').textContent = s.scene === 'message' ? (s.sceneText || '') : (s.sceneTitle || '');
                $('[data-scene-text]').textContent = s.scene === 'message' ? '' : (s.sceneText || '');
                layer('lower', s.lowerThird && !s.scene);
                if (s.lowerThird) {
                    $('[data-lower-name]').textContent = s.lowerThird.name || '';
                    $('[data-lower-title]').textContent = s.lowerThird.title || '';
                    $('[data-lower-title]').hidden = !s.lowerThird.title;
                }
                layer('verse', s.scripture && !s.scene && !s.lowerThird);
                if (s.scripture) {
                    $('[data-verse-ref]').textContent = s.scripture.reference || '';
                    $('[data-verse-text]').textContent = s.scripture.text || '';
                }
                layer('ticker', s.ticker && !s.scene);
                if (s.ticker && $('[data-ticker-text]').textContent !== s.ticker) $('[data-ticker-text]').textContent = s.ticker;
                document.body.classList.toggle('with-ticker', !!s.ticker && !s.scene);
                layer('logo', s.logo && !s.scene);
                tick();
            }

            function tick() {
                const el = $('[data-countdown]');
                const target = state.scene === 'starting' && state.countdownTo ? new Date(state.countdownTo).getTime() : null;
                if (!target) { el.hidden = true; return; }
                const left = Math.max(0, Math.round((target - Date.now()) / 1000));
                const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), sec = left % 60;
                el.textContent = (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(sec).padStart(2, '0');
                el.hidden = false;
            }

            async function poll() {
                try {
                    const res = await fetch(stateUrl, { cache: 'no-store', headers: { Accept: 'application/json' } });
                    if (res.ok) apply(await res.json());
                } catch (e) { /* keep showing what's on screen */ }
            }

            apply(state);
            setInterval(poll, 1000);
            setInterval(tick, 250);
        })();
    </script>
</body>
</html>
