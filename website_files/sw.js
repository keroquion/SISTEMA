// Service Worker - Petulap PWA v21 (Historial Ejecutivo de Tickets Entregados y Finalizados Multiorigen)
 
var CACHE_NAME = 'petulap-v21';
var ASSETS = [
    './',
    'index.html',
    'login.html',
    'css/tokens.css',
    'css/styles.css',
    'css/dashboard.css',
    'css/missing.css',
    'icon-192.png',
    'icon-512.png',
    'manifest.json'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(ASSETS).catch(function(err) { console.log('Error caching assets', err); });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.map(function(key) {
                    if (key !== CACHE_NAME) return caches.delete(key);
                })
            );
        })
    );
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', function(event) {
    // Network-first for API calls
    if (event.request.url.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }
    
    // Network-first with cache fallback for everything else
    event.respondWith(
        fetch(event.request)
            .then(function(response) {
                // Clone and cache successful responses
                if (response.ok) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            })
            .catch(function() {
                return caches.match(event.request).then(function(response) {
                    if (response) return response;
                    if (event.request.mode === 'navigate') {
                        return caches.match('index.html');
                    }
                });
            })
    );
});

// ============================================================
// PUSH NOTIFICATIONS
// ============================================================

self.addEventListener('push', function(event) {
    var data = {};
    if (event.data) {
        try { data = event.data.json(); } catch(e) { data = { body: event.data.text() }; }
    }
    
    var title = data.title || 'Petulap SST';
    var options = {
        body: data.body || 'Nueva notificacion',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        data: { url: data.url || '/index.html' },
        vibrate: [200, 100, 200],
        requireInteraction: true,
        tag: data.tag || 'petulap-' + Date.now()
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    var targetUrl = event.notification.data && event.notification.data.url 
                    ? event.notification.data.url 
                    : '/index.html';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            // If there's already a window open, focus it and navigate
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    client.focus();
                    if (client.url.indexOf(targetUrl) === -1) {
                        client.navigate(targetUrl);
                    }
                    return;
                }
            }
            // Otherwise open a new window
            return clients.openWindow(targetUrl);
        })
    );
});
