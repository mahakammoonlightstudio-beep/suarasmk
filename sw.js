/* ============================================================
   Service Worker — Suara SMKN 1 Tenggarong (PWA)
   Strategi:
   - Halaman (navigasi) : network-first → cache → offline.html
   - Aset statis/CDN    : cache-first + update diam-diam di belakang
   CATATAN: kalau kamu update style.css/main.js, naikkan versi
   CACHE_NAME di bawah (mis. v1 → v2) agar pengunjung dapat file baru.
   ============================================================ */
var CACHE_NAME = 'suara-smk-v1';

var PRECACHE = [
    'index.php',
    'org.php',
    'assets/css/style.css',
    'assets/js/main.js',
    'assets/img/icon.svg',
    'assets/img/icon-maskable.svg',
    'manifest.json',
    'offline.html',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'
];

// ---- INSTALL: precache aset inti (satu per satu, anti gagal total) ----
self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return Promise.all(PRECACHE.map(function (url) {
                return cache.add(url).catch(function () { /* lewati yg gagal */ });
            }));
        }).then(function () { return self.skipWaiting(); })
    );
});

// ---- ACTIVATE: hapus cache versi lama ----
self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (k) {
                if (k !== CACHE_NAME) return caches.delete(k);
            }));
        }).then(function () { return self.clients.claim(); })
    );
});

// ---- FETCH ----
self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;

    // Navigasi halaman: coba network dulu (biar data rating selalu segar)
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).then(function (res) {
                var copy = res.clone();
                caches.open(CACHE_NAME).then(function (c) { c.put(req, copy); });
                return res;
            }).catch(function () {
                return caches.match(req).then(function (hit) {
                    return hit || caches.match('offline.html');
                });
            })
        );
        return;
    }

    // Aset statis & CDN: cepat dari cache, sambil perbarui di belakang
    e.respondWith(
        caches.match(req).then(function (hit) {
            var fetching = fetch(req).then(function (res) {
                if (res && (res.status === 200 || res.type === 'opaque')) {
                    var copy = res.clone();
                    caches.open(CACHE_NAME).then(function (c) { c.put(req, copy); });
                }
                return res;
            }).catch(function () { return hit; });
            return hit || fetching;
        })
    );
});
