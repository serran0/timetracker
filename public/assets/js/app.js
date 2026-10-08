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

    // Colour swatches
    document.addEventListener('click', (e) => {
        const sw = e.target.closest('.swatch');
        if (!sw) return;
        const input = sw.closest('label').querySelector('input[type=color]');
        if (input) input.value = sw.dataset.color;
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
