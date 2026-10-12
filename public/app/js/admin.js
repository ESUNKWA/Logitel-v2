(function () {
    'use strict';

    // Sidebar toggle (mobile)
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.admin-sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }

    // --- Stay pricing helpers (mirror App\Enums\PricingMode; the server recomputes everything) ---
    var RATE_KEYS = { heure: 'hourlyRate', journee: 'dayRate', nuitee: 'nightRate', sejour: 'stayRate' };

    var pad = function (n) { return (n < 10 ? '0' : '') + n; };

    var formatDateTime = function (d) {
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' à ' + pad(d.getHours()) + 'h' + pad(d.getMinutes());
    };

    var formatAmount = function (n) {
        return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    };

    // --- Money inputs ([data-money], FCFA, whole numbers): thousands separators while typing
    // ("150 000"). Always read them with moneyValue() and write them with setMoney(); the server
    // strips the spaces (App\Http\Middleware\NormalizeMoneyInputs).
    var groupDigits = function (digits) { return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };

    var moneyDigits = function (value) {
        return String(value).replace(/[.,]\d{1,2}$/, '').replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    };

    var moneyValue = function (input) {
        var digits = moneyDigits(input.value);
        return digits === '' ? 0 : parseInt(digits, 10);
    };

    var setMoney = function (input, n) {
        input.value = n === '' || n === null || n === undefined || isNaN(n) ? '' : groupDigits(String(Math.round(n)));
    };

    var formatMoneyInput = function (input) {
        var caret = input.selectionStart === null ? input.value.length : input.selectionStart;
        var digitsBefore = input.value.slice(0, caret).replace(/\D/g, '').length;
        var formatted = groupDigits(moneyDigits(input.value));
        if (formatted === input.value) {
            return;
        }
        input.value = formatted;
        if (document.activeElement === input) {
            var pos = 0;
            for (var seen = 0; pos < formatted.length && seen < digitsBefore; pos++) {
                if (/\d/.test(formatted.charAt(pos))) {
                    seen++;
                }
            }
            input.setSelectionRange(pos, pos);
        }
    };

    // Capture phase: the value is formatted before any other "input" handler reads it.
    document.addEventListener('input', function (event) {
        if (event.target.matches && event.target.matches('[data-money]')) {
            formatMoneyInput(event.target);
        }
    }, true);
    document.querySelectorAll('[data-money]').forEach(formatMoneyInput);

    var unitLabels = function (mode, config) {
        return {
            heure: { duration: "Nombre d'heures", rate: '/ heure' },
            journee: { duration: 'Journées de ' + config.dayHours + ' h', rate: '/ journée' },
            nuitee: { duration: 'Nombre de nuits', rate: '/ nuit' },
            sejour: { duration: 'Périodes de ' + config.stayHours + ' h', rate: '/ ' + config.stayHours + ' h' },
        }[mode];
    };

    // Departure for an arrival "now" (affectation), same rules as PricingMode::end().
    var departureFor = function (mode, start, duration, config) {
        var end = new Date(start.getTime());
        if (mode === 'heure') {
            end.setHours(end.getHours() + duration);
        } else if (mode === 'journee') {
            end.setHours(end.getHours() + duration * config.dayHours);
        } else if (mode === 'sejour') {
            end.setHours(end.getHours() + duration * config.stayHours);
        } else {
            var until = config.nightUntil.split(':');
            var beforeNoon = pad(start.getHours()) + ':' + pad(start.getMinutes()) < config.nightUntil;
            end.setHours(0, 0, 0, 0);
            end.setDate(end.getDate() + (beforeNoon ? duration - 1 : duration));
            end.setHours(parseInt(until[0], 10), parseInt(until[1], 10));
        }
        return end;
    };

    // --- Discount ("remise", [data-discount]): amount in FCFA or percentage of the subtotal.
    // Same rule as Reservation::discountFor(): capped at the subtotal, percentage rounded to the franc.
    var discountAmount = function (scope, subtotal) {
        var box = scope ? scope.querySelector('[data-discount]') : null;
        if (!box || subtotal <= 0) {
            return 0;
        }
        var value = moneyValue(box.querySelector('[data-discount-value]'));
        var amount = box.querySelector('[data-discount-type]').value === 'pourcentage'
            ? Math.round(subtotal * Math.min(value, 100) / 100)
            : value;
        return Math.max(0, Math.min(amount, subtotal));
    };

    // The server refuses these (typo protection); the preview says so instead of showing 0 FCFA.
    var discountTooHigh = function (scope, subtotal) {
        var box = scope ? scope.querySelector('[data-discount]') : null;
        if (!box) {
            return false;
        }
        var value = moneyValue(box.querySelector('[data-discount-value]'));
        return box.querySelector('[data-discount-type]').value === 'pourcentage' ? value > 100 : value > subtotal;
    };

    // "Total : 15 000 FCFA", or "Sous-total 15 000 FCFA − remise 1 500 FCFA = Total : 13 500 FCFA".
    var totalText = function (subtotal, discount, tooHigh) {
        if (tooHigh) {
            return 'Total : ' + formatAmount(subtotal) + ' — ⚠ la remise dépasse le montant du séjour';
        }
        return (discount > 0 ? 'Sous-total ' + formatAmount(subtotal) + ' − remise ' + formatAmount(discount) + ' = ' : '')
            + 'Total : ' + formatAmount(subtotal - discount);
    };

    var bindDiscount = function (scope, refresh) {
        var box = scope ? scope.querySelector('[data-discount]') : null;
        if (box) {
            box.querySelector('[data-discount-value]').addEventListener('input', refresh);
            box.querySelector('[data-discount-type]').addEventListener('change', refresh);
        }
    };

    // --- Room assignment modal (room board) ---
    // Each clickable room card carries data-assign-url / data-room-number / data-room-type and
    // data-hourly-rate / data-night-rate / data-stay-rate (empty = mode not offered for the type).
    // The same stay form also lives outside a modal, on the page validating a guest's request
    // ([data-stay-standalone], which carries the room type's rates like a room card does).
    var assignModal = document.getElementById('assignRoomModal');
    var stayScope = assignModal || document.querySelector('[data-stay-standalone]');
    var stayForm = stayScope ? stayScope.querySelector('[data-stay-form]') : null;
    var currentCard = assignModal ? null : stayScope;

    var refreshStayPreview = function () {
        if (!stayForm) {
            return;
        }
        var config = {
            nightUntil: stayForm.dataset.nightUntil,
            stayHours: parseInt(stayForm.dataset.stayHours, 10),
            dayHours: parseInt(stayForm.dataset.dayHours, 10),
        };
        var checked = stayForm.querySelector('[data-mode-option]:checked');
        var durationInput = stayForm.querySelector('[data-duration-input]');
        var rateInput = stayForm.querySelector('[data-unit-rate-input]');
        var preview = stayForm.querySelector('[data-stay-preview]');

        if (!checked) {
            preview.textContent = 'Choisissez un mode.';
            return;
        }

        var labels = unitLabels(checked.value, config);
        stayForm.querySelector('[data-duration-label]').textContent = labels.duration;
        stayForm.querySelector('[data-rate-unit]').textContent = labels.rate;

        var duration = Math.max(1, parseInt(durationInput.value, 10) || 1);
        var rate = moneyValue(rateInput);
        var departure = departureFor(checked.value, new Date(), duration, config);
        var subtotal = rate * duration;
        var tooHigh = discountTooHigh(stayScope, subtotal);
        var discount = tooHigh ? 0 : discountAmount(stayScope, subtotal);

        preview.textContent = 'Départ prévu : ' + formatDateTime(departure) + ' — ' + totalText(subtotal, discount, tooHigh);

        var paymentBox = stayScope.querySelector('[data-payment-lines]');
        if (paymentBox && paymentBox.paymentLines) {
            paymentBox.paymentLines.setDue(subtotal - discount);
        }
    };

    var selectMode = function (radio) {
        radio.checked = true;
        var rateInput = stayForm.querySelector('[data-unit-rate-input]');
        if (currentCard) {
            var rate = currentCard.dataset[RATE_KEYS[radio.value]];
            setMoney(rateInput, rate ? parseFloat(rate) : ''); // FCFA: no decimals
        }
        refreshStayPreview();
    };

    if (stayForm) {
        stayForm.querySelectorAll('[data-mode-option]').forEach(function (radio) {
            radio.addEventListener('change', function () { selectMode(radio); });
        });
        stayForm.querySelector('[data-duration-input]').addEventListener('input', refreshStayPreview);
        bindDiscount(stayScope, refreshStayPreview);
    }

    // restore = true when reopening after a failed submission: the fields already hold the user's
    // input (old values rendered by the server), so nothing is reset.
    var openAssignModal = function (card, restore) {
        if (!assignModal) {
            return;
        }
        currentCard = card;

        var form = assignModal.querySelector('form');
        form.action = card.dataset.assignUrl;
        var roomIdInput = form.querySelector('[data-assign-room-id]');
        if (roomIdInput) {
            roomIdInput.value = card.dataset.roomId;
        }

        var assignPayments = assignModal.querySelector('[data-payment-lines]');
        if (!restore && assignPayments && assignPayments.paymentLines) {
            assignPayments.paymentLines.reset();
        }

        // A new room starts without a discount (after a failed submission it's kept).
        var discountBox = assignModal.querySelector('[data-discount]');
        if (!restore && discountBox) {
            discountBox.querySelector('[data-discount-value]').value = '';
            discountBox.querySelector('[data-discount-type]').value = 'montant';
            discountBox.querySelector('[data-discount-reason]').value = '';
        }

        assignModal.querySelector('[data-room-label]').textContent =
            'Chambre N°' + card.dataset.roomNumber + ' — ' + card.dataset.roomType;

        if (stayForm) {
            var nightOpen = stayForm.dataset.nightOpen === '1';
            var firstEnabled = null;
            var preferred = null;
            var kept = null;

            stayForm.querySelectorAll('[data-mode-option]').forEach(function (radio) {
                var offered = card.dataset[RATE_KEYS[radio.value]] !== '';
                radio.disabled = !offered || (radio.value === 'nuitee' && !nightOpen);
                if (restore && radio.checked && !radio.disabled) {
                    kept = radio;
                }
                radio.checked = false;
                if (!radio.disabled) {
                    firstEnabled = firstEnabled || radio;
                    if (radio.value === 'nuitee' || (radio.value === 'heure' && !preferred)) {
                        preferred = radio;
                    }
                }
            });

            if (kept) {
                selectMode(kept); // keep the submitted mode and duration; the rate is the room type's
            } else {
                stayForm.querySelector('[data-duration-input]').value = 1;
                var initial = preferred || firstEnabled;
                if (initial) {
                    selectMode(initial);
                } else {
                    stayForm.querySelector('[data-unit-rate-input]').value = '';
                    var nightOnly = card.dataset.nightRate !== '' && !nightOpen;
                    stayForm.querySelector('[data-stay-preview]').textContent = nightOnly
                        ? 'Ce type de chambre n\'est tarifé qu\'à la nuitée, disponible à partir de ' + stayForm.dataset.nightFrom.replace(':', 'h')
                            + '. Pour une entrée immédiate, ajoutez un tarif à l\'heure, journée ou séjour (Configuration → Types de chambre).'
                        : "Aucun tarif n'est défini pour ce type de chambre : renseignez-en un dans Configuration → Types de chambre.";
                }
            }
        }

        var modal = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(assignModal) : null;
        if (modal) {
            modal.show();
        }
    };

    document.querySelectorAll('[data-assign-room]').forEach(function (card) {
        card.addEventListener('click', function () { openAssignModal(card, false); });
    });

    // --- Client type ([data-client-form]): natural person or company. Fields marked
    // data-client-only="morale" / "physique" only show for that type (they're still submitted;
    // the server ignores company fields for a person).
    document.querySelectorAll('[data-client-form]').forEach(function (form) {
        var apply = function () {
            var checked = form.querySelector('[data-client-type]:checked');
            var type = checked ? checked.value : 'physique';
            form.querySelectorAll('[data-client-only]').forEach(function (el) {
                el.hidden = el.dataset.clientOnly !== type;
            });
        };
        form.querySelectorAll('[data-client-type]').forEach(function (radio) {
            radio.addEventListener('change', apply);
        });
        apply();
    });

    // --- Guest search autocomplete ---
    // Usage: a text <input data-guest-search data-guest-search-url="...">,
    // a hidden <input data-guest-id-input name="guest_id">, and an empty
    // <div data-guest-search-results> right after the text input for suggestions.
    document.querySelectorAll('[data-guest-search]').forEach(function (input) {
        var url = input.dataset.guestSearchUrl;
        var hiddenInput = document.querySelector(input.dataset.guestIdTarget);
        var resultsBox = input.parentElement.querySelector('[data-guest-search-results]');
        var timer = null;

        if (!url || !hiddenInput || !resultsBox) {
            return;
        }

        input.addEventListener('input', function () {
            hiddenInput.value = '';
            var query = input.value.trim();

            clearTimeout(timer);
            if (query.length < 2) {
                resultsBox.innerHTML = '';
                return;
            }

            timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(query))
                    .then(function (response) { return response.json(); })
                    .then(function (guests) {
                        resultsBox.innerHTML = '';
                        guests.forEach(function (guest) {
                            var item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action';
                            item.textContent = guest.label;
                            item.addEventListener('click', function () {
                                input.value = guest.label;
                                hiddenInput.value = guest.id;
                                resultsBox.innerHTML = '';
                            });
                            resultsBox.appendChild(item);
                        });
                    });
            }, 250);
        });

        document.addEventListener('click', function (event) {
            if (!input.parentElement.contains(event.target)) {
                resultsBox.innerHTML = '';
            }
        });
    });

    // --- Dynamic room availability select (reservation form) ---
    // Usage: inputs with data-availability-trigger (check_in, and check_out for date-based
    // nuitées or duration for other modes), an optional pricing_mode field, a
    // <select data-room-select name="room_id"> to repopulate, and optionally a rate input
    // with data-rate-target (pre-filled with the room type's rate for the stay's mode).
    var roomSelect = document.querySelector('[data-room-select]');
    var triggers = document.querySelectorAll('[data-availability-trigger]');

    if (roomSelect && triggers.length) {
        var url = roomSelect.dataset.availabilityUrl;
        var excludeId = roomSelect.dataset.excludeReservationId || '';
        var rateInput = document.querySelector('[data-rate-target]');
        var form = roomSelect.form;

        var field = function (name) {
            // For radio groups (pricing_mode), the checked one; otherwise the field itself.
            var el = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('[name="' + name + '"]:not([type="radio"])');
            return el ? el.value : '';
        };

        var refreshRooms = function () {
            var params = new URLSearchParams({
                pricing_mode: field('pricing_mode'),
                check_in: field('check_in'),
                exclude_reservation_id: excludeId,
            });
            if (field('check_out')) {
                params.set('check_out', field('check_out'));
            } else if (field('duration')) {
                params.set('duration', field('duration'));
            } else {
                return;
            }
            if (!field('check_in')) {
                return;
            }

            fetch(url + '?' + params.toString(), { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : []; })
                .then(function (rooms) {
                    var currentValue = roomSelect.value;
                    roomSelect.innerHTML = '';

                    if (rooms.length === 0) {
                        var empty = document.createElement('option');
                        empty.value = '';
                        empty.textContent = 'Aucune chambre disponible pour ces dates';
                        roomSelect.appendChild(empty);
                        return;
                    }

                    // From an online booking request: pre-select a room of the requested type.
                    var preferredType = roomSelect.dataset.preferredType || '';
                    var preferred = !currentValue && preferredType
                        ? rooms.find(function (room) { return String(room.room_type_id) === preferredType; })
                        : null;
                    rooms.forEach(function (room) {
                        var option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.label;
                        option.dataset.rate = room.rate || '';
                        if (String(room.id) === currentValue || (preferred && room.id === preferred.id)) {
                            option.selected = true;
                        }
                        roomSelect.appendChild(option);
                    });
                    roomSelect.dispatchEvent(new CustomEvent('rooms:refreshed'));
                    if (preferred) {
                        roomSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
        };

        triggers.forEach(function (trigger) {
            trigger.addEventListener('change', refreshRooms);
        });
        if (roomSelect.hasAttribute('data-refresh-on-load')) {
            refreshRooms();
        }

        roomSelect.addEventListener('change', function () {
            var selected = roomSelect.options[roomSelect.selectedIndex];
            if (rateInput && selected && selected.dataset.rate) {
                setMoney(rateInput, parseFloat(selected.dataset.rate));
            }
        });
    }

    // --- End-of-stay alarm (departure time reached) ---
    // Rendered for staff who receive it (#checkoutAlerts: the Réception, never the Gérant). Polls
    // the feed, shows one card per overdue stay and RINGS CONTINUOUSLY (every few seconds) while
    // at least one of them isn't paused, until the stay is checked out or extended. « Couper 5
    // minutes » pauses one stay for everyone. Browsers only allow sound after a user interaction
    // with the page, so the audio context is unlocked on the first click/keypress.
    var alertsBox = document.getElementById('checkoutAlerts');
    var requestsBox = document.getElementById('requestAlerts');

    // Shared by the checkout alarm and the guest-request notification.
    var audioCtx = null;
    var baseTitle = document.title;

    var unlockAudio = function () {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) {
            return;
        }
        audioCtx = audioCtx || new Ctx();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    };
    if (alertsBox || requestsBox) {
        document.addEventListener('click', unlockAudio);
        document.addEventListener('keydown', unlockAudio);
    }

    // pattern: list of [offset s, frequency Hz]; default = the checkout alarm's triple beep.
    var ring = function (pattern) {
        if (!audioCtx || audioCtx.state !== 'running') {
            return false;
        }
        (pattern || [[0, 880], [0.35, 880], [0.7, 880]]).forEach(function (beep) {
            var offset = beep[0];
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = pattern ? 'sine' : 'square';
            osc.frequency.value = beep[1];
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime + offset);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + offset + 0.25);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + offset);
            osc.stop(audioCtx.currentTime + offset + 0.3);
        });
        return true;
    };

    var el = function (tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text; // never innerHTML: guest names come from user input
        }
        return node;
    };

    if (alertsBox && window.fetch) {
        var POLL_MS = 15000;
        var RING_EVERY_MS = 4000;
        var ringing = false;  // at least one overdue stay isn't paused
        var unlockButton = null;

        var overdueLabel = function (minutes) {
            if (minutes < 1) {
                return "à l'instant";
            }
            if (minutes < 60) {
                return 'depuis ' + minutes + ' min';
            }
            return 'depuis ' + Math.floor(minutes / 60) + ' h ' + pad(minutes % 60);
        };

        var updateTitle = function (count) {
            document.title = count ? '(' + count + ') Fin de séjour — ' + baseTitle : baseTitle;
        };

        // Continuous ringing: one triple beep every RING_EVERY_MS while `ringing`.
        var ringTick = function () {
            if (!ringing) {
                return;
            }
            if (ring()) {
                if (unlockButton) {
                    unlockButton.remove();
                    unlockButton = null;
                }
            } else if (!unlockButton) {
                unlockButton = el('button', 'btn btn-sm btn-warning checkout-alerts__unlock', "🔔 Cliquez ici pour activer le son de l'alarme");
                unlockButton.type = 'button';
                unlockButton.addEventListener('click', function () {
                    unlockAudio();
                    setTimeout(ringTick, 50);
                });
                alertsBox.insertBefore(unlockButton, alertsBox.firstChild);
            }
        };

        var snooze = function (alert, button) {
            button.disabled = true;
            if (!alert.snooze_url) { // demo card: nothing stored server-side
                var resume = new Date(Date.now() + 5 * 60000);
                alert.snoozed_until = pad(resume.getHours()) + 'h' + pad(resume.getMinutes());
                render(currentAlerts);
                return;
            }
            fetch(alert.snooze_url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': alertsBox.dataset.csrf, Accept: 'application/json' },
            }).then(function (response) {
                return response.ok ? response.json() : null;
            }).then(function (data) {
                if (data) {
                    alert.snoozed_until = data.snoozed_until;
                    render(currentAlerts);
                }
                button.disabled = false;
            }).catch(function () { button.disabled = false; });
        };

        var currentAlerts = [];
        var render = function (alerts) {
            currentAlerts = alerts;
            Array.prototype.slice.call(alertsBox.querySelectorAll('.checkout-alert')).forEach(function (card) { card.remove(); });

            alerts.forEach(function (alert) {
                var card = el('div', 'checkout-alert' + (alert.snoozed_until ? ' is-snoozed' : ''));
                card.setAttribute('role', 'alert');
                card.appendChild(el('div', 'checkout-alert__title',
                    'Fin de séjour — Chambre N°' + alert.room + (alert.room_type ? ' (' + alert.room_type + ')' : '')));

                var details = el('p', 'checkout-alert__details');
                [
                    'Client : ' + (alert.guest || '—') + (alert.phone ? ' — ' + alert.phone : ''),
                    alert.mode + ' : ' + alert.billing,
                    'Arrivée : ' + alert.check_in,
                    'Sortie prévue : ' + alert.check_out + ' (' + overdueLabel(alert.overdue_minutes) + ')',
                ].forEach(function (line, i) {
                    if (i) {
                        details.appendChild(document.createElement('br'));
                    }
                    details.appendChild(document.createTextNode(line));
                });
                card.appendChild(details);

                if (alert.snoozed_until) {
                    card.appendChild(el('div', 'checkout-alert__snoozed', '🔕 Sonnerie coupée jusqu\'à ' + alert.snoozed_until));
                }

                var actions = el('div', 'checkout-alert__actions');
                if (alert.checkout_url) {
                    var checkout = el('a', 'btn btn-sm btn-danger', 'Faire le check-out');
                    checkout.href = alert.checkout_url;
                    actions.appendChild(checkout);
                }
                if (alert.extend_url) {
                    var extend = el('a', 'btn btn-sm btn-outline-primary', 'Prolonger');
                    extend.href = alert.extend_url;
                    actions.appendChild(extend);
                }
                if (!alert.snoozed_until) {
                    var pause = el('button', 'btn btn-sm btn-outline-secondary', 'Couper 5 minutes');
                    pause.type = 'button';
                    pause.addEventListener('click', function () { snooze(alert, pause); });
                    actions.appendChild(pause);
                }
                card.appendChild(actions);

                alertsBox.appendChild(card);
            });

            ringing = alerts.some(function (alert) { return !alert.snoozed_until; });
            if (!ringing && unlockButton) {
                unlockButton.remove();
                unlockButton = null;
            }
            updateTitle(alerts.length);
            ringTick();
        };

        var poll = function () {
            fetch(alertsBox.dataset.feedUrl, { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (alerts) {
                    if (alerts) {
                        render(alerts);
                    }
                })
                .catch(function () { /* network hiccup: retry on next tick */ });
        };

        setInterval(ringTick, RING_EVERY_MS);

        // Demo: add ?demo-alarme to any admin URL to display a sample alarm (no data is created
        // or changed; real polling is paused on that page so the sample stays on screen).
        if (new URLSearchParams(window.location.search).has('demo-alarme')) {
            var now = new Date();
            var out = new Date(now.getTime() - 15 * 60000);
            var inAt = new Date(out.getTime() - 6 * 3600000);
            var stamp = function (d) {
                return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad(d.getHours()) + 'h' + pad(d.getMinutes());
            };
            render([{
                id: 0,
                room: '201 · DÉMO',
                room_type: 'Chambre Deluxe',
                guest: 'Client de démonstration',
                phone: '07 00 00 00 00',
                mode: 'Journée (6 h)',
                billing: '1 journée × 25 000 FCFA',
                check_in: stamp(inAt),
                check_out: stamp(out),
                overdue_minutes: 15,
                snoozed_until: null,
                checkout_url: null,
                extend_url: null,
                snooze_url: null,
            }]);
        } else {
            poll();
            setInterval(poll, POLL_MS);
        }
    }

    // --- Guest requests (sent from their phone, /demande) ---
    // Rendered for staff who can handle them (#requestAlerts in the layout). Polls the feed,
    // keeps the sidebar counter up to date, shows a card per pending request and chimes once
    // for each new one. "Masquer" hides a card on this browser only (the request stays pending).
    if (requestsBox && window.fetch) {
        var REQUEST_POLL_MS = 15000;
        var HIDDEN_KEY = 'ek.hiddenRequests';
        var SEEN_KEY = 'ek.seenRequests';
        var counter = document.querySelector('[data-request-count]');

        var readIds = function (key) {
            try {
                return JSON.parse(window.localStorage.getItem(key) || '[]');
            } catch (e) {
                return [];
            }
        };
        var writeIds = function (key, ids) {
            try {
                window.localStorage.setItem(key, JSON.stringify(ids.slice(-200)));
            } catch (e) { /* storage unavailable: cards simply come back */ }
        };

        var renderRequests = function (items) {
            if (counter) {
                counter.textContent = String(items.length);
                counter.hidden = items.length === 0;
            }

            var hidden = readIds(HIDDEN_KEY);
            var seen = readIds(SEEN_KEY);
            var fresh = false;
            requestsBox.innerHTML = '';

            items.forEach(function (item) {
                if (seen.indexOf(item.id) === -1) {
                    fresh = true;
                    seen.push(item.id);
                }
                if (hidden.indexOf(item.id) !== -1) {
                    return;
                }

                var card = el('div', 'request-alert');
                card.setAttribute('role', 'status');
                card.appendChild(el('div', 'request-alert__title',
                    'Demande client N°' + item.id + ' — Chambre N°' + item.room + (item.room_type ? ' (' + item.room_type + ')' : '')));

                var details = el('p', 'request-alert__details');
                [
                    item.guest + (item.phone ? ' — ' + item.phone : ''),
                    item.mode + ' : ' + item.billing + ' = ' + item.total,
                    'Envoyée à ' + item.sent_at + ' · chambre retenue jusqu\'à ' + item.expires_at,
                ].forEach(function (line, i) {
                    if (i) {
                        details.appendChild(document.createElement('br'));
                    }
                    details.appendChild(document.createTextNode(line));
                });
                card.appendChild(details);

                var actions = el('div', 'd-flex gap-2');
                var open = el('a', 'btn btn-sm btn-primary', 'Traiter');
                open.href = item.open_url;
                actions.appendChild(open);
                var hide = el('button', 'btn btn-sm btn-outline-secondary', 'Masquer');
                hide.type = 'button';
                hide.addEventListener('click', function () {
                    var ids = readIds(HIDDEN_KEY);
                    ids.push(item.id);
                    writeIds(HIDDEN_KEY, ids);
                    card.remove();
                });
                actions.appendChild(hide);
                card.appendChild(actions);

                requestsBox.appendChild(card);
            });

            writeIds(SEEN_KEY, seen);
            if (fresh) {
                ring([[0, 660], [0.18, 880], [0.36, 1100]]);
            }
            if (!document.title.match(/^\(\d+\) Départ/)) {
                document.title = items.length ? '(' + items.length + ') Demande client — ' + baseTitle : baseTitle;
            }
        };

        var pollRequests = function () {
            fetch(requestsBox.dataset.feedUrl, { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (items) {
                    if (items) {
                        renderRequests(items);
                    }
                })
                .catch(function () { /* network hiccup: retry on next tick */ });
        };

        pollRequests();
        setInterval(pollRequests, REQUEST_POLL_MS);
    }

    // --- Receipt preview, opened right after an affectation / a réservation (#receiptModal) ---
    // The ticket is printed from its own frame, so only the ticket goes to the printer.
    var receiptModal = document.getElementById('receiptModal');
    if (receiptModal && window.bootstrap) {
        var receiptFrame = receiptModal.querySelector('[data-receipt-frame]');
        receiptModal.querySelector('[data-receipt-print]').addEventListener('click', function () {
            if (receiptFrame && receiptFrame.contentWindow) {
                receiptFrame.contentWindow.focus();
                receiptFrame.contentWindow.print();
            }
        });
        window.bootstrap.Modal.getOrCreateInstance(receiptModal).show();
    }

    // --- Chart tooltips (dashboard) ---
    // Any element with data-tip shows its text in a floating tooltip on hover and keyboard
    // focus. Text only (textContent): values never pass through innerHTML.
    var tipTargets = document.querySelectorAll('[data-tip]');

    if (tipTargets.length) {
        var tip = document.createElement('div');
        tip.className = 'chart-tip';
        tip.setAttribute('role', 'tooltip');
        document.body.appendChild(tip);

        var placeTip = function (x, y) {
            var rect = tip.getBoundingClientRect();
            var left = Math.min(window.innerWidth - rect.width - 8, Math.max(8, x - rect.width / 2));
            var top = y - rect.height - 12;
            tip.style.left = left + 'px';
            tip.style.top = (top < 8 ? y + 16 : top) + 'px';
        };

        tipTargets.forEach(function (target) {
            var show = function () {
                tip.textContent = target.getAttribute('data-tip');
                tip.classList.add('is-visible');
            };
            var hide = function () { tip.classList.remove('is-visible'); };

            target.addEventListener('pointerenter', show);
            target.addEventListener('pointermove', function (event) { placeTip(event.clientX, event.clientY); });
            target.addEventListener('pointerleave', hide);
            target.addEventListener('focus', function () {
                show();
                var box = target.getBoundingClientRect();
                placeTip(box.left + box.width / 2, box.top);
            });
            target.addEventListener('blur', hide);
        });
    }

    // --- Payment lines ("encaissement", mixed payment = several lines) ---
    // resources/views/admin/payments/_lines.blade.php. Lines post as payments[i][field]; the
    // summary compares the total with data-due (the server re-checks: never above what is due).
    var setupPaymentLines = function (box) {
        var list = box.querySelector('[data-lines]');
        var template = box.querySelector('[data-line-template]');
        var addButton = box.querySelector('[data-add-line]');
        var fillButton = box.querySelector('[data-fill-due]');
        var summary = box.querySelector('[data-payment-summary]');
        var required = box.dataset.required === '1';
        var mustPayFull = box.dataset.full === '1';
        var receivedRequired = box.dataset.receivedRequired === '1';
        var amountTouched = false; // in "full" mode the first line follows the due amount until edited
        var methodCount = template.content.querySelectorAll('option').length;
        // One shared « Montant reçu » field (at the desk) instead of one per line: methods are
        // distinct, so it belongs to the single cash line, whose hidden "received" it fills.
        var sharedBox = box.querySelector('[data-received-shared-box]');
        var sharedInput = sharedBox ? sharedBox.querySelector('[data-received-shared]') : null;
        var sharedChange = sharedBox ? sharedBox.querySelector('[data-change-shared]') : null;
        var cashLine = function () {
            return lines().filter(function (line) { return line.querySelector('[data-field="method"]').value === 'especes'; })[0] || null;
        };

        var lines = function () { return Array.prototype.slice.call(list.querySelectorAll('[data-line]')); };
        var due = function () { return box.dataset.due === '' ? null : parseFloat(box.dataset.due) || 0; };
        var total = function () {
            return lines().reduce(function (sum, line) {
                return sum + moneyValue(line.querySelector('[data-field="amount"]'));
            }, 0);
        };

        var reindex = function () {
            lines().forEach(function (line, i) {
                line.querySelectorAll('[data-field]').forEach(function (field) {
                    field.name = 'payments[' + i + '][' + field.dataset.field + ']';
                });
                var amount = line.querySelector('[data-field="amount"]');
                amount.required = required && i === 0;
            });
            var count = lines().length;
            addButton.disabled = count >= methodCount;
            lines().forEach(function (line) {
                line.querySelector('[data-remove-line]').hidden = count === 1;
            });
        };

        var syncMethods = function () {
            var selects = lines().map(function (line) { return line.querySelector('[data-field="method"]'); });
            selects.forEach(function (select) {
                var takenElsewhere = selects
                    .filter(function (other) { return other !== select; })
                    .map(function (other) { return other.value; });
                Array.prototype.forEach.call(select.options, function (option) {
                    option.disabled = takenElsewhere.indexOf(option.value) !== -1;
                });
            });
        };

        // Cash lines show "Reçu" and the change to give back (the server recomputes it).
        var changeTotal = function () {
            return lines().reduce(function (sum, line) {
                var isCash = line.querySelector('[data-field="method"]').value === 'especes';
                var cashBox = line.querySelector('[data-cash-only]');
                var changeBox = line.querySelector('[data-change]');
                var amount = moneyValue(line.querySelector('[data-field="amount"]'));
                var receivedInput = line.querySelector('[data-field="received"]');
                cashBox.hidden = !isCash;
                receivedInput.required = isCash && receivedRequired && amount > 0;
                if (!isCash) {
                    receivedInput.value = '';
                    changeBox.textContent = '';
                    changeBox.className = 'payment-line__change';
                    return sum;
                }
                if (amount === 0) { // nothing to cash on this line yet: no change to announce
                    changeBox.textContent = '';
                    changeBox.className = 'payment-line__change';
                    return sum;
                }
                var received = receivedInput.value === '' ? amount : moneyValue(receivedInput);
                var change = received - amount;
                changeBox.className = 'payment-line__change' + (change < 0 ? ' is-error' : '');
                changeBox.textContent = receivedInput.value === '' || amount === 0
                    ? ''
                    : (change < 0 ? 'Montant reçu insuffisant (manque ' + formatAmount(-change) + ')' : 'Monnaie à rendre : ' + formatAmount(change));
                return sum + Math.max(0, change);
            }, 0);
        };

        var update = function () {
            syncMethods();
            if (sharedBox) {
                var cash = cashLine();
                if (cash) {
                    cash.querySelector('[data-field="received"]').value = sharedInput.value;
                }
            }
            var change = changeTotal();
            if (sharedBox) {
                var cashNow = cashLine();
                var cashAmount = cashNow ? moneyValue(cashNow.querySelector('[data-field="amount"]')) : 0;
                sharedBox.hidden = !cashNow;
                sharedInput.required = !!cashNow && receivedRequired && cashAmount > 0;
                if (!cashNow) {
                    sharedInput.value = '';
                }
                var lineChange = cashNow ? cashNow.querySelector('[data-change]') : null;
                sharedChange.textContent = lineChange ? lineChange.textContent : '';
                sharedChange.className = lineChange ? lineChange.className : 'payment-line__change';
            }
            var paid = total();
            var owed = due();
            summary.className = 'payment-lines__summary';
            if (owed === null) {
                summary.textContent = paid > 0 ? 'Encaissé : ' + formatAmount(paid) : '';
            } else if (paid > owed + 0.001) {
                summary.classList.add('is-error');
                summary.textContent = 'Encaissé : ' + formatAmount(paid) + ' — dépasse le reste dû (' + formatAmount(owed) + ')';
            } else if (paid > 0 && Math.abs(paid - owed) < 0.001) {
                summary.classList.add('is-ok');
                summary.textContent = 'Encaissé : ' + formatAmount(paid) + ' — facture soldée';
            } else if (mustPayFull && owed > 0) {
                summary.classList.add('is-error');
                summary.textContent = 'Encaissé : ' + formatAmount(paid) + ' — il manque ' + formatAmount(owed - paid) + ' (paiement total obligatoire)';
            } else {
                summary.textContent = 'Encaissé : ' + formatAmount(paid) + ' · Reste dû : ' + formatAmount(Math.max(0, owed - paid));
            }
            if (change > 0 && summary.textContent) {
                summary.textContent += ' — monnaie à rendre : ' + formatAmount(change);
            }
            fillButton.hidden = owed === null || owed <= 0;
        };

        var unusedMethod = function () {
            var used = lines().map(function (line) { return line.querySelector('[data-field="method"]').value; });
            var options = template.content.querySelectorAll('option');
            for (var i = 0; i < options.length; i++) {
                if (used.indexOf(options[i].value) === -1) {
                    return options[i].value;
                }
            }
            return options[0].value;
        };

        var bindLine = function (line) {
            line.querySelector('[data-field="amount"]').addEventListener('input', function () {
                amountTouched = true;
                update();
            });
            line.querySelector('[data-field="received"]').addEventListener('input', update);
            line.querySelector('[data-field="method"]').addEventListener('change', update);
            line.querySelector('[data-remove-line]').addEventListener('click', function () {
                line.remove();
                reindex();
                update();
            });
        };

        var addLine = function () {
            var method = unusedMethod();
            var fragment = template.content.cloneNode(true);
            var line = fragment.querySelector('[data-line]');
            line.querySelector('[data-field="method"]').value = method;
            list.appendChild(fragment);
            bindLine(line);
            reindex();
            update();
            return line;
        };

        lines().forEach(bindLine);
        if (sharedInput) {
            // After a failed submission, the cash line's posted value comes back in its hidden input.
            var restoredCash = cashLine();
            if (restoredCash) {
                sharedInput.value = restoredCash.querySelector('[data-field="received"]').value;
            }
            sharedInput.addEventListener('input', update);
        }
        addButton.addEventListener('click', function () {
            addLine().querySelector('[data-field="amount"]').focus();
        });
        fillButton.addEventListener('click', function () {
            var owed = due() || 0;
            var others = lines().slice(1).reduce(function (sum, line) {
                return sum + moneyValue(line.querySelector('[data-field="amount"]'));
            }, 0);
            setMoney(lines()[0].querySelector('[data-field="amount"]'), Math.max(0, owed - others));
            update();
        });

        box.paymentLines = {
            setDue: function (value) {
                box.dataset.due = String(value);
                // Affectation: pre-fill the single line with the total until the cashier edits it.
                if (mustPayFull && !amountTouched && lines().length === 1) {
                    setMoney(lines()[0].querySelector('[data-field="amount"]'), value > 0 ? value : '');
                }
                update();
            },
            reset: function () {
                lines().forEach(function (line, i) {
                    if (i > 0) {
                        line.remove();
                    }
                });
                var first = lines()[0];
                first.querySelector('[data-field="method"]').selectedIndex = 0;
                first.querySelector('[data-field="amount"]').value = '';
                first.querySelector('[data-field="received"]').value = '';
                if (sharedInput) {
                    sharedInput.value = '';
                }
                amountTouched = false;
                reindex();
                update();
            },
        };

        reindex();
        update();
    };

    document.querySelectorAll('[data-payment-lines]').forEach(setupPaymentLines);

    // Standalone stay form (guest request validation): show the total and pre-fill the payment
    // with it, keeping the request's mode, duration and rate (or the re-submitted ones).
    if (stayForm && !assignModal) {
        refreshStayPreview();
    }

    // --- Reopen the assignment modal after a failed submission (input + errors kept) ---
    if (assignModal && assignModal.dataset.reopenRoom) {
        var reopenCard = document.querySelector('[data-assign-room][data-room-id="' + assignModal.dataset.reopenRoom + '"]');
        if (reopenCard) {
            openAssignModal(reopenCard, true);
            var firstInvalid = assignModal.querySelector('.is-invalid');
            if (firstInvalid) {
                assignModal.addEventListener('shown.bs.modal', function () { firstInvalid.focus(); }, { once: true });
            }
        }
    }

    // --- Room board tabs: one state (Occupées, Libres…) at a time ---
    // Opening tab: the URL hash (#disponible…) if valid, else the tab holding a room whose
    // assignment just failed, else "Libres", else the first non-empty state. The choice is
    // kept in the hash so a reload (e.g. after an assignment) comes back to the same tab.
    var roomTabBar = document.querySelector('[data-room-tabs]');

    if (roomTabBar) {
        var roomTabs = Array.prototype.slice.call(roomTabBar.querySelectorAll('[data-room-tab]'));
        var roomPanels = document.querySelectorAll('[data-room-panel]');
        var enabledTabs = roomTabs.filter(function (tab) { return !tab.disabled; });

        var activateRoomTab = function (state, focus) {
            roomTabs.forEach(function (tab) {
                var selected = tab.dataset.roomTab === state;
                tab.classList.toggle('is-active', selected);
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
                if (selected && focus) {
                    tab.focus();
                }
            });
            Array.prototype.forEach.call(roomPanels, function (panel) {
                panel.hidden = panel.dataset.roomPanel !== state;
            });
            document.querySelectorAll('[data-room-tab-only]').forEach(function (el) {
                el.hidden = el.dataset.roomTabOnly !== state;
            });
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + state);
            }
        };

        var isEnabledState = function (state) {
            return enabledTabs.some(function (tab) { return tab.dataset.roomTab === state; });
        };

        var initialState = window.location.hash.replace('#', '');
        if (!isEnabledState(initialState)) {
            initialState = null;
            var reopenId = assignModal ? assignModal.dataset.reopenRoom : '';
            var reopenPanel = reopenId
                ? document.querySelector('[data-room-panel] [data-room-id="' + reopenId + '"]')
                : null;
            if (reopenPanel) {
                initialState = reopenPanel.closest('[data-room-panel]').dataset.roomPanel;
            } else if (isEnabledState('disponible')) {
                initialState = 'disponible';
            } else if (enabledTabs.length) {
                initialState = enabledTabs[0].dataset.roomTab;
            }
        }

        if (initialState) {
            document.body.classList.add('room-tabs-ready');
            activateRoomTab(initialState, false);
        }

        roomTabs.forEach(function (tab) {
            tab.addEventListener('click', function () { activateRoomTab(tab.dataset.roomTab, false); });
            tab.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                    return;
                }
                event.preventDefault();
                var index = enabledTabs.indexOf(tab);
                var next = enabledTabs[(index + (event.key === 'ArrowRight' ? 1 : enabledTabs.length - 1)) % enabledTabs.length];
                activateRoomTab(next.dataset.roomTab, true);
            });
        });
    }

    // --- Réservation form: four pricing modes ([data-booking-form], reservations/_form) ---
    // The arrival is a date for a nuitée (night of …) and a date + time otherwise; the preview
    // mirrors PricingMode (the server recomputes everything) and drives the deposit's "due".
    var bookingForm = document.querySelector('[data-booking-form]');

    if (bookingForm) {
        var bookingConfig = {
            nightFrom: bookingForm.dataset.nightFrom,
            nightUntil: bookingForm.dataset.nightUntil,
            dayHours: parseInt(bookingForm.dataset.dayHours, 10),
            stayHours: parseInt(bookingForm.dataset.stayHours, 10),
        };
        var bookingCheckIn = bookingForm.querySelector('[data-check-in-input]');
        var bookingDuration = bookingForm.querySelector('[data-booking-duration]');
        var bookingRate = bookingForm.querySelector('[data-booking-rate]');
        var bookingPreview = bookingForm.querySelector('[data-booking-preview]');
        var bookingRoom = bookingForm.querySelector('[data-room-select]');
        var bookingDeposit = bookingForm.querySelector('[data-payment-lines]');
        var rateIsStale = false;

        var bookingUnits = {
            heure: { name: 'heures', rate: '/ heure' },
            journee: { name: 'journées (' + bookingConfig.dayHours + ' h)', rate: '/ journée' },
            nuitee: { name: 'nuits', rate: '/ nuit' },
            sejour: { name: 'périodes de ' + bookingConfig.stayHours + ' h', rate: '/ ' + bookingConfig.stayHours + ' h' },
        };

        var bookingMode = function () {
            var checked = bookingForm.querySelector('[data-booking-mode]:checked');
            return checked ? checked.value : 'nuitee';
        };

        var switchArrivalInput = function (mode) {
            var value = bookingCheckIn.value;
            if (mode === 'nuitee' && bookingCheckIn.type !== 'date') {
                bookingCheckIn.type = 'date';
                bookingCheckIn.value = value.slice(0, 10);
            } else if (mode !== 'nuitee' && bookingCheckIn.type !== 'datetime-local') {
                bookingCheckIn.type = 'datetime-local';
                bookingCheckIn.value = value ? value.slice(0, 10) + 'T10:00' : '';
            }
            bookingForm.querySelector('[data-check-in-label]').textContent = mode === 'nuitee' ? 'Nuit du' : 'Arrivée le';
        };

        var refreshBooking = function () {
            var mode = bookingMode();
            bookingForm.querySelector('[data-booking-duration-label]').textContent = 'Nombre de ' + bookingUnits[mode].name;
            bookingForm.querySelector('[data-booking-rate-unit]').textContent = bookingUnits[mode].rate;

            var duration = Math.max(1, parseInt(bookingDuration.value, 10) || 1);
            var rate = moneyValue(bookingRate);
            var subtotal = rate * duration;
            var tooHigh = discountTooHigh(bookingForm, subtotal);
            var discount = tooHigh ? 0 : discountAmount(bookingForm, subtotal);
            var total = subtotal - discount;

            if (bookingCheckIn.value) {
                var start = new Date(mode === 'nuitee' ? bookingCheckIn.value + 'T' + bookingConfig.nightFrom : bookingCheckIn.value);
                bookingPreview.textContent = 'Arrivée : ' + formatDateTime(start)
                    + ' — Départ prévu : ' + formatDateTime(departureFor(mode, start, duration, bookingConfig))
                    + ' — ' + totalText(subtotal, discount, tooHigh);
            } else {
                bookingPreview.textContent = "Renseignez l'arrivée.";
            }

            if (bookingDeposit && bookingDeposit.paymentLines) {
                bookingDeposit.paymentLines.setDue(total);
            }
        };

        bookingForm.querySelectorAll('[data-booking-mode]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                switchArrivalInput(radio.value);
                rateIsStale = true; // the room's rate for the new mode arrives with the room list
                refreshBooking();
            });
        });
        bindDiscount(bookingForm, refreshBooking);

        [bookingCheckIn, bookingDuration, bookingRate].forEach(function (input) {
            input.addEventListener('input', refreshBooking);
            input.addEventListener('change', refreshBooking);
        });
        if (bookingRoom) {
            bookingRoom.addEventListener('change', refreshBooking);
            bookingRoom.addEventListener('rooms:refreshed', function () {
                var selected = bookingRoom.options[bookingRoom.selectedIndex];
                if (rateIsStale && selected && selected.dataset.rate) {
                    setMoney(bookingRate, parseFloat(selected.dataset.rate));
                }
                rateIsStale = false;
                refreshBooking();
            });
        }

        refreshBooking();
    }

    // --- Filter bars: show the custom date range only for "Dates personnalisées" ---
    document.querySelectorAll('[data-filter-form]').forEach(function (form) {
        var period = form.querySelector('[data-period-select]');
        var dates = form.querySelector('[data-period-dates]');
        if (period && dates) {
            period.addEventListener('change', function () {
                dates.hidden = period.value !== 'personnalisee';
            });
        }
    });

    // --- Room photos ([data-image-uploader], rooms/_form): downsize in the browser, preview, limit ---
    // Picked files are re-encoded as JPEG (max 1600 px, which also strips EXIF/GPS data) so 5
    // phone photos fit the server's upload limits; the server re-validates everything.
    var MAX_PHOTO_SIDE = 1600;

    var loadWithImg = function (file) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    };

    var downsizePhoto = function (file) {
        // createImageBitmap honours EXIF orientation; if it's missing, rejects (older Safari) or
        // doesn't answer within 4 s, fall back to an <img> (browsers apply EXIF there too).
        var load = window.createImageBitmap
            ? Promise.race([
                window.createImageBitmap(file, { imageOrientation: 'from-image' }),
                new Promise(function (resolve, reject) { setTimeout(reject, 4000); }),
            ]).catch(function () { return loadWithImg(file); })
            : loadWithImg(file);

        return load.then(function (bitmap) {
            var ratio = Math.min(1, MAX_PHOTO_SIDE / Math.max(bitmap.width, bitmap.height));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * ratio);
            canvas.height = Math.round(bitmap.height * ratio);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    resolve(new File([blob], name, { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.85);
            });
        });
    };

    var lightbox = document.querySelector('[data-lightbox]');
    var openLightbox = function (src) {
        if (!lightbox) {
            return;
        }
        lightbox.querySelector('[data-lightbox-img]').src = src;
        lightbox.hidden = false;
    };
    if (lightbox) {
        var closeLightbox = function () { lightbox.hidden = true; };
        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox || event.target.hasAttribute('data-lightbox-close')) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            }
        });
    }

    document.querySelectorAll('[data-image-uploader]').forEach(function (box) {
        var max = parseInt(box.dataset.max, 10) || 5;
        var limitLabel = box.dataset.limitLabel || 'par chambre';
        var input = box.querySelector('[data-photo-input]');
        var grid = box.querySelector('[data-photo-grid]');
        var addTile = box.querySelector('[data-add-tile]');
        var countBadge = box.querySelector('[data-photo-count]');
        var status = box.querySelector('[data-photo-status]');
        var picked = []; // [{file, url, tile}]

        var keptExisting = function () {
            return Array.prototype.filter.call(box.querySelectorAll('[data-remove-existing]'), function (cb) { return !cb.checked; }).length;
        };
        var total = function () { return keptExisting() + picked.length; };

        var syncInput = function () {
            var transfer = new DataTransfer();
            picked.forEach(function (item) { transfer.items.add(item.file); });
            input.files = transfer.files;
        };

        var refresh = function () {
            countBadge.textContent = total() + ' / ' + max + ' photos';
            addTile.hidden = total() >= max;
        };

        var addPicked = function (file) {
            var url = URL.createObjectURL(file);
            var tile = document.createElement('figure');
            tile.className = 'room-photo is-new';
            var img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            img.dataset.previewSrc = url;
            var badge = document.createElement('span');
            badge.className = 'room-photo__cover';
            badge.textContent = 'Nouvelle';
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'room-photo__remove';
            remove.setAttribute('aria-label', 'Retirer cette photo');
            remove.innerHTML = '<i class="far fa-times"></i>';
            tile.appendChild(img);
            tile.appendChild(badge);
            tile.appendChild(remove);
            grid.insertBefore(tile, addTile);

            var item = { file: file, url: url, tile: tile };
            picked.push(item);
            remove.addEventListener('click', function () {
                picked.splice(picked.indexOf(item), 1);
                URL.revokeObjectURL(url);
                tile.remove();
                syncInput();
                refresh();
            });
        };

        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files);
            var room = max - total();
            var accepted = files.filter(function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
            var ignoredType = files.length - accepted.length;
            var ignoredCount = Math.max(0, accepted.length - room);
            accepted = accepted.slice(0, Math.max(0, room));

            status.textContent = accepted.length ? 'Préparation des photos…' : '';
            Promise.all(accepted.map(downsizePhoto)).then(function (ready) {
                ready.forEach(addPicked);
                syncInput();
                refresh();
                var notes = [];
                if (ignoredCount) {
                    notes.push(ignoredCount + ' photo(s) ignorée(s) : maximum ' + max + ' ' + limitLabel + '.');
                }
                if (ignoredType) {
                    notes.push(ignoredType + ' fichier(s) ignoré(s) : formats acceptés JPG, PNG, WebP.');
                }
                status.textContent = notes.join(' ');
            }).catch(function () {
                syncInput();
                status.textContent = "Une photo n'a pas pu être lue. Essayez un autre fichier.";
            });
        });

        box.querySelectorAll('[data-remove-existing]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (!cb.checked && total() > max) {
                    cb.checked = true; // restoring it would exceed the limit
                    status.textContent = 'Retirez d\'abord une nouvelle photo : maximum ' + max + ' ' + limitLabel + '.';
                }
                cb.closest('[data-existing-photo]').classList.toggle('is-removed', cb.checked);
                refresh();
            });
            cb.closest('[data-existing-photo]').classList.toggle('is-removed', cb.checked);
        });

        grid.addEventListener('click', function (event) {
            var img = event.target.closest('img[data-preview-src]');
            if (img) {
                openLightbox(img.dataset.previewSrc);
            }
        });

        refresh();
    });
})();

// --- Tables as cards on phones ---
// Every list table of the application (a <table> with a <thead>, not marked .table-keep and not
// a "Voir les données" twin) gets .table-cards: under 768 px the CSS shows each row as a card,
// each cell labelled with its column's header (data-label). The title column (data-card-title on
// its <th>, else the first one) heads the card; « Actions » / unlabelled last columns become the
// card's buttons; a row made of one wide cell (group heading, "nothing yet") spans the card.
// Without JS the tables simply scroll sideways as before.
(function () {
    'use strict';

    document.querySelectorAll('.admin-content table').forEach(function (table) {
        if (!table.tHead || table.classList.contains('table-keep') || table.closest('.dash-data, details')) {
            return;
        }

        var headers = [];
        var titleIndex = 0;
        Array.prototype.forEach.call(table.tHead.rows[table.tHead.rows.length - 1].cells, function (th) {
            for (var i = 0; i < (th.colSpan || 1); i++) {
                if (th.hasAttribute('data-card-title')) {
                    titleIndex = headers.length;
                }
                headers.push((th.textContent || '').replace(/\s+/g, ' ').trim());
            }
        });
        var last = headers.length - 1;

        Array.prototype.forEach.call(table.querySelectorAll('tbody tr, tfoot tr'), function (row) {
            var cells = row.cells;
            if (cells.length === 1 && (cells[0].colSpan || 1) > 1) {
                row.classList.add('row-full');
                return;
            }
            var column = 0;
            Array.prototype.forEach.call(cells, function (cell) {
                var label = headers[column] || '';
                if (!cell.hasAttribute('data-label')) {
                    cell.setAttribute('data-label', label);
                }
                if (column === titleIndex) {
                    cell.classList.add('cell-title');
                } else if (column === last && (label === '' || /^actions?$/i.test(label))) {
                    cell.classList.add('cell-actions');
                }
                var text = cell.textContent.replace(/\s+/g, '');
                if (column !== titleIndex && (text === '' || text === '—' || text === '-') && !cell.querySelector('img, input, select, button, a')) {
                    cell.classList.add('cell-empty');
                }
                column += cell.colSpan || 1;
            });
        });

        table.classList.add('table-cards');
    });
})();

// --- SweetAlert pop-ups (pages defining the Blade section "sweetalert", e.g. the réservations) ---
// #swalFlash carries the flashed status message and the validation errors (rendered as text,
// never as HTML). Forms with data-confirm ask through SweetAlert instead of window.confirm()
// (data-confirm-title, data-confirm-button, data-confirm-cancel, data-confirm-danger). Without SweetAlert loaded,
// the browser's confirm() is used.
(function () {
    'use strict';

    var Swal = window.Swal;
    var theme = {
        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ek-primary').trim() || '#24396b',
        cancelButtonText: 'Annuler',
        reverseButtons: true,
    };

    var flash = document.getElementById('swalFlash');
    if (flash && Swal) {
        var data = {};
        try {
            data = JSON.parse(flash.textContent || '{}');
        } catch (e) {
            data = {};
        }
        var receiptOpening = !!document.getElementById('receiptModal');

        if (data.errors && data.errors.length) {
            var list = document.createElement('ul');
            list.className = 'swal-error-list';
            data.errors.forEach(function (message) {
                var item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            });
            Swal.fire(Object.assign({}, theme, {
                icon: 'error',
                title: data.errors.length > 1 ? 'Veuillez corriger ces points' : 'Veuillez corriger ce point',
                html: list,
                confirmButtonText: 'Compris',
            }));
        } else if (data.status) {
            // A receipt preview may open right after a booking: a toast doesn't get in its way.
            Swal.fire({
                icon: 'success',
                title: data.status,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: receiptOpening ? 4000 : 6000,
                timerProgressBar: true,
            });
        }
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            var proceed = function () {
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(event.submitter || undefined);
                } else {
                    form.submit();
                }
            };
            if (!Swal) {
                if (window.confirm(form.dataset.confirm)) {
                    proceed();
                }
                return;
            }
            var danger = form.hasAttribute('data-confirm-danger');
            Swal.fire(Object.assign({}, theme, {
                icon: danger ? 'warning' : 'question',
                title: form.dataset.confirmTitle || 'Confirmer',
                text: form.dataset.confirm,
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Confirmer',
                cancelButtonText: form.dataset.confirmCancel || theme.cancelButtonText,
                confirmButtonColor: danger ? '#b53441' : theme.confirmButtonColor,
                focusCancel: danger,
            })).then(function (result) {
                if (result.isConfirmed) {
                    proceed();
                }
            });
        });
    });
})();
