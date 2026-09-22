/* ============================================================
   PWA & UX — Suara SMKN 1 Tenggarong
   Service worker, tombol install, indikator offline, toast,
   share, scroll-to-top, haptic feedback.
   ============================================================ */
(function () {
    'use strict';

    // ---- 1. Registrasi Service Worker (inti PWA) ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        });
    }

    // ---- 2. Toast notification global ----
    function toast(message, type) {
        var stack = document.getElementById('toastStack');
        if (!stack) return;
        var el = document.createElement('div');
        el.className = 'toast-item toast-' + (type || 'info');
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(function () { el.classList.add('show'); }, 10);
        setTimeout(function () {
            el.classList.remove('show');
            setTimeout(function () { el.remove(); }, 300);
        }, 3500);
    }
    window.showToast = toast;

    // ---- 3. Tombol Install App (muncul saat browser mengizinkan) ----
    var deferredPrompt = null;
    var installBtn = document.getElementById('installBtn');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn) installBtn.classList.add('show');
    });

    if (installBtn) {
        installBtn.addEventListener('click', function () {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function () {
                deferredPrompt = null;
                installBtn.classList.remove('show');
            });
        });
    }

    window.addEventListener('appinstalled', function () {
        if (installBtn) installBtn.classList.remove('show');
        toast('🎉 App terpasang! Buka dari home screen ya.', 'success');
    });

    // ---- 4. Indikator offline / online ----
    var offlineEl = document.getElementById('offlineIndicator');
    function syncOnline() {
        if (offlineEl) offlineEl.classList.toggle('show', !navigator.onLine);
    }
    window.addEventListener('online', function () {
        syncOnline();
        toast('✅ Kembali online!', 'success');
    });
    window.addEventListener('offline', function () {
        syncOnline();
        toast('📡 Kamu offline — halaman terbuka tetap bisa dilihat.', 'warning');
    });
    syncOnline();

    // ---- 5. Tombol "Kembali ke atas" ----
    var scrollTop = document.getElementById('scrollTop');
    if (scrollTop) {
        window.addEventListener('scroll', function () {
            scrollTop.classList.toggle('show', window.scrollY > 400);
        }, { passive: true });
        scrollTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ---- 6. Tombol Bagikan (Web Share API + fallback copy link) ----
    var shareBtn = document.getElementById('shareBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function () {
            var data = {
                title: document.title,
                text: 'Yuk beri penilaian jujur untuk organisasi sekolah kita — anonim! 📢',
                url: location.origin + location.pathname
            };
            if (navigator.share) {
                navigator.share(data).catch(function () { /* user membatalkan */ });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(data.url).then(function () {
                    toast('🔗 Link berhasil dikopi!', 'success');
                }).catch(function () {
                    toast('Tidak bisa mengopi link.', 'warning');
                });
            }
        });
    }

    // ---- 7. Haptic feedback (getar halus di HP saat tekan tombol utama) ----
    if (navigator.vibrate) {
        document.querySelectorAll('.btn-rate, .star-pick').forEach(function (el) {
            el.addEventListener('click', function () { navigator.vibrate(8); });
        });
    }
})();
