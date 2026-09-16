const CACHE_NAME = 'recovery-admin-v3'
const PRECACHE_URLS = [
  './',
  './index.html',
  './manifest.webmanifest',
  './icon-192.png',
  './icon-512.png',
  './maskable-512.png',
  './apple-touch-icon.png'
]

// Detectar la página de desafío anti-bot de InfinityFree para nunca cachearla.
function isChallengeHtml(response) {
  const ct = response.headers.get('content-type') || ''
  if (!ct.includes('text/html')) return Promise.resolve(false)
  return response
    .clone()
    .text()
    .then((text) => text.includes('aes.js') || text.includes('__test'))
    .catch(() => false)
}

function cachePut(request, response) {
  caches.open(CACHE_NAME).then((cache) => cache.put(request, response)).catch(() => {})
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
      .then(() => self.clients.matchAll({ includeUncontrolled: true, type: 'window' }))
      .then((clients) => {
        clients.forEach((client) => {
          client.navigate(client.url).catch(() => {})
        })
      })
  )
})

self.addEventListener('fetch', (event) => {
  const { request } = event
  if (request.method !== 'GET') return

  const url = new URL(request.url)
  if (url.pathname.includes('/api/')) return

  // Navegaciones (HTML): siempre red para no servir versiones viejas.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            isChallengeHtml(response).then((challenge) => {
              if (!challenge) cachePut(request, response.clone())
            })
          }
          return response
        })
        .catch(() =>
          caches.match(request).then((cached) => cached || caches.match('./index.html'))
        )
    )
    return
  }

  // Assets: cache-first con actualización en segundo plano.
  event.respondWith(
    caches.match(request).then((cached) => {
      const fetched = fetch(request)
        .then((response) => {
          if (response && response.status === 200 && response.type === 'basic') {
            isChallengeHtml(response).then((challenge) => {
              if (!challenge) cachePut(request, response.clone())
            })
          }
          return response
        })
        .catch(() => cached)
      return cached || fetched
    })
  )
})