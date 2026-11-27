// The verse scroll: swipe up (or tap, or press the down arrow) for another verse; save,
// share or copy one. More verses load before the reel runs out. The daily verse pop-up
// opens by itself. Without JavaScript the reel still scrolls and Save still works.

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

// The daily verse.
document.querySelector('[data-daily-verse]')?.showModal?.();

const reel = document.querySelector('[data-reel]');
if (reel) {
    const template = document.querySelector('[data-card-template]');
    const seen = new Set([...reel.querySelectorAll('[data-verse]')].map((c) => c.dataset.verse));
    let loading = false;

    const cards = () => [...reel.querySelectorAll('[data-verse]')];
    const current = () => Math.round(reel.scrollTop / reel.clientHeight);
    const next = () => reel.scrollTo({ top: (current() + 1) * reel.clientHeight, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });

    const addCard = (v) => {
        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.verse = v.id;
        card.dataset.ref = v.reference;
        card.className = `verse-card verse-card--${v.id % 6}${v.text.length > 200 ? ' verse-card--long' : ''}`;
        card.querySelector('[data-text]').textContent = v.text;
        card.querySelector('[data-ref-label]').textContent = v.reference;
        card.querySelector('[data-theme-label]').textContent = v.theme || '';
        card.querySelector('[data-save-form]').action = reel.dataset.save.replace('__ID__', v.id);
        reel.append(card);
        seen.add(String(v.id));
    };

    const loadMore = async () => {
        if (loading) return;
        loading = true;
        try {
            const url = `${reel.dataset.more}?seen=${[...seen].slice(-900).join(',')}`;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (res.ok) (await res.json()).verses.forEach(addCard);
        } finally {
            loading = false;
        }
    };

    reel.addEventListener('scroll', () => {
        if (cards().length - current() < 6) loadMore();
    }, { passive: true });

    // Tap anywhere on a verse (but not its buttons) for the next one.
    reel.addEventListener('click', (e) => {
        if (e.target.closest('button, a, form')) return;
        next();
    });
    reel.addEventListener('keydown', (e) => {
        if (['ArrowDown', 'PageDown', ' '].includes(e.key)) { e.preventDefault(); next(); }
        if (['ArrowUp', 'PageUp'].includes(e.key)) { e.preventDefault(); reel.scrollTo({ top: (current() - 1) * reel.clientHeight, behavior: 'smooth' }); }
    });

    const textOf = (card) => `"${card.querySelector('[data-text]').textContent.trim()}" — ${card.dataset.ref} (KJV)`;
    const flash = (el, label, done) => {
        const old = el.textContent;
        el.textContent = label;
        setTimeout(() => { el.textContent = done ?? old; }, 1800);
    };

    reel.addEventListener('submit', async (e) => {
        const form = e.target.closest('[data-save-form]');
        if (!form) return;
        e.preventDefault();
        const button = form.querySelector('[data-save]');
        try {
            const res = await fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            if (!res.ok) throw new Error();
            const { saved } = await res.json();
            button.setAttribute('aria-pressed', saved ? 'true' : 'false');
            button.firstElementChild.textContent = saved ? '♥' : '♡';
            button.querySelector('[data-save-label]').textContent = saved ? 'Saved' : 'Save';
            const count = document.querySelector('[data-saved-count]');
            if (count) count.textContent = Math.max(0, Number(count.textContent) + (saved ? 1 : -1));
        } catch {
            flash(button.querySelector('[data-save-label]'), 'Try again');
        }
    });

    reel.addEventListener('click', async (e) => {
        const card = e.target.closest('[data-verse]');
        if (!card) return;
        if (e.target.closest('[data-share]')) {
            const text = textOf(card);
            if (navigator.share) {
                try { await navigator.share({ text }); } catch { /* closed the share sheet */ }
            } else {
                window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
            }
        }
        if (e.target.closest('[data-copy]')) {
            const label = card.querySelector('[data-copy-label]');
            const text = textOf(card);
            let copied = false;
            try {
                await navigator.clipboard.writeText(text);
                copied = true;
            } catch {
                // Some browsers block the clipboard: copy through a hidden text box instead.
                const box = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
                box.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
                document.body.append(box);
                box.select();
                try { copied = document.execCommand('copy'); } catch { copied = false; }
                box.remove();
            }
            flash(label, copied ? 'Copied' : 'Press and hold the verse to copy');
        }
    });
}
