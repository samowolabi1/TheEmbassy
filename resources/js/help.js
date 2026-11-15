// Admin help: the Help panel, guided tours of each page, tooltips and the help centre search.
// Without JavaScript the Help button simply opens the help centre.

const store = {
    get(key) {
        try { return localStorage.getItem(key); } catch { return null; }
    },
    set(key, value) {
        try { localStorage.setItem(key, value); } catch { /* private mode: fine */ }
    },
};

// ---------- Help panel ----------
const panel = document.querySelector('[data-help-panel]');
const fab = document.querySelector('[data-help-open]');
if (panel && fab && typeof panel.showModal === 'function') {
    fab.addEventListener('click', (e) => {
        e.preventDefault();
        panel.showModal();
    });
    panel.querySelector('[data-help-close]')?.addEventListener('click', () => panel.close());
    // Clicking the dimmed area outside the panel closes it.
    panel.addEventListener('click', (e) => {
        if (e.target === panel) panel.close();
    });
}

// ---------- Guided tour ----------
const tourData = document.getElementById('page-tour');
const steps = tourData ? JSON.parse(tourData.textContent) : [];
const tourKey = tourData ? `tour-done:${tourData.dataset.key}` : null;

const visible = (el) => el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

const findTarget = (target) => {
    if (target.startsWith('box:')) {
        const label = target.slice(4).trim().toLowerCase();
        const heading = [...document.querySelectorAll('main h2, main h3, main summary')]
            .find((h) => h.textContent.trim().toLowerCase().startsWith(label));
        return heading?.closest('section, .box, form, details, article') ?? heading ?? null;
    }
    try {
        return [...document.querySelectorAll(target)].find(visible) ?? null;
    } catch {
        return null;
    }
};

let tour = null;

const endTour = (finished) => {
    if (!tour) return;
    tour.spot.remove();
    tour.card.remove();
    window.removeEventListener('resize', tour.place);
    window.removeEventListener('scroll', tour.place, true);
    document.removeEventListener('keydown', tour.keys);
    if (finished && tourKey) store.set(tourKey, '1');
    tour.returnFocus?.focus();
    tour = null;
};

const startTour = () => {
    const usable = steps.map((s) => ({ ...s, el: findTarget(s.target) })).filter((s) => visible(s.el));
    if (!usable.length) return;
    if (panel?.open) panel.close();
    endTour(false);

    const spot = document.createElement('div');
    spot.className = 'tour-spot';
    spot.setAttribute('aria-hidden', 'true');
    const card = document.createElement('div');
    card.className = 'tour-card';
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'false');
    card.setAttribute('aria-labelledby', 'tour-title');
    card.tabIndex = -1;
    document.body.append(spot, card);

    let index = 0;
    const place = () => {
        const el = usable[index].el;
        const r = el.getBoundingClientRect();
        const pad = 6;
        Object.assign(spot.style, {
            top: `${r.top - pad}px`, left: `${r.left - pad}px`,
            width: `${r.width + pad * 2}px`, height: `${r.height + pad * 2}px`,
        });
        const cw = Math.min(340, window.innerWidth - 24);
        card.style.width = `${cw}px`;
        const ch = card.offsetHeight;
        let top = r.bottom + 14;
        if (top + ch > window.innerHeight - 12) top = r.top - ch - 14;
        if (top < 12) top = Math.max(12, window.innerHeight - ch - 12);
        const left = Math.min(Math.max(12, r.left), window.innerWidth - cw - 12);
        Object.assign(card.style, { top: `${top}px`, left: `${left}px` });
    };
    const show = () => {
        const step = usable[index];
        const last = index === usable.length - 1;
        card.innerHTML = `
            <span class="tour-card__count">${index + 1} of ${usable.length}</span>
            <h2 id="tour-title" class="tour-card__title"></h2>
            <p class="tour-card__body"></p>
            <div class="tour-card__actions">
                <button type="button" class="btn btn--ghost btn--xs" data-tour="skip">${last ? 'Close' : 'Skip tour'}</button>
                <span style="flex: 1"></span>
                ${index ? '<button type="button" class="btn btn--ghost btn--xs" data-tour="back">Back</button>' : ''}
                <button type="button" class="btn btn--navy btn--xs" data-tour="next">${last ? 'Done' : 'Next'}</button>
            </div>`;
        card.querySelector('.tour-card__title').textContent = step.title;
        card.querySelector('.tour-card__body').textContent = step.body;
        step.el.scrollIntoView({ block: 'center', behavior: 'instant' in document.documentElement.style ? 'instant' : 'auto' });
        requestAnimationFrame(() => {
            place();
            card.focus({ preventScroll: true });
        });
    };
    card.addEventListener('click', (e) => {
        const action = e.target.closest('[data-tour]')?.dataset.tour;
        if (action === 'skip') endTour(true);
        if (action === 'back' && index > 0) { index--; show(); }
        if (action === 'next') {
            if (index < usable.length - 1) { index++; show(); } else endTour(true);
        }
    });
    const keys = (e) => {
        if (e.key === 'Escape') endTour(true);
        if (e.key === 'ArrowRight') card.querySelector('[data-tour="next"]')?.click();
        if (e.key === 'ArrowLeft') card.querySelector('[data-tour="back"]')?.click();
    };
    tour = { spot, card, place, keys, returnFocus: document.activeElement };
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
    document.addEventListener('keydown', keys);
    show();
};

document.querySelectorAll('[data-tour-start]').forEach((b) => b.addEventListener('click', startTour));

if (steps.length) {
    const params = new URLSearchParams(location.search);
    if (params.get('tour') === '1') {
        setTimeout(startTour, 150);
    } else if (!store.get(tourKey) && fab) {
        // First time on this page: offer the tour without getting in the way.
        const prompt = document.createElement('div');
        prompt.className = 'tour-prompt';
        prompt.setAttribute('role', 'status');
        prompt.innerHTML = '<span>New to this page? Take a quick tour.</span><button type="button" class="btn btn--gold btn--xs" data-go>Show me</button><button type="button" class="tour-prompt__close" aria-label="Not now">×</button>';
        prompt.querySelector('[data-go]').addEventListener('click', () => { prompt.remove(); startTour(); });
        prompt.querySelector('.tour-prompt__close').addEventListener('click', () => {
            prompt.remove();
            store.set(tourKey, '1');
        });
        document.body.append(prompt);
    }
}

// ---------- Tooltips: placed on screen, beside their ⓘ ----------
for (const button of document.querySelectorAll('[data-tip]')) {
    const text = button.nextElementSibling;
    const fit = () => {
        text.classList.add('tip__text--placed');
        text.style.left = '0px';
        text.style.top = '0px';
        const b = button.getBoundingClientRect();
        const w = text.offsetWidth;
        const h = text.offsetHeight;
        const left = Math.min(Math.max(8, b.left - 10), window.innerWidth - w - 8);
        let top = b.top - h - 8;
        if (top < 8) top = b.bottom + 8;
        text.style.left = `${left}px`;
        text.style.top = `${top}px`;
    };
    button.addEventListener('mouseenter', fit);
    button.addEventListener('focus', fit);
    // Tapping on a phone toggles it; tapping elsewhere or Escape hides it.
    button.addEventListener('click', (e) => {
        e.preventDefault();
        button.classList.toggle('is-open');
        fit();
    });
    button.addEventListener('blur', () => button.classList.remove('is-open'));
    button.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { button.classList.remove('is-open'); button.blur(); }
    });
    window.addEventListener('scroll', () => button.classList.remove('is-open'), { passive: true });
}

// ---------- Help centre search ----------
const search = document.querySelector('[data-help-search]');
if (search) {
    const items = [...document.querySelectorAll('[data-help-item]')];
    const groups = [...document.querySelectorAll('[data-help-group]')];
    const empty = document.querySelector('[data-help-empty]');
    search.addEventListener('input', () => {
        const words = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;
        for (const item of items) {
            const group = item.closest('[data-help-group]');
            const text = `${item.textContent} ${group?.querySelector('h3')?.textContent ?? ''}`.toLowerCase();
            const match = words.every((w) => text.includes(w));
            item.hidden = !match;
            if (match) shown++;
            if (item.tagName === 'DETAILS') item.open = words.length > 0 && match;
        }
        for (const group of groups) group.hidden = !group.querySelector('[data-help-item]:not([hidden])');
        empty.hidden = shown > 0;
    });
    // Arriving from a page's Help panel: open that topic.
    if (location.hash) document.querySelector(`${CSS.escape ? '#' + CSS.escape(location.hash.slice(1)) : location.hash}`)?.scrollIntoView();
}
