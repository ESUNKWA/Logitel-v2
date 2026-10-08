/*
 * Guest room request form (/demande/chambre/{room}): live total and duration unit, +/− buttons,
 * no double submit. The server recomputes everything; this is only a preview.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-request-form]');
    if (!form) {
        return;
    }

    var duration = form.querySelector('#duration');
    var total = form.querySelector('[data-total]');
    var unitsLabel = form.querySelector('[data-units-label]');
    var modes = form.querySelectorAll('input[name="pricing_mode"]');

    var money = function (n) {
        return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    };

    var update = function () {
        var checked = form.querySelector('input[name="pricing_mode"]:checked');
        var qty = Math.max(1, Math.min(30, parseInt(duration.value, 10) || 1));

        if (!checked) {
            total.textContent = 'Choisissez une formule.';
            unitsLabel.textContent = '';
            return;
        }

        unitsLabel.textContent = '(' + checked.dataset.units + ')';
        total.textContent = '';
        total.appendChild(document.createTextNode('Total à régler à la réception : '));
        var strong = document.createElement('strong');
        strong.textContent = money(parseFloat(checked.dataset.rate) * qty);
        total.appendChild(strong);
    };

    modes.forEach(function (input) {
        input.addEventListener('change', update);
    });
    duration.addEventListener('input', update);

    form.querySelectorAll('[data-step]').forEach(function (button) {
        button.addEventListener('click', function () {
            var next = (parseInt(duration.value, 10) || 1) + parseInt(button.dataset.step, 10);
            duration.value = String(Math.max(1, Math.min(30, next)));
            update();
        });
    });

    form.addEventListener('submit', function () {
        var submit = form.querySelector('[data-submit]');
        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Envoi en cours…';
        }
    });

    update();
})();
