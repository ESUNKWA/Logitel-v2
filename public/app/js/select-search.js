// --- Searchable selects (client request, 2026-11-19) ---
// Every <select> of the application becomes a Tom Select (public/vendor/tom-select, vendored) with
// a search field at the top of its list. The original <select> stays in the form, visually hidden:
// it is still what is posted, what the browser validates (required) and what admin.js reads
// (value, selectedIndex, options, data-*). So nothing else has to change:
//   - a choice in the list → native "input" + "change" events on the <select> (they bubble), so
//     inline onchange="this.form.submit()" and admin.js listeners keep working;
//   - admin.js changes are mirrored in the list: options rebuilt / disabled (MutationObserver),
//     select.value / selectedIndex assigned (per-select setters), form reset, back/forward cache;
//   - selects inserted later (payment lines from a <template>) are enhanced on insertion.
// Opt-out: data-no-search on the select or an ancestor. Left native: multiple / size > 1 lists,
// DataTables' « lignes par page » (a table control, not a form field) and SweetAlert's own hidden
// input select (.swal2-select, unused by our pop-ups: enhanced, it showed up in every confirmation).
// Security: labels are rendered by Tom Select's default templates, which HTML-escape them (guest
// and room names are user input) — any custom render function must use escape() too. No create:
// only the existing options can be chosen (the server validates every value anyway) — except on a
// select marked data-allow-create (catalogue category, client request 2026-11-28): a name typed
// in the search that matches no option can be added (« Ajouter « … » », 60 characters at most,
// escaped); a matching <option> is appended to the <select> so it is what gets posted. If the
// library is missing or fails, the native selects simply stay as they are.
(function () {
    'use strict';

    if (typeof window.TomSelect !== 'function' || !window.MutationObserver) {
        return;
    }

    var proto = HTMLSelectElement.prototype;
    var nativeValue = Object.getOwnPropertyDescriptor(proto, 'value');
    var nativeIndex = Object.getOwnPropertyDescriptor(proto, 'selectedIndex');
    if (!nativeValue || !nativeIndex) {
        return;
    }

    var SKIP = '[data-no-search], .dt-length select, .swal2-select, .swal2-container select';

    var eligible = function (select) {
        return !select.multiple && !(select.size > 1) && !select.matches(SKIP) && !select.closest('[data-no-search]');
    };

    // Tom Select's own writer re-appends the chosen <option> at the end of the <select>, which
    // reorders the list and breaks selectedIndex-based code (payment lines reset to index 0):
    // write the value in place instead, with the native setter (not the watched one below).
    window.TomSelect.define('ek_in_place', function () {
        var self = this;
        self.hook('instead', 'updateOriginalInput', function (opts) {
            var value = self.items.length ? String(self.items[0]) : '';
            if (nativeValue.get.call(self.input) !== value) {
                nativeValue.set.call(self.input, value);
            }
            if (self.isSetup && !(opts && opts.silent)) {
                self.trigger('change', self.getValue());
            }
        });
    });

    // Rebuild the list from the <select> as it is now. (Tom Select's sync() skips the empty
    // option and never removes the old ones, so it's done here.)
    var refresh = function (select) {
        var ts = select.tomselect;
        if (!ts) {
            return;
        }
        ts.clearOptions(function () { return false; });
        Array.prototype.forEach.call(select.options, function (option) {
            ts.addOption({ value: option.value, text: option.textContent, disabled: option.disabled });
        });
        ts.setValue(select.selectedIndex >= 0 ? nativeValue.get.call(select) : [], true);
        if (select.disabled && !ts.isDisabled) {
            ts.disable();
        } else if (!select.disabled && ts.isDisabled) {
            ts.enable();
        }
        if (ts.isOpen) {
            ts.refreshOptions(false);
        }
    };

    // Several changes in a row (a list rebuilt option by option) → one refresh.
    var pending = [];
    var flush = function () {
        var list = pending;
        pending = [];
        list.forEach(function (select) {
            select.ekSelectPending = false;
            refresh(select);
        });
    };
    var schedule = function (select) {
        if (select.ekSelectPending) {
            return;
        }
        select.ekSelectPending = true;
        if (pending.push(select) === 1) {
            Promise.resolve().then(flush);
        }
    };

    // select.value = … / select.selectedIndex = … fire no event: watch them on each select.
    var watchSetter = function (select, name, descriptor) {
        Object.defineProperty(select, name, {
            configurable: true,
            enumerable: true,
            get: function () { return descriptor.get.call(this); },
            set: function (v) {
                descriptor.set.call(this, v);
                schedule(this);
            },
        });
    };

    // A select copied with its Tom Select markup (cloneNode of an enhanced block): drop the copy's
    // dead wrapper so it can be enhanced on its own.
    var cleanCopy = function (select) {
        if (!select.classList.contains('tomselected')) {
            return;
        }
        var next = select.nextElementSibling;
        if (next && next.classList.contains('ts-wrapper')) {
            next.remove();
        }
        select.classList.remove('tomselected', 'ts-hidden-accessible');
    };

    // Next (step 1) / previous (step -1) focusable field after a control, in document order, within
    // the open modal if any (Bootstrap traps the focus there).
    var FOCUSABLE = 'a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]';
    var neighbour = function (from, step) {
        var scope = from.closest('.modal.show') || document;
        var list = Array.prototype.filter.call(scope.querySelectorAll(FOCUSABLE), function (el) {
            return el === from || (!el.disabled && el.tabIndex >= 0 && !el.closest('.ts-dropdown')
                && !el.classList.contains('tomselected') && el.getClientRects().length > 0
                && getComputedStyle(el).visibility !== 'hidden');
        });
        var i = list.indexOf(from);
        return i === -1 ? null : list[i + step] || null;
    };

    var enhance = function (select) {
        if (select.tomselect || !eligible(select)) {
            return;
        }
        cleanCopy(select);

        // Inside a modal the list stays in the modal (Bootstrap keeps the focus there); elsewhere a
        // list in a table cell or a scrolling box is attached to <body> so it isn't clipped.
        var clipped = !select.closest('.modal') && !!select.closest('td, .table-responsive, .dt-container');
        // A native select sized by its content (width: auto, flex item) is as wide as its longest
        // option; the control is only as wide as the chosen one: keep the native width.
        var nativeWidth = select.offsetWidth;
        // data-allow-create: a new value typed in the search (trimmed, 60 characters at most), unless
        // an option already has that name whatever the case (« boissons » → « Boissons »).
        var allowCreate = select.hasAttribute('data-allow-create');
        var clean = function (input) { return String(input || '').replace(/\s+/g, ' ').trim().slice(0, 60); };
        var exists = function (text) {
            var wanted = text.toLocaleLowerCase('fr');
            return Array.prototype.some.call(select.options, function (option) {
                return option.value !== '' && option.value.toLocaleLowerCase('fr') === wanted;
            });
        };
        var ts;
        try {
            ts = new window.TomSelect(select, {
                plugins: ['dropdown_input', 'no_backspace_delete', 'ek_in_place'],
                allowEmptyOption: true,
                create: allowCreate ? function (input) {
                    var text = clean(input);
                    return text === '' || exists(text) ? false : { value: text, text: text };
                } : false,
                createFilter: allowCreate ? function (input) {
                    var text = clean(input);
                    return text !== '' && !exists(text);
                } : null,
                maxItems: 1,
                maxOptions: null,
                closeAfterSelect: true,
                refreshThrottle: 50, // local lists: filter as the user types
                dropdownParent: clipped ? 'body' : null,
                render: {
                    no_results: function () { return '<div class="no-results">Aucun résultat</div>'; },
                    option_create: function (data, escape) {
                        return '<div class="create">Ajouter « <strong>' + escape(clean(data.input)) + '</strong> »</div>';
                    },
                },
            });
        } catch (e) {
            return; // the native select stays usable
        }

        if (allowCreate) {
            // The created value must exist in the <select> to be posted (ek_in_place writes the
            // value with the native setter, which ignores unknown values).
            ts.on('option_add', function (value, data) {
                var known = Array.prototype.some.call(select.options, function (option) { return option.value === String(value); });
                if (!known) {
                    select.appendChild(new Option(data && data.text ? data.text : String(value), String(value)));
                }
            });
        }
        ts.wrapper.classList.add('ek-select');
        if (nativeWidth > 0 && ts.wrapper.offsetWidth < nativeWidth - 1) {
            ts.wrapper.style.minWidth = nativeWidth + 'px';
        }
        ts.dropdown.classList.add('ek-select__dropdown');
        ts.control_input.setAttribute('placeholder', 'Rechercher…');
        ts.control_input.setAttribute('autocomplete', 'off');
        // Selects named by aria-label only (no <label for>): give the name to the visible control.
        var name = select.getAttribute('aria-label');
        if (name && !ts.focus_node.hasAttribute('aria-labelledby')) {
            ts.focus_node.setAttribute('aria-label', name);
        }
        ts.control_input.setAttribute('aria-label', 'Rechercher' + (name ? ' — ' + name : ''));
        // [Tab] from the search field: Tom Select hides it while the browser moves the focus, which
        // lands back on the same control (3 presses per field). Move to the next/previous field
        // ourselves, like a native select (capture: runs before Tom Select's own keydown).
        ts.control_input.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            var target = neighbour(ts.focus_node, event.shiftKey ? -1 : 1);
            ts.close();
            ts.blur();
            if (target) {
                target.focus();
            }
        }, true);
        // Letters typed on the control itself (closed list, or right after a click before the search
        // field gets the focus) go to the search, like typing on a native select.
        ts.focus_node.addEventListener('keydown', function (event) {
            if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey || ts.isDisabled || event.key === ' ') {
                return;
            }
            if (!ts.isOpen) {
                ts.open();
            }
            ts.control_input.focus(); // the typed character lands in the search field
        });

        watchSetter(select, 'value', nativeValue);
        watchSetter(select, 'selectedIndex', nativeIndex);
        new MutationObserver(function () { schedule(select); }).observe(select, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['disabled', 'value', 'label', 'selected'],
        });
    };

    var enhanceWithin = function (root) {
        if (root.tagName === 'SELECT') {
            enhance(root);
        } else if (root.querySelectorAll) {
            Array.prototype.forEach.call(root.querySelectorAll('select'), enhance);
        }
    };

    var resyncAll = function () {
        Array.prototype.forEach.call(document.querySelectorAll('select.tomselected'), function (select) {
            if (select.tomselect) {
                refresh(select);
            }
        });
    };

    enhanceWithin(document);

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                if (node.nodeType === 1 && !node.classList.contains('ts-wrapper') && !node.classList.contains('ts-dropdown')) {
                    enhanceWithin(node);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });

    // form.reset() restores the selects after the event: resync once it's done.
    document.addEventListener('reset', function (event) {
        var form = event.target;
        setTimeout(function () {
            Array.prototype.forEach.call(form.querySelectorAll('select'), refresh);
        }, 0);
    }, true);
    // The browser may restore form values on Back (bfcache, form state restoration).
    window.addEventListener('pageshow', resyncAll);

    window.EkSelect = { refresh: refresh, enhance: enhance };
})();
