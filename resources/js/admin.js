// Admin enhancements; every form still works without them.
import './check-in';
import './share';
import './editor';
import './gallery-admin';
import './studio';
import './birthday-design';
import './fields';
import './help';

// Ask before destructive actions: <form data-confirm="Delete this?">.
for (const form of document.querySelectorAll('form[data-confirm]')) {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
}

// Live phone preview of an announcement while it's being written.
for (const [field, target] of [['title', 'preview-title'], ['body', 'preview-body']]) {
    const input = document.querySelector(`[data-preview-source="${field}"]`);
    const out = document.getElementById(target);
    if (!input || !out) continue;
    const fallback = out.textContent;
    const sync = () => (out.textContent = input.value.trim() || fallback);
    input.addEventListener('input', sync);
    sync();
}

// Only show the date picker when "Schedule" is chosen.
const scheduleAt = document.querySelector('[data-schedule-at]');
if (scheduleAt) {
    const radios = document.querySelectorAll('input[name="when"]');
    const sync = () => (scheduleAt.hidden = document.querySelector('input[name="when"]:checked')?.value !== 'schedule');
    radios.forEach((r) => r.addEventListener('change', sync));
    sync();
}

// Seller listing form: stock for products, price unit for services.
const typeRadios = document.querySelectorAll('[data-listing-type]');
if (typeRadios.length) {
    const sync = () => {
        const type = document.querySelector('[data-listing-type]:checked')?.value;
        for (const el of document.querySelectorAll('[data-for-type]')) el.hidden = el.dataset.forType !== type;
    };
    typeRadios.forEach((r) => r.addEventListener('change', sync));
    sync();
}

// Reminders & greetings: show how each message reads as it's typed, and label the on/off switch.
for (const area of document.querySelectorAll('[data-reminder-text]')) {
    const out = area.closest('form').querySelector('[data-reminder-preview]');
    const sample = JSON.parse(area.dataset.sample || '{}');
    area.addEventListener('input', () => {
        out.textContent = area.value.replace(/\{(\w+)\}/g, (m, key) => (key in sample ? sample[key] : m)).trim();
    });
}
for (const toggle of document.querySelectorAll('.switch input')) {
    const label = toggle.nextElementSibling;
    toggle.addEventListener('change', () => (label.textContent = toggle.checked ? 'On' : 'Off'));
}

// Sidebar: hide it on computers (remembered), open and close it as a menu on phones, and fold
// its groups (remembered; the group with the current page stays open).
{
    const root = document.documentElement;
    const side = document.querySelector('.side');
    const store = (key, value) => {
        try {
            if (value === undefined) return localStorage.getItem(key);
            localStorage.setItem(key, value);
        } catch { /* private browsing */ }
        return null;
    };
    document.querySelector('[data-side-collapse]')?.addEventListener('click', () => {
        root.classList.add('side-collapsed');
        store('side-collapsed', '1');
        document.querySelector('[data-side-expand]')?.focus();
    });
    document.querySelector('[data-side-expand]')?.addEventListener('click', () => {
        root.classList.remove('side-collapsed');
        store('side-collapsed', '0');
        document.querySelector('[data-side-collapse]')?.focus();
    });
    const menu = document.querySelector('[data-side-menu]');
    menu?.addEventListener('click', () => {
        const open = side.classList.toggle('is-open');
        menu.setAttribute('aria-expanded', String(open));
        menu.setAttribute('aria-label', open ? 'Close the menu' : 'Open the menu');
    });

    let folded = [];
    try { folded = JSON.parse(store('side-folded') || '[]'); } catch { folded = []; }
    for (const group of document.querySelectorAll('[data-side-group]')) {
        const button = group.querySelector('[data-side-fold]');
        const key = group.dataset.sideGroup;
        const set = (closed) => {
            group.classList.toggle('is-folded', closed);
            button.setAttribute('aria-expanded', String(!closed));
        };
        set(folded.includes(key) && !('current' in group.dataset));
        button.addEventListener('click', () => {
            const closed = !group.classList.contains('is-folded');
            set(closed);
            folded = closed ? [...new Set([...folded, key])] : folded.filter((k) => k !== key);
            store('side-folded', JSON.stringify(folded));
        });
    }
}

// Message centre: show only the choices for who it's for, and count the people as they change.
const outreach = document.querySelector('[data-outreach-form]');
if (outreach) {
    const reach = outreach.querySelector('[data-reach]');
    const syncSections = () => {
        const type = outreach.querySelector('[data-audience-type]:checked')?.value;
        for (const el of outreach.querySelectorAll('[data-for-audience]')) el.hidden = el.dataset.forAudience !== type;
    };
    let timer;
    const count = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const params = new URLSearchParams();
            for (const [k, v] of new FormData(outreach)) {
                const m = k.match(/^audience\[(\w+)\]$/);
                if (m) params.set(m[1], v);
            }
            reach.classList.add('is-loading');
            try {
                const r = await fetch(`${outreach.dataset.reachUrl}?${params}`, { headers: { Accept: 'application/json' } });
                const d = await r.json();
                reach.querySelector('[data-reach-people]').textContent = d.people.toLocaleString();
                reach.querySelector('[data-reach-email]').textContent = d.email.toLocaleString();
                reach.querySelector('[data-reach-whatsapp]').textContent = d.whatsapp.toLocaleString();
                reach.querySelector('[data-reach-app]').textContent = d.app.toLocaleString();
            } catch { /* the count updates on the next change */ }
            reach.classList.remove('is-loading');
        }, 250);
    };
    outreach.addEventListener('change', (e) => {
        if (e.target.name?.startsWith('audience')) {
            syncSections();
            count();
        }
    });
    outreach.querySelector('[data-confirm-send]')?.addEventListener('click', (e) => {
        const people = reach.querySelector('[data-reach-people]').textContent;
        if (!window.confirm(`Send this to ${people} people now?`)) e.preventDefault();
    });
    syncSections();
}


// Online shop editor: show the chosen banner, logo, colour and tagline before saving.
(() => {
    const preview = document.querySelector('[data-storefront-preview]');
    if (!preview) return;
    document.querySelectorAll('[data-preview-input]').forEach((input) => input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        const target = preview.querySelector(input.dataset.previewInput === 'banner' ? '[data-preview-banner]' : '[data-preview-logo]');
        target.style.backgroundImage = `url('${URL.createObjectURL(file)}')`;
        if (input.dataset.previewInput === 'logo') target.textContent = '';
    }));
    document.querySelectorAll('[data-accent]').forEach((radio) => radio.addEventListener('change', () => {
        const [main, soft] = radio.dataset.accent.split('|');
        preview.style.setProperty('--shop', main);
        preview.style.setProperty('--shop-soft', soft);
    }));
    const tagline = document.querySelector('[data-preview-text="tagline"]');
    tagline?.addEventListener('input', () => {
        preview.querySelector('[data-preview-tagline]').textContent = tagline.value || tagline.dataset.fallback;
    });
})();

// Product photos: preview the ones chosen before uploading, and say how many more fit.
(() => {
    const input = document.querySelector('[data-photo-input]');
    if (!input) return;
    const list = document.querySelector('[data-photo-new]');
    const max = Number(input.dataset.max || 8);
    const note = document.querySelector('[data-photo-note]');
    input.addEventListener('change', () => {
        list.replaceChildren();
        const kept = document.querySelectorAll('[data-photo-kept]:not(:has(input[type=checkbox]:checked))').length;
        [...input.files].forEach((file, i) => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = '';
            img.className = 'photo-tile__img' + (kept + i >= max ? ' is-over' : '');
            const tile = document.createElement('span');
            tile.className = 'photo-tile';
            tile.append(img);
            list.append(tile);
        });
        if (note) {
            const over = kept + input.files.length - max;
            note.textContent = over > 0 ? `Only ${max} photos fit: the last ${over} won't be added.` : '';
        }
    });
})();
