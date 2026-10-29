// Online only: no cache storage, inventory data, photos or write queue.
const offlinePage = [
    '<!doctype html><html lang="en">',
    '<meta name="viewport" content="width=device-width,initial-scale=1">',
    '<title>No connection</title>',
    '<body style="font:18px system-ui;padding:32px;background:#f5f6f2;color:#243830">',
    '<h1>No connection</h1>',
    '<p>Connect to your home network and check that the server is running.</p>',
    '<p>SoloInventory requires a connection. Changes cannot be saved offline.</p>',
    '<a href="/">Retry</a></body></html>',
].join('');

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }
    event.respondWith(
        fetch(event.request).catch(
            () =>
                new Response(
                    offlinePage,
                    {
                        status: 503,
                        headers: {
                            'Content-Type': 'text/html; charset=utf-8',
                            'Cache-Control': 'no-store',
                        },
                    },
                ),
        ),
    );
});
