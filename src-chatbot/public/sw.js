// Service Worker for PersonalOS PWA
// Minimal service worker - just enough to enable PWA installation

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

