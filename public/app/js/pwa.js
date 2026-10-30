// --- Installable application (PWA → Android APK) ---
// meta[name="app-sw"] (application pages and login only, never the website) gives the service
// worker, registered with the application's scope (offline page, installed app / APK). There is
// no « Installer l'application » button any more (client request, 2026-10-25): phones get the
// application through the APK.
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="app-sw"]');
    if (!meta || !('serviceWorker' in navigator)) {
        return;
    }
    var secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (secure) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(meta.content, { scope: meta.dataset.scope }).catch(function () { /* not installable here */ });
        });
    }
})();
