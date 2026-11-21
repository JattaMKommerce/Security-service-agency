/* Guard app service worker: makes the app installable and loads the shell
   when the network is flaky. API calls always go to the network. */
const CACHE = 'guard-shell-v1';

self.addEventListener('install', (e) => { self.skipWaiting(); });
self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.pathname.includes('/api/')) return;               // live data: never cache
    const isPage = req.mode === 'navigate';
    const isStatic = /\.(css|js|png|svg|woff2?)$/.test(url.pathname) || url.hostname.includes('jsdelivr') || url.hostname.includes('gstatic') || url.hostname.includes('googleapis');
    if (!isPage && !isStatic) return;
    // Network first, fall back to cache when offline.
    e.respondWith(fetch(req).then((res) => {
        if (res.ok && (isStatic || isPage)) {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
    }).catch(() => caches.match(req)));
});
