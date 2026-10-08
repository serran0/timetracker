/* Timetracker – shared behaviour. No inline scripts are used (strict CSP). */
(() => {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // Translations come from the server (see I18n::forJs); the English text is the key.
    let i18n = { locale: 'en', decimal: '.', dow: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], strings: {} };
    try { i18n = JSON.parse((document.getElementById('tt-i18n') || {}).textContent || '{}'); } catch (e) { /* keep defaults */ }

    window.TT = {
        i18n,
        /** Translate a key and fill {placeholders}. */
        t(key, vars) {
            let s = (i18n.strings && i18n.strings[key]) || key;
            Object.keys(vars || {}).forEach((k) => { s = s.split('{' + k + '}').join(String(vars[k])); });
            return s;
        },
        /** 7.5 -> "7.50" / "7,50" */
        dec(n) { return n.toFixed(2).replace('.', i18n.decimal || '.'); },
        csrf: () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
        toast(message, ms = 3500) {
            const el = document.createElement('div');
            el.className = 'toast';
            el.setAttribute('role', 'status');
            el.textContent = message;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), ms);
        },
    };

    // Mobile navigation
    const toggle = $('[data-nav-toggle]');
    const nav = $('[data-nav]');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    // Confirm destructive buttons
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-confirm]');
        if (btn && !window.confirm(btn.dataset.confirm)) {
            e.preventDefault();
        }
    });

    // Close dropdown <details> when clicking elsewhere
    document.addEventListener('click', (e) => {
        $$('details.multi[open], details.user-menu[open], details.inline-details[open]').forEach((d) => {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });

    // Colour swatches: a click sets the colour input; the swatch matching the current colour is ringed
    const markSwatches = (input) => {
        const row = input.closest('.color-row');
        if (!row) return;
        $$('.swatch', row).forEach((s) => s.classList.toggle('is-active', s.dataset.color.toLowerCase() === input.value.toLowerCase()));
    };
    document.addEventListener('click', (e) => {
        const sw = e.target.closest('.swatch');
        if (!sw) return;
        const input = sw.closest('label').querySelector('input[type=color]');
        if (input) { input.value = sw.dataset.color; markSwatches(input); }
    });
    document.addEventListener('input', (e) => {
        if (e.target.matches && e.target.matches('input[type=color]')) markSwatches(e.target);
    });

    // Auto-submit filters
    document.addEventListener('change', (e) => {
        const el = e.target.closest('[data-autosubmit]');
        if (el && el.form) el.form.submit();
    });

    // Period presets (fills the from/to fields of the same form)
    document.addEventListener('change', (e) => {
        const sel = e.target.closest('[data-preset-select]');
        if (!sel || !sel.value) return;
        const [from, to] = sel.value.split('|');
        const form = sel.form;
        form.elements.from.value = from;
        form.elements.to.value = to;
        if (form.hasAttribute('data-filterbar')) form.submit();
    });

    // Working hours editor
    const whForm = $('[data-wh-form]');
    if (whForm) {
        let counter = 1000;
        const rowHtml = (day, start = '', end = '') => {
            const i = counter++;
            const wrap = document.createElement('div');
            wrap.className = 'wh-row';
            wrap.innerHTML =
                `<input type="text" name="wh[${day}][${i}][start]" placeholder="08:00" inputmode="numeric" maxlength="5" aria-label="Start time" list="time-options">` +
                '<span class="muted">–</span>' +
                `<input type="text" name="wh[${day}][${i}][end]" placeholder="17:00" inputmode="numeric" maxlength="5" aria-label="End time" list="time-options">` +
                '<button type="button" class="icon-btn" data-wh-remove aria-label="Remove interval" title="Remove">×</button>';
            const inputs = wrap.querySelectorAll('input');
            inputs[0].value = start;
            inputs[1].value = end;
            return wrap;
        };
        whForm.addEventListener('click', (e) => {
            const day = e.target.closest('.wh-day');
            if (!day) return;
            const rows = $('[data-wh-rows]', day);
            if (e.target.closest('[data-wh-add]')) {
                rows.appendChild(rowHtml(day.dataset.day));
            } else if (e.target.closest('[data-wh-remove]')) {
                e.target.closest('.wh-row').remove();
                if (!rows.children.length) rows.appendChild(rowHtml(day.dataset.day));
            } else if (e.target.closest('[data-wh-copy]')) {
                const source = $$('.wh-row', rows).map((r) => $$('input', r).map((i) => i.value.trim())).filter(([s, en]) => s || en);
                [2, 3, 4, 5].forEach((d) => {
                    const target = $(`.wh-day[data-day="${d}"] [data-wh-rows]`, whForm);
                    target.innerHTML = '';
                    source.forEach(([s, en]) => target.appendChild(rowHtml(d, s, en)));
                    target.appendChild(rowHtml(d));
                });
                window.TT.toast(window.TT.t('Copied Monday to Tuesday–Friday. Remember to save.'));
            }
        });
    }
})();

// Calendar filter bar: month picker (year + month grid) and week picker (whole-week rows with week numbers).
(() => {
    'use strict';
    const T = window.TT;
    const i18n = T.i18n;
    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parse = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
    const monday = (d) => addDays(d, -((d.getDay() + 6) % 7));
    // ISO 8601 week number: the week belongs to the year of its Thursday.
    const isoWeek = (d) => {
        // UTC arithmetic: local-time differences are an hour short across daylight saving and round down a week.
        const th = addDays(monday(d), 3);
        const days = (Date.UTC(th.getFullYear(), th.getMonth(), th.getDate()) - Date.UTC(th.getFullYear(), 0, 1)) / 864e5;
        return Math.floor(days / 7) + 1;
    };
    const mondayFirst = [1, 2, 3, 4, 5, 6, 0].map((i) => (i18n.dow || [])[i] || '');

    const el = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    };

    document.querySelectorAll('[data-datepick]').forEach((root) => {
        const mode = root.dataset.datepick;
        const input = root.querySelector('input[name=date]');
        const toggle = root.querySelector('[data-dp-toggle]');
        const selected = parse(input.value);
        const todayD = new Date();
        const today = new Date(todayD.getFullYear(), todayD.getMonth(), todayD.getDate());
        let view = new Date(selected.getFullYear(), selected.getMonth(), 1);
        let pop = null;

        const pick = (d) => {
            input.value = iso(d);
            if (input.form) input.form.submit();
        };
        const nav = (label, onClick) => {
            const b = el('button', 'dp-nav', label === 'prev' ? '‹' : '›');
            b.type = 'button';
            b.setAttribute('aria-label', T.t(label === 'prev' ? 'Previous' : 'Next'));
            b.addEventListener('click', onClick);
            return b;
        };

        const render = () => {
            pop.textContent = '';
            const head = el('div', 'dp-head');
            if (mode === 'month') {
                head.append(nav('prev', () => { view = new Date(view.getFullYear() - 1, 0, 1); render(); }),
                    el('strong', 'dp-title', String(view.getFullYear())),
                    nav('next', () => { view = new Date(view.getFullYear() + 1, 0, 1); render(); }));
                pop.appendChild(head);
                const grid = el('div', 'dp-months');
                (i18n.monthsShort || []).forEach((name, m) => {
                    const b = el('button', 'dp-month', name);
                    b.type = 'button';
                    b.title = (i18n.months || [])[m] + ' ' + view.getFullYear();
                    if (view.getFullYear() === selected.getFullYear() && m === selected.getMonth()) b.classList.add('is-selected');
                    if (view.getFullYear() === today.getFullYear() && m === today.getMonth()) b.classList.add('is-today');
                    b.addEventListener('click', () => pick(new Date(view.getFullYear(), m, 1)));
                    grid.appendChild(b);
                });
                pop.appendChild(grid);
            } else {
                head.append(nav('prev', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); render(); }),
                    el('strong', 'dp-title', (i18n.months || [])[view.getMonth()] + ' ' + view.getFullYear()),
                    nav('next', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); render(); }));
                pop.appendChild(head);
                const table = el('table', 'dp-weeks');
                const hr = el('tr');
                hr.appendChild(el('th', 'dp-wk', T.t('Wk')));
                mondayFirst.forEach((n) => hr.appendChild(el('th', '', n)));
                const thead = el('thead');
                thead.appendChild(hr);
                table.appendChild(thead);
                const tbody = el('tbody');
                const selMonday = iso(monday(selected));
                const lastMonday = monday(new Date(view.getFullYear(), view.getMonth() + 1, 0));
                for (let start = monday(view); start <= lastMonday; start = addDays(start, 7)) {
                    const tr = el('tr', 'dp-week');
                    if (iso(start) === selMonday) tr.classList.add('is-selected');
                    const wk = el('td', 'dp-wk');
                    const wb = el('button', 'dp-day', String(isoWeek(start)));
                    wb.type = 'button';
                    wb.setAttribute('aria-label', T.t('Week {n}', { n: isoWeek(start) }));
                    wk.appendChild(wb);
                    tr.appendChild(wk);
                    const rowMonday = start;
                    for (let i = 0; i < 7; i++) {
                        const d = addDays(rowMonday, i);
                        const td = el('td');
                        const b = el('button', 'dp-day', String(d.getDate()));
                        b.type = 'button';
                        if (d.getMonth() !== view.getMonth()) b.classList.add('is-other');
                        if (iso(d) === iso(today)) b.classList.add('is-today');
                        td.appendChild(b);
                        tr.appendChild(td);
                    }
                    tr.addEventListener('click', () => pick(rowMonday));
                    tbody.appendChild(tr);
                }
                table.appendChild(tbody);
                pop.appendChild(table);
            }
            const foot = el('div', 'dp-foot');
            const tb = el('button', 'btn btn-sm', T.t('Today'));
            tb.type = 'button';
            tb.addEventListener('click', () => pick(today));
            foot.appendChild(tb);
            pop.appendChild(foot);
        };

        const close = () => {
            if (!pop) return;
            pop.remove();
            pop = null;
            toggle.setAttribute('aria-expanded', 'false');
        };
        const open = () => {
            view = new Date(selected.getFullYear(), selected.getMonth(), 1);
            pop = el('div', 'dp-pop');
            pop.setAttribute('role', 'dialog');
            root.appendChild(pop);
            toggle.setAttribute('aria-expanded', 'true');
            render();
        };
        toggle.addEventListener('click', () => (pop ? close() : open()));
        document.addEventListener('click', (e) => { if (pop && e.target.isConnected && !root.contains(e.target)) close(); });
        document.addEventListener('keydown', (e) => { if (pop && e.key === 'Escape') { close(); toggle.focus(); } });
    });
})();

// Export page: remember the format options as soon as one is changed (no need to press Update preview or Download).
(() => {
    'use strict';
    const box = document.querySelector('[data-export-prefs]');
    if (!box) return;
    const state = box.querySelector('[data-save-state]');
    const T = window.TT;
    let timer = null;
    let fade = null;
    const collect = () => {
        const val = (name) => (box.querySelector(`[name="${name}"]`) || {}).value;
        const checked = box.querySelector('input[name=format]:checked');
        return {
            format: checked ? checked.value : '',
            cols: Array.from(box.querySelectorAll('input[name="cols[]"]:checked')).map((i) => i.value),
            duration: val('duration'),
            delimiter: val('delimiter'),
            decimal: val('decimal'),
            totals: !!(box.querySelector('input[name=totals]') || {}).checked,
            vat: !!(box.querySelector('input[name=vat]') || {}).checked,
        };
    };
    const save = async () => {
        try {
            const res = await fetch('api/export_prefs.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': T.csrf() },
                body: JSON.stringify(collect()),
            });
            const data = await res.json();
            if (!data.ok) throw new Error('save failed');
            state.textContent = '✓ ' + T.t('Saved');
            clearTimeout(fade);
            fade = setTimeout(() => { state.textContent = ''; }, 2500);
        } catch (e) {
            state.textContent = '';
            T.toast(T.t('Could not save.'));
        }
    };
    box.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(save, 250); // several quick ticks become one request
    });
})();
