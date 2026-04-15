/**
 * Service Worker for Imposter Game PWA
 * Caches static assets and provides offline fallback
 */

const CACHE_NAME = 'imposter-game-v2';
const OFFLINE_URL = 'index.php';

// Static assets to pre-cache on install (no PHP pages – they are always network-fetched)
const PRECACHE_ASSETS = [
  'manifest.json',
  'assets/css/style.css',
  'assets/js/app.js',
  'data/words.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://code.jquery.com/jquery-3.7.1.min.js'
];

// Install event – pre-cache assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate event – clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  const isPhpPage = url.pathname.endsWith('.php') || url.pathname === '/';

  // PHP pages: always network-first (session-dependent, never serve from cache)
  if (isPhpPage || event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        // Only fall back to cached index as last resort when truly offline
        return caches.match('index.php');
      })
    );
    return;
  }

  // Static assets: cache-first with network fallback
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const cloned = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, cloned);
          });
        }
        return networkResponse;
      });
    })
  );
});
