// Service worker de Rondo, généré au build (voir vite.config.js).
// Les fichiers de l'appli sont gardés sur l'appareil pour une ouverture instantanée ;
// les données (/api) passent toujours par le réseau et ne sont jamais mises en cache.
const VERSION = '__VERSION__'
const ASSETS = __ASSETS__
const CACHE = `rondo-${VERSION}`

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(['/', ...ASSETS])))
})

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('rondo-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()))
})

self.addEventListener('message', (e) => {
  if (e.data === 'SKIP_WAITING') self.skipWaiting()
})

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url)
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/')) return
  if (e.request.mode === 'navigate') {
    // réseau d'abord pour avoir la dernière page, coquille en cache si hors ligne
    e.respondWith(fetch(e.request).catch(() => caches.match('/')))
    return
  }
  e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request)))
})
