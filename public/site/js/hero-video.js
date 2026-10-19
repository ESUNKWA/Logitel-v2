/*
 * Home banner video capsules (site/partials/hero-video). Native, no dependency.
 *
 * - Nothing is downloaded before the page has loaded (preload="none"); then, if allowed, the
 *   first capsule plays and the others follow one after the other, one file at a time.
 * - Sound on by default (client request, 2026-10-19): playback is first tried WITH sound;
 *   browsers refuse that before any click/tap on the page, so it then plays muted + inline
 *   (accepted on iOS / Android), the sound button turns into a visible « Activer le son », and
 *   the sound comes on by itself at the visitor's first click, tap or key press anywhere on the
 *   page — unless they muted it themselves. Scrolling isn't a user activation for browsers.
 * - No autoplay when the visitor prefers reduced motion, has data saver on or a 2G connection,
 *   or when the browser refuses it: the poster stays with an explicit « play » button.
 * - Pauses when the banner leaves the screen or the tab is hidden; resumes on return unless
 *   the visitor paused it. Stops after MAX_ROUNDS full rounds (no endless loop).
 * - Controls: play / pause, sound, previous / next, one dot per capsule; status announced to
 *   assistive technologies.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-hero-video]');
    if (!root) {
        return;
    }

    var capsules = [];
    try {
        capsules = JSON.parse(root.getAttribute('data-capsules') || '[]');
    } catch (e) {
        capsules = [];
    }
    var video = root.querySelector('[data-hero-video-media]');
    if (!video || !capsules.length) {
        return;
    }

    var MAX_ROUNDS = 3;
    var controls = root.querySelector('[data-hero-video-controls]');
    var startButton = root.querySelector('[data-hero-video-start]');
    var toggleButton = root.querySelector('[data-action="toggle"]');
    var muteButton = root.querySelector('[data-action="mute"]');
    var status = root.querySelector('[data-hero-video-status]');
    var dots = Array.prototype.slice.call(root.querySelectorAll('.hero-video__dot'));

    var index = 0;
    var rounds = 0;
    var userPaused = false;   // the visitor pressed pause: never resume on their behalf
    var userMuted = false;    // the visitor cut the sound: never turn it back on for them
    var soundBlocked = false; // the browser refused sound: waiting for a first interaction
    var autoPaused = false;   // paused because off screen / tab hidden
    var visible = true;

    var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    var saveData = !!(connection && (connection.saveData || /(^|-)2g$/.test(connection.effectiveType || '')));
    var mayAutoplay = !reduceMotion && !saveData;

    // Custom controls replace the native ones (kept in the HTML for visitors without JavaScript).
    video.removeAttribute('controls');
    video.volume = 0.8;
    if (controls) {
        controls.hidden = false;
    }

    var setIcon = function (button, icon) {
        var i = button && button.querySelector('i');
        if (i) {
            i.className = 'fas ' + icon;
        }
    };

    var label = function (i) {
        return capsules[i].title + ' — ' + (i + 1) + ' sur ' + capsules.length;
    };

    var syncState = function () {
        var playing = !video.paused && !video.ended;
        if (toggleButton) {
            toggleButton.setAttribute('aria-label', playing ? 'Mettre en pause' : 'Lire la vidéo');
            setIcon(toggleButton, playing ? 'fa-pause' : 'fa-play');
        }
        if (muteButton) {
            muteButton.setAttribute('aria-label', video.muted ? 'Activer le son' : 'Couper le son');
            muteButton.setAttribute('aria-pressed', video.muted ? 'false' : 'true');
            setIcon(muteButton, video.muted ? 'fa-volume-mute' : 'fa-volume-up');
        }
        if (startButton) {
            startButton.hidden = playing;
        }
        root.classList.toggle('is-sound-blocked', soundBlocked && video.muted && !userMuted);
    };

    // First click / tap / key press anywhere on the page: the browser now accepts sound.
    var unlockEvents = ['pointerdown', 'touchend', 'keydown'];
    var stopWaiting = function () {
        unlockEvents.forEach(function (type) { document.removeEventListener(type, onFirstInteraction, true); });
    };
    var onFirstInteraction = function (event) {
        stopWaiting();
        // A press on the sound button itself is handled by that button (else it would turn the
        // sound on here, then straight back off as a toggle).
        if (event && event.target && event.target.closest && event.target.closest('[data-action="mute"]')) {
            return;
        }
        if (soundBlocked && !userMuted) {
            soundBlocked = false;
            video.muted = false;
        }
        syncState();
    };
    var waitForInteraction = function () {
        soundBlocked = true;
        unlockEvents.forEach(function (type) { document.addEventListener(type, onFirstInteraction, true); });
        syncState();
    };

    var showStart = function () {
        if (startButton) {
            startButton.hidden = false;
        }
        syncState();
    };

    var load = function (i) {
        index = (i + capsules.length) % capsules.length;
        var capsule = capsules[index];
        Array.prototype.slice.call(video.querySelectorAll('track')).forEach(function (t) { t.remove(); });
        if (capsule.captions) {
            var track = document.createElement('track');
            track.kind = 'captions';
            track.srclang = 'fr';
            track.label = 'Français';
            track.src = capsule.captions;
            track.default = true;
            video.appendChild(track);
        }
        if (capsule.poster) {
            video.setAttribute('poster', capsule.poster);
        } else {
            video.removeAttribute('poster');
        }
        video.setAttribute('aria-label', label(index));
        if (video.getAttribute('src') !== capsule.src) {
            video.setAttribute('src', capsule.src);
            video.load();
        }
        dots.forEach(function (dot, d) {
            if (d === index) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
        if (status) {
            status.textContent = label(index);
        }
    };

    var play = function () {
        video.preload = 'auto';
        // With sound unless the visitor muted it or the browser already refused it.
        video.muted = userMuted || soundBlocked;
        var attempt = video.play();
        if (!attempt || typeof attempt.catch !== 'function') {
            return;
        }
        attempt.catch(function () {
            if (video.muted) {
                showStart(); // even muted playback refused: explicit play button
                return;
            }
            // Sound refused before any interaction: play muted, offer the sound, unlock it on the first click.
            video.muted = true;
            waitForInteraction();
            var muted = video.play();
            if (muted && typeof muted.catch === 'function') {
                muted.catch(function () { showStart(); });
            }
        });
    };

    var goTo = function (i) {
        load(i);
        if (!userPaused) {
            play();
        } else {
            syncState();
        }
    };

    video.addEventListener('play', syncState);
    video.addEventListener('playing', syncState);
    video.addEventListener('pause', syncState);
    video.addEventListener('volumechange', syncState);
    video.addEventListener('error', function () {
        // A capsule that can't be read: skip it rather than show a broken player.
        if (capsules.length > 1) {
            goTo(index + 1);
        } else {
            showStart();
        }
    });
    video.addEventListener('ended', function () {
        if (index === capsules.length - 1) {
            rounds++;
        }
        if (rounds >= MAX_ROUNDS) {
            load(0);
            showStart(); // enough: the poster and the play button stay
            return;
        }
        goTo(index + 1);
    });

    if (startButton) {
        startButton.addEventListener('click', function () {
            userPaused = false;
            rounds = 0;
            play();
        });
    }
    if (toggleButton) {
        toggleButton.addEventListener('click', function () {
            if (video.paused || video.ended) {
                userPaused = false;
                rounds = 0;
                play();
            } else {
                userPaused = true;
                video.pause();
            }
        });
    }
    if (muteButton) {
        muteButton.addEventListener('click', function () {
            stopWaiting();
            // A click is a user gesture: sound is allowed from now on.
            var wantSound = video.muted;
            userMuted = !wantSound;
            soundBlocked = false;
            video.muted = !wantSound;
            if (wantSound && video.paused) {
                userPaused = false;
                play();
            }
            syncState();
        });
    }
    root.querySelectorAll('[data-action="prev"], [data-action="next"]').forEach(function (button) {
        button.addEventListener('click', function () {
            userPaused = false;
            goTo(index + (button.getAttribute('data-action') === 'next' ? 1 : -1));
        });
    });
    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            userPaused = false;
            goTo(parseInt(dot.getAttribute('data-index'), 10) || 0);
        });
    });

    // Off screen / tab hidden: pause, and resume on return unless the visitor paused it.
    var suspend = function () {
        if (!video.paused) {
            autoPaused = true;
            video.pause();
        }
    };
    var resume = function () {
        if (autoPaused && !userPaused && visible && !document.hidden) {
            autoPaused = false;
            play();
        }
    };
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;
            if (visible) {
                resume();
            } else {
                suspend();
            }
        }, { threshold: 0.25 }).observe(root.parentElement || root);
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            suspend();
        } else {
            resume();
        }
    });

    load(0);
    syncState();

    // Start once the page itself has loaded, so the video never competes with it.
    var begin = function () {
        if (!mayAutoplay) {
            showStart();
            return;
        }
        window.setTimeout(function () {
            if (visible && !document.hidden) {
                play();
            } else {
                autoPaused = true; // will start when the banner / tab comes back
            }
        }, 200);
    };
    if (document.readyState === 'complete') {
        begin();
    } else {
        window.addEventListener('load', begin);
    }
})();
