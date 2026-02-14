// Valírica Service Worker v1.0
const CACHE_NAME = 'valirica-v1';
const urlsToCache = [
  '/login_equipo.php',
  '/valirica-design-system.css',
  'https://use.typekit.net/qrv8fyz.css'
];

// Instalar el Service Worker
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Cache abierto');
        return cache.addAll(urlsToCache);
      })
      .catch(function(error) {
        console.log('Error al cachear:', error);
      })
  );
  // Activar inmediatamente
  self.skipWaiting();
});

// Activar el Service Worker
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.filter(function(cacheName) {
          return cacheName !== CACHE_NAME;
        }).map(function(cacheName) {
          return caches.delete(cacheName);
        })
      );
    })
  );
  // Tomar control inmediatamente
  self.clients.claim();
});

// Interceptar peticiones - Network First (para contenido dinámico PHP)
self.addEventListener('fetch', function(event) {
  event.respondWith(
    fetch(event.request)
      .then(function(response) {
        // Clonar la respuesta para el cache
        if (response && response.status === 200) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then(function(cache) {
              // Solo cachear GET requests
              if (event.request.method === 'GET') {
                cache.put(event.request, responseToCache);
              }
            });
        }
        return response;
      })
      .catch(function() {
        // Si falla la red, intentar desde cache
        return caches.match(event.request);
      })
  );
});