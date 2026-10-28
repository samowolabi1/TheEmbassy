// The installable app: registers the service worker, offers "Install the app",
// and turns notifications on or off ([data-push-toggle]).

const swReady = 'serviceWorker' in navigator
    ? navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => null)
    : Promise.resolve(null);

// Install button: Android and desktop Chrome/Edge offer a prompt; iPhones need Share › Add to Home Screen.
let installPrompt = null;
const installButtons = document.querySelectorAll('[data-install]');
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const iOS = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    installPrompt = e;
    installButtons.forEach((b) => (b.hidden = false));
});
window.addEventListener('appinstalled', () => {
    installPrompt = null;
    installButtons.forEach((b) => (b.hidden = true));
});

for (const button of installButtons) {
    if (standalone) continue;
    if (iOS) {
        button.hidden = false;
    }
    button.addEventListener('click', async () => {
        if (installPrompt) {
            installPrompt.prompt();
            await installPrompt.userChoice.catch(() => null);
            installPrompt = null;
            button.hidden = true;
            return;
        }
        const hint = document.getElementById(button.getAttribute('aria-controls'));
        if (hint) hint.hidden = !hint.hidden;
    });
}
if (standalone) {
    document.querySelectorAll('[data-installed]').forEach((el) => (el.hidden = false));
}

// Notifications.
const key = document.querySelector('meta[name="push-key"]')?.content;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const pushSupported = key && 'PushManager' in window && 'Notification' in window && 'serviceWorker' in navigator;

const toBytes = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
};

const post = (url, body) =>
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

for (const toggle of document.querySelectorAll('[data-push-toggle]')) {
    const status = toggle.parentElement.querySelector('[data-push-status]');
    const say = (text) => status && (status.textContent = text);

    if (!pushSupported) {
        if (iOS && !standalone && key) {
            toggle.hidden = true;
            say('On iPhone, add the app to your home screen first, then turn notifications on from there.');
        } else {
            toggle.hidden = true;
        }
        continue;
    }
    toggle.hidden = false;

    const render = async () => {
        const reg = await swReady;
        const sub = reg ? await reg.pushManager.getSubscription() : null;
        const on = Boolean(sub) && Notification.permission === 'granted';
        toggle.textContent = on ? toggle.dataset.offLabel : toggle.dataset.onLabel;
        toggle.setAttribute('aria-pressed', String(on));
        if (Notification.permission === 'denied') {
            say('Notifications are blocked for this site. Allow them in your browser settings.');
        } else {
            say(on ? 'Notifications are on for this device.' : '');
        }
        return sub;
    };

    toggle.addEventListener('click', async () => {
        toggle.disabled = true;
        try {
            const reg = await swReady;
            const existing = reg && (await reg.pushManager.getSubscription());
            if (existing && Notification.permission === 'granted') {
                await post('/push/unsubscribe', { endpoint: existing.endpoint });
                await existing.unsubscribe();
            } else if (reg && (await Notification.requestPermission()) === 'granted') {
                const sub = existing || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toBytes(key) }));
                const json = sub.toJSON();
                const encoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
                const res = await post('/push/subscribe', { ...json, contentEncoding: encoding });
                if (!res.ok) throw new Error('save failed');
            }
        } catch {
            say('That didn\'t work. Try again in a moment.');
            toggle.disabled = false;
            return;
        }
        toggle.disabled = false;
        render();
    });

    render();
}
