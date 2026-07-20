/* AK Menu System - basic offline cache service worker. */
const CACHE = 'akmenu-v1';
self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(clients.claim()); });
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.open(CACHE).then(cache =>
      cache.match(e.request).then(hit => {
        const net = fetch(e.request).then(res => { cache.put(e.request, res.clone()); return res; }).catch(() => hit);
        return hit || net;
      })
    )
  );
});
