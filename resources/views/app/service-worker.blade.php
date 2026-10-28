{{-- The app's service worker: an offline page, cached build files, and notifications. --}}
const VERSION = @json($version);
const CACHE = `tribe-${VERSION}`;
const OFFLINE = @json($precache[0]);
const PRECACHE = @json($precache);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('tribe-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== location.origin) return;

    // Pages always come from the network (they can be personal); the offline page stands in when there is none.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
        return;
    }

    // Built CSS and JS have the version in their name, so a cached copy is always right.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }
    event.waitUntil(
        self.registration.showNotification(data.title || @json(config('church.short_name')), {
            body: data.body || '',
            icon: data.icon,
            badge: data.icon,
            tag: data.tag || undefined,
            data: { url: data.url || '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    let target = new URL(event.notification.data?.url || '/', location.origin);
    if (target.origin !== location.origin) target = new URL('/', location.origin);
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const win of windows) {
                if (win.url === target.href && 'focus' in win) return win.focus();
            }
            return self.clients.openWindow(target.href);
        }),
    );
});
