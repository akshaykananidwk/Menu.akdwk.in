/* Restaurant owner panel — service worker (makes the panel installable).
   Pass-through only: panel pages are private and live, so nothing is cached.
   When the internet is down, page loads show a small offline notice. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET' || e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(() => new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<title>Offline</title><body style="font-family:system-ui;text-align:center;padding:60px 20px">' +
    '<h2>No internet connection</h2><p>Please check your internet and try again.</p>' +
    '<button onclick="location.reload()" style="padding:10px 18px;font-size:16px">Retry</button></body>',
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
});
