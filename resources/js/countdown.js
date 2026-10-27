// Keeps the Events page countdown current between page loads.
const el = document.querySelector('[data-countdown]');

if (el) {
    const ms = Date.parse(el.dataset.countdown) - Date.now();
    el.textContent = String(Math.max(0, Math.ceil(ms / 86_400_000)));
}
