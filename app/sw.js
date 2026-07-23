/* Master Staff App — service worker.
   Network-first so live order/service data is never stale; API + non-GET
   requests always hit the network; the login shell falls back to cache offline. */
const CACHE = 'akstaff-v1';

self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(clients.claim()); });

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  // Never cache APIs or non-GET traffic — staff screens must stay live.
  if (e.request.method !== 'GET' || url.pathname.indexOf('/api/') !== -1) return;
  e.respondWith(
    fetch(e.request)
      .then(res => {
        if (res && res.status === 200 && res.type === 'basic') {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});
