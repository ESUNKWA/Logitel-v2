// --- Installable application (PWA) ---
// meta[name="app-sw"] (application pages and login only, never the website) gives the service
// worker, registered with the application's scope. « Installer l'application » appears when the
// browser offers installation (Android/Chrome/Edge), or on iPhone/iPad (Safari has no prompt:
// the button explains « Partager → Sur l'écran d'accueil »); hidden once installed.
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

    var buttons = document.querySelectorAll('[data-pwa-install]');
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (!buttons.length || standalone) {
        return;
    }

    var deferred = null;
    var show = function (visible) {
        buttons.forEach(function (button) { button.hidden = !visible; });
    };

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferred = event;
        show(true);
    });
    window.addEventListener('appinstalled', function () {
        deferred = null;
        show(false);
    });

    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (ios && secure) {
        show(true);
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (deferred) {
                deferred.prompt();
                deferred.userChoice.then(function () {
                    deferred = null;
                    show(false);
                });
                return;
            }
            if (ios) {
                window.alert("Pour installer l'application sur iPhone ou iPad :\n\n1. Touchez le bouton Partager (carré avec une flèche) en bas de Safari ;\n2. Choisissez « Sur l'écran d'accueil » ;\n3. Touchez « Ajouter ».");
            }
        });
    });
})();
