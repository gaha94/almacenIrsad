const CACHE_NAME = 'almacen-pwa-v1';

const STATIC_ASSETS = [
    '/',
    '/login.php',
    '/offline.html',
    '/manifest.json',
    '/assets/img/icon-192.png',
    '/assets/img/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .catch(() => null)
    );

    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys
                    .filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            );
        })
    );

    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(request)
            .then(response => {
                return response;
            })
            .catch(() => {
                if (request.mode === 'navigate') {
                    return caches.match('/offline.html');
                }

                return caches.match(request);
            })
    );
});