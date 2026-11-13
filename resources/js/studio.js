// Live studio: send the control forms without reloading, so the operator never loses their place.

const status = document.querySelector('[data-studio-status]');

for (const form of document.querySelectorAll('form[data-studio]')) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = new FormData(form);
        if (e.submitter?.name) data.set(e.submitter.name, e.submitter.value);
        const hiding = data.get('action') === 'hide';
        // Showing needs the fields filled in; hiding doesn't.
        if (!hiding && !form.reportValidity()) return;
        try {
            // getAttribute, because a button named "action" hides form.action.
            const res = await fetch(form.getAttribute('action'), {
                method: 'POST',
                body: data,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                show(Object.values(body.errors ?? {}).flat()[0] ?? 'That didn\'t work. Try again.', true);
                return;
            }
            show(body.message);
            onAir(body.state);
        } catch {
            show('No connection. Check the internet and try again.', true);
        }
    });
}

function show(message, error = false) {
    if (!status) return;
    status.textContent = message;
    status.hidden = false;
    status.classList.toggle('flash--error', error);
}

function onAir(state) {
    if (!state) return;
    const on = { scene: state.scene, caption: state.lowerThird, scripture: state.scripture, ticker: state.ticker, logo: state.logo };
    for (const [key, value] of Object.entries(on)) {
        const pill = document.querySelector(`[data-onair="${key}"]`);
        if (pill) pill.hidden = !value;
    }
}

// Fit the 1920 × 1080 overlay into the preview box.
const preview = document.querySelector('.studio-preview');
if (preview) {
    const fit = () => preview.style.setProperty('--studio-scale', String(preview.clientWidth / 1920));
    new ResizeObserver(fit).observe(preview);
    fit();
}
