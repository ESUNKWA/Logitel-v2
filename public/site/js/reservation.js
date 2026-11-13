// « Réserver » (site/pages/reservation): shows the estimate for the chosen room type. The site
// offers the séjour only (PricingMode::WEBSITE): one hidden, checked formula; the other branches
// below are never reached there.
// Display only: the server recomputes everything (App\Enums\PricingMode).
(function () {
    'use strict';

    var form = document.querySelector('[data-booking]');
    if (!form) {
        return;
    }

    var rates = JSON.parse(form.dataset.rates || '{}');
    var nightFrom = form.dataset.nightFrom || '22:00';
    var nightUntil = form.dataset.nightUntil || '12:00';
    var dayHours = parseInt(form.dataset.dayHours, 10) || 6;
    var stayHours = parseInt(form.dataset.stayHours, 10) || 24;
    var checkIn = form.querySelector('[data-check-in]');
    var checkInLabel = form.querySelector('[data-check-in-label]');
    var duration = form.querySelector('[data-duration]');
    var unitsLabel = form.querySelector('[data-units-label]');

    var fcfa = function (n) {
        return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    };
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    var months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    var formatDate = function (d) {
        return days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()] + ' à ' + pad(d.getHours()) + 'h' + pad(d.getMinutes());
    };
    var atTime = function (date, hhmm) {
        var parts = hhmm.split(':');
        var d = new Date(date.getTime());
        d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0, 0);
        return d;
    };

    var checked = function (name) { return form.querySelector('[name="' + name + '"]:checked'); };

    var update = function () {
        var type = checked('room_type_id');
        var typeRates = type ? (rates[type.value] || {}) : {};
        // Séjour of a type ending the next day at a fixed time (data-stay-until « 12:00 »).
        var stayUntil = type ? (type.dataset.stayUntil || '') : '';
        var clock = stayUntil.slice(0, 5).replace(':', 'h');
        var unitOf = function (radio) {
            return radio.value === 'sejour' && stayUntil ? '/ séjour' : radio.dataset.unit;
        };

        // Formulas offered by this type; move off a formula it doesn't offer.
        form.querySelectorAll('[data-mode]').forEach(function (radio) {
            var rate = typeRates[radio.value];
            radio.disabled = rate === null || rate === undefined;
            radio.closest('label').classList.toggle('is-disabled', radio.disabled);
            radio.closest('label').querySelector('[data-mode-price]').textContent = radio.disabled ? 'non proposée' : fcfa(rate) + ' ' + unitOf(radio);
            var name = radio.closest('label').querySelector('span');
            if (name && name.firstChild && radio.dataset.label) {
                name.firstChild.textContent = radio.value === 'sejour' && stayUntil ? "Séjour (jusqu'à " + clock + ')' : radio.dataset.label;
            }
        });
        var mode = checked('pricing_mode');
        if (!mode || mode.disabled) {
            mode = form.querySelector('[data-mode]:not(:disabled)');
            if (mode) {
                mode.checked = true;
            }
        }
        if (!mode) {
            return;
        }

        // Arrival field: a date for a nuitée, a date and time otherwise (keeps the chosen day).
        var isNight = mode.value === 'nuitee';
        var wanted = isNight ? 'date' : 'datetime-local';
        if (checkIn.type !== wanted) {
            var day = (checkIn.value || '').slice(0, 10);
            checkIn.type = wanted;
            checkIn.value = day ? (isNight ? day : day + 'T10:00') : '';
        }
        checkInLabel.textContent = isNight ? 'Nuit du' : 'Arrivée le';
        var fixedStay = mode.value === 'sejour' && stayUntil;
        unitsLabel.textContent = fixedStay ? 'séjours (sortie à ' + clock + ')' : mode.dataset.units;

        var n = Math.max(1, parseInt(duration.value, 10) || 1);
        var rate = typeRates[mode.value] || 0;
        var start = checkIn.value ? new Date(isNight ? checkIn.value + 'T00:00' : checkIn.value) : null;
        var end = null;
        if (start && !isNaN(start)) {
            if (isNight) {
                start = atTime(start, nightFrom);
                end = atTime(new Date(start.getTime() + n * 86400000), nightUntil);
            } else if (fixedStay) {
                // That time N days after the arrival's date, whatever the arrival time.
                var day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + n);
                end = atTime(day, stayUntil);
            } else {
                var hours = mode.value === 'heure' ? n : (mode.value === 'journee' ? n * dayHours : n * stayHours);
                end = new Date(start.getTime() + hours * 3600000);
            }
        } else {
            start = null;
        }

        form.querySelector('[data-sum-type]').textContent = type ? type.closest('label').querySelector('strong').textContent : '—';
        form.querySelector('[data-sum-mode]').textContent = mode.closest('label').querySelector('span').firstChild.textContent.trim()
            + (rate ? ' — ' + n + ' × ' + fcfa(rate) + ' ' + unitOf(mode) : '');
        form.querySelector('[data-sum-start]').textContent = start ? formatDate(start) : '—';
        form.querySelector('[data-sum-end]').textContent = end ? formatDate(end) : '—';
        form.querySelector('[data-sum-total]').textContent = rate ? fcfa(rate * n) : '—';
    };

    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
