const CACHE_PREFIX = 'zentrix-astra-static-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
    '/brand/zentrix-astra-mark.svg',
];
const BUILD_ASSET_PATH = /^\/build\/assets\/[A-Za-z0-9._-]+\.(?:css|js|woff2?|ttf|svg|png|webp)$/i;

function isSafeStaticAsset(url) {
    return url.origin === self.location.origin
        && (BUILD_ASSET_PATH.test(url.pathname) || PRECACHE_URLS.includes(url.pathname));
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(
                names
                    .filter((name) => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME)
                    .map((name) => caches.delete(name)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request, { cache: 'no-store' }).catch(() => caches.match('/offline.html').then(
                (response) => response || new Response('ZENTRIX ASTRA is offline.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                }),
            )),
        );

        return;
    }

    if (!isSafeStaticAsset(url)) {
        return;
    }

    event.respondWith(
        caches.match(request).then(async (cached) => {
            if (cached) {
                return cached;
            }

            const response = await fetch(request);
            if (response.ok && response.type === 'basic') {
                const cache = await caches.open(CACHE_NAME);
                await cache.put(request, response.clone());
            }

            return response;
        }),
    );
});
