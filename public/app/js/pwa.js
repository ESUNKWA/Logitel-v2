// --- Installable application (PWA) ---
// meta[name="app-sw"] (application pages and login only, never the website) gives the service
// worker, registered with the application's scope. « Installer l'application » (topbar and login
// page) shows until the app runs installed: it opens the browser's install prompt when there is
// one, otherwise explains the steps for the browser in use (Chrome, Samsung Internet, Firefox,
// Safari on iPhone/Mac).
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

    // Chrome/Edge only fire `beforeinstallprompt` after a little use (and never when the app is
    // already installed or the browser can't install): the button is therefore shown anyway
    // and, without that event, explains how to install with the browser in use.
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferred = event;
    });
    window.addEventListener('appinstalled', function () {
        deferred = null;
        show(false);
    });
    if (!secure) {
        return;
    }
    show(true);

    var ua = navigator.userAgent;
    var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var steps = function () {
        if (ios) {
            return /crios|fxios|edgios/i.test(ua)
                ? "Sur iPhone et iPad, l'installation se fait depuis Safari :\n\n1. Ouvrez cette page dans Safari ;\n2. Touchez Partager (carré avec une flèche) ;\n3. Choisissez « Sur l'écran d'accueil », puis « Ajouter »."
                : "Pour installer l'application :\n\n1. Touchez le bouton Partager (carré avec une flèche) en bas de Safari ;\n2. Choisissez « Sur l'écran d'accueil » ;\n3. Touchez « Ajouter ».";
        }
        if (/samsungbrowser/i.test(ua)) {
            return "Dans Samsung Internet :\n\n1. Touchez le menu ☰ en bas à droite ;\n2. Choisissez « Ajouter page à » → « Écran d'accueil »\n   (ou « Installer » si proposé).";
        }
        if (/firefox/i.test(ua)) {
            return /android/i.test(ua)
                ? "Dans Firefox :\n\n1. Touchez le menu ⋮ ;\n2. Choisissez « Installer » ou « Ajouter à l'écran d'accueil »."
                : "Firefox sur ordinateur n'installe pas les applications web. Ouvrez cette page dans Chrome ou Edge pour l'installer.";
        }
        if (/android/i.test(ua)) {
            return "Dans Chrome :\n\n1. Touchez le menu ⋮ en haut à droite ;\n2. Choisissez « Installer l'application » (ou « Ajouter à l'écran d'accueil ») ;\n3. Confirmez.\n\nSi l'option n'apparaît pas, l'application est peut-être déjà installée : cherchez l'icône « HE » sur l'écran d'accueil.";
        }
        return "Dans Chrome ou Edge :\n\ncliquez sur l'icône d'installation à droite de la barre d'adresse (écran avec une flèche),\nou menu ⋮ → « Installer Hôtel Evasion… » / « Applications » → « Installer ce site en tant qu'application ».\n\nSur Safari (Mac) : menu Fichier → « Ajouter au Dock ».";
    };

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (deferred) {
                deferred.prompt();
                deferred.userChoice.then(function (choice) {
                    deferred = null;
                    if (choice.outcome === 'accepted') {
                        show(false);
                    }
                });
                return;
            }
            window.alert(steps());
        });
    });
})();
