// Minimal service worker: lets the phone install the app. It keeps nothing in memory (every page and
// every answer comes fresh from the server, so a shop never sees old chats). Notifications will be added here later.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
