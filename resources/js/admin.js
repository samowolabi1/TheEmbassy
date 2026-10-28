// Admin enhancements; every form still works without them.
import './check-in';
import './share';

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
