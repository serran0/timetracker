/* Timetracker – calendar interactions: drag to create, right-click menus, entry dialog. */
(() => {
    'use strict';

    const dialog = document.getElementById('entry-dialog');
    if (!dialog) return;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const form = document.getElementById('entry-form');
    const errorsBox = document.getElementById('entry-errors');
    const deleteBtn = document.getElementById('entry-delete');
    const saveBtn = document.getElementById('entry-save');
    const titleEl = document.getElementById('entry-title');
    const durationEl = document.getElementById('entry-duration');
    const view = ($('.cal-body') || {}).dataset ? $('.cal-body').dataset.view : '';

    const pad = (n) => String(n).padStart(2, '0');
    const toHHMM = (min) => `${pad(Math.floor(min / 60))}:${pad(min % 60)}`;
    const fmtDur = (min) => `${Math.floor(min / 60)}:${pad(min % 60)}`;

    /** Same rules as the server: "9", "9:30", "0930", "09.30", "24:00". */
    function parseTime(str) {
        const m = String(str).trim().match(/^(\d{1,2})[:.]?(\d{2})$|^(\d{1,2})$/);
        if (!m) return null;
        const h = m[3] !== undefined ? parseInt(m[3], 10) : parseInt(m[1], 10);
        const min = m[3] !== undefined ? 0 : parseInt(m[2], 10);
        if (min > 59 || h > 24 || (h === 24 && min > 0)) return null;
        return h * 60 + min;
    }

    const store = {
        get(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* ignore */ } },
    };

    /* ---------------------------------------------------------------- dialog */

    function showErrors(list) {
        errorsBox.hidden = !list || !list.length;
        errorsBox.innerHTML = '';
        (list || []).forEach((msg) => {
            const p = document.createElement('div');
            p.textContent = msg;
            errorsBox.appendChild(p);
        });
    }

    function updateDuration() {
        const s = parseTime(form.start.value);
        const e = parseTime(form.end.value);
        if (s !== null && e !== null && e > s) {
            durationEl.textContent = `Duration ${fmtDur(e - s)} (${((e - s) / 60).toFixed(2)} h)`;
        } else {
            durationEl.textContent = '';
        }
    }

    function selectDefault(select, storeKey, preferred) {
        const options = Array.from(select.options);
        const wanted = preferred || store.get(storeKey);
        const match = options.find((o) => o.value === String(wanted) && !o.parentElement.label);
        const firstActive = options.find((o) => !o.parentElement.label);
        select.value = (match || firstActive || options[0] || {}).value || '';
    }

    /** Opens the dialog. `data` may contain id (edit) or not (create). */
    function openDialog(data) {
        if (dialog.dataset.ready !== '1') {
            window.TT.toast('Add at least one client and one action first.');
            return;
        }
        showErrors([]);
        const editing = !!data.id;
        titleEl.textContent = editing ? 'Edit time report' : 'New time report';
        deleteBtn.hidden = !editing;
        form.id.value = editing ? data.id : '';
        form.date.value = data.date;
        form.start.value = data.start;
        form.end.value = data.end;
        form.description.value = data.description || '';
        selectDefault(form.client_id, 'tt_client', data.client_id);
        selectDefault(form.action_id, 'tt_action', data.action_id);
        updateDuration();
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        (editing ? form.description : form.client_id).focus();
    }

    function openNew(date, startMin, endMin) {
        openDialog({ date, start: toHHMM(startMin), end: toHHMM(endMin) });
    }

    function closeDialog() {
        if (dialog.open) dialog.close();
    }

    async function post(payload) {
        const res = await fetch('api/entry.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.TT.csrf() },
            body: JSON.stringify(payload),
        });
        if (res.status === 401) {
            window.location.href = 'login.php';
            return { ok: false, errors: ['Session expired.'] };
        }
        try {
            return await res.json();
        } catch (e) {
            return { ok: false, errors: ['Unexpected server response.'] };
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const s = parseTime(form.start.value);
        const en = parseTime(form.end.value);
        if (s === null || en === null) return showErrors(['Enter times as HH:MM (e.g. 08:30).']);
        if (en <= s) return showErrors(['End time must be after the start time.']);
        saveBtn.disabled = true;
        const result = await post({
            op: 'save',
            id: form.id.value ? parseInt(form.id.value, 10) : null,
            date: form.date.value,
            start: toHHMM(s),
            end: toHHMM(en),
            client_id: parseInt(form.client_id.value, 10) || 0,
            action_id: parseInt(form.action_id.value, 10) || 0,
            description: form.description.value,
        });
        saveBtn.disabled = false;
        if (result.ok) {
            store.set('tt_client', form.client_id.value);
            store.set('tt_action', form.action_id.value);
            window.location.reload();
        } else {
            showErrors(result.errors || ['Could not save.']);
        }
    });

    async function deleteEntry(id) {
        const result = await post({ op: 'delete', id });
        if (result.ok) window.location.reload();
        else window.TT.toast((result.errors || ['Could not delete.'])[0]);
    }

    deleteBtn.addEventListener('click', () => {
        if (form.id.value && window.confirm('Delete this time report?')) deleteEntry(parseInt(form.id.value, 10));
    });
    $$('[data-dialog-close]', dialog).forEach((b) => b.addEventListener('click', closeDialog));
    dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });
    ['start', 'end'].forEach((name) => {
        form[name].addEventListener('input', updateDuration);
        form[name].addEventListener('blur', () => {
            const m = parseTime(form[name].value);
            if (m !== null && (name === 'end' || m < 1440)) form[name].value = toHHMM(m);
            updateDuration();
        });
    });

    /* ----------------------------------------------------------- context menu */

    let menu = null;
    let menuOpenedAt = 0;
    function hideMenu() {
        if (menu) { menu.remove(); menu = null; }
    }
    function showMenu(x, y, title, items) {
        hideMenu();
        menu = document.createElement('div');
        menu.className = 'ctx';
        menu.setAttribute('role', 'menu');
        if (title) {
            const t = document.createElement('div');
            t.className = 'ctx-title';
            t.textContent = title;
            menu.appendChild(t);
        }
        items.forEach((item) => {
            if (item === '-') { menu.appendChild(document.createElement('hr')); return; }
            const b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('role', 'menuitem');
            b.textContent = item.label;
            if (item.danger) b.className = 'danger';
            b.addEventListener('click', () => { hideMenu(); item.run(); });
            menu.appendChild(b);
        });
        document.body.appendChild(menu);
        const r = menu.getBoundingClientRect();
        menu.style.left = Math.max(6, Math.min(x, window.innerWidth - r.width - 6)) + 'px';
        menu.style.top = Math.max(6, Math.min(y, window.innerHeight - r.height - 6)) + 'px';
        menuOpenedAt = Date.now();
        const first = menu.querySelector('button');
        if (first) first.focus({ preventScroll: true });
    }
    document.addEventListener('click', (e) => { if (menu && !menu.contains(e.target)) hideMenu(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideMenu(); });
    window.addEventListener('scroll', hideMenu, true);
    window.addEventListener('resize', hideMenu);

    /* --------------------------------------------------------- entry actions */

    const payloadOf = (el) => JSON.parse(el.dataset.entry);

    function entryMenu(x, y, el) {
        const p = payloadOf(el);
        showMenu(x, y, `${p.start}–${p.end} · ${p.date}`, [
            { label: 'Edit time report', run: () => openDialog(p) },
            { label: 'Duplicate', run: () => openDialog({ ...p, id: null }) },
            '-',
            { label: 'Delete', danger: true, run: () => { if (window.confirm('Delete this time report?')) deleteEntry(p.id); } },
        ]);
    }

    // Click an entry to edit it
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-entry]');
        if (el && !e.target.closest('.ctx')) { e.preventDefault(); openDialog(payloadOf(el)); }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches && e.target.matches('tr[data-entry]')) openDialog(payloadOf(e.target));
    });

    // "+ New report" button
    $$('[data-new-entry]').forEach((btn) => btn.addEventListener('click', () => {
        const dayTrack = $('.cal-body[data-view="day"] .tl-track');
        openNew(dayTrack ? dayTrack.dataset.date : btn.dataset.date, 9 * 60, 10 * 60);
    }));

    /* ------------------------------------------------- week / day timeline */

    const parseWh = (el) => (el.dataset.wh || '').split(',').filter(Boolean).map((s) => s.split('-').map(Number));
    const hourAt = (track, clientX) => {
        const r = track.getBoundingClientRect();
        const f = Math.min(0.99999, Math.max(0, (clientX - r.left) / r.width));
        return Math.floor(f * 24);
    };

    function trackMenu(x, y, track, hour) {
        const date = track.dataset.date;
        const items = [{ label: `New time report ${toHHMM(hour * 60)}–${toHHMM((hour + 1) * 60)}`, run: () => openNew(date, hour * 60, (hour + 1) * 60) }];
        parseWh(track).forEach(([s, e]) => items.push({
            label: `New report for working hours ${toHHMM(s)}–${toHHMM(e)}`,
            run: () => openNew(date, s, e),
        }));
        if (view !== 'day') {
            items.push('-', { label: 'Open day', run: () => { window.location.href = dayUrl(date); } });
        }
        showMenu(x, y, track.dataset.label, items);
    }
    const dayUrl = (date) => {
        const u = new URL(window.location.href);
        u.searchParams.set('view', 'day');
        u.searchParams.set('date', date);
        u.searchParams.delete('from');
        u.searchParams.delete('to');
        return u.pathname.split('/').pop() + u.search;
    };

    let drag = null;
    document.addEventListener('pointerdown', (e) => {
        const track = e.target.closest('.tl-track');
        if (!track || e.target.closest('.te')) return;
        if (e.pointerType === 'touch') {
            drag = { touch: true, track, x: e.clientX, y: e.clientY, t: Date.now() };
            return;
        }
        if (e.button !== 0) return;
        hideMenu();
        const h = hourAt(track, e.clientX);
        const sel = document.createElement('div');
        sel.className = 'tl-sel';
        track.appendChild(sel);
        drag = { track, from: h, to: h, sel, pointerId: e.pointerId };
        paintSel();
        try { track.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
        e.preventDefault();
    });

    function paintSel() {
        const a = Math.min(drag.from, drag.to);
        const b = Math.max(drag.from, drag.to);
        drag.sel.style.left = (a / 24 * 100) + '%';
        drag.sel.style.width = ((b - a + 1) / 24 * 100) + '%';
        drag.sel.textContent = `${toHHMM(a * 60)}–${toHHMM((b + 1) * 60)}`;
    }

    document.addEventListener('pointermove', (e) => {
        if (!drag || drag.touch) return;
        const h = hourAt(drag.track, e.clientX);
        if (h !== drag.to) { drag.to = h; paintSel(); }
    });

    document.addEventListener('pointerup', (e) => {
        if (!drag) return;
        const d = drag;
        drag = null;
        if (d.touch) {
            const moved = Math.hypot(e.clientX - d.x, e.clientY - d.y) > 10;
            if (!moved && Date.now() - d.t < 600 && Date.now() - menuOpenedAt > 700) {
                trackMenu(e.clientX, e.clientY, d.track, hourAt(d.track, e.clientX));
            }
            return;
        }
        d.sel.remove();
        const a = Math.min(d.from, d.to);
        const b = Math.max(d.from, d.to);
        if (a !== b) openNew(d.track.dataset.date, a * 60, (b + 1) * 60); // drag across several hours
    });
    document.addEventListener('pointercancel', () => {
        if (drag && drag.sel) drag.sel.remove();
        drag = null;
    });

    document.addEventListener('dblclick', (e) => {
        // Double-click-to-create is a mouse shortcut; on touch screens it would fire after menu taps.
        if (window.matchMedia('(pointer: coarse)').matches || Date.now() - menuOpenedAt < 1000 || e.target.closest('.ctx')) return;
        const track = e.target.closest('.tl-track');
        if (track && !e.target.closest('.te')) {
            const h = hourAt(track, e.clientX);
            openNew(track.dataset.date, h * 60, (h + 1) * 60);
            return;
        }
        const day = e.target.closest('.mv-day');
        if (day && !e.target.closest('a, button')) openNew(day.dataset.date, 9 * 60, 10 * 60);
    });

    /* ----------------------------------------------------------- month view */

    function dayMenu(x, y, day) {
        const date = day.dataset.date;
        const items = parseWh(day).map(([s, e]) => ({
            label: `New report for working hours ${toHHMM(s)}–${toHHMM(e)}`,
            run: () => openNew(date, s, e),
        }));
        items.unshift({ label: 'New time report…', run: () => openNew(date, 9 * 60, 10 * 60) });
        items.push('-',
            { label: 'Open day', run: () => { window.location.href = day.dataset.dayUrl; } },
            { label: 'Open week', run: () => { window.location.href = day.dataset.weekUrl; } });
        showMenu(x, y, day.dataset.label, items);
    }

    // Right click anywhere in the calendar
    document.addEventListener('contextmenu', (e) => {
        const entry = e.target.closest('[data-entry]');
        if (entry && !entry.closest('dialog')) {
            e.preventDefault();
            return entryMenu(e.clientX, e.clientY, entry);
        }
        const track = e.target.closest('.tl-track');
        if (track) {
            e.preventDefault();
            if (drag && drag.sel) { drag.sel.remove(); drag = null; }
            return trackMenu(e.clientX, e.clientY, track, hourAt(track, e.clientX));
        }
        const day = e.target.closest('.mv-day');
        if (day) {
            e.preventDefault();
            dayMenu(e.clientX, e.clientY, day);
        }
    });

    // Touch: tap a day to open it, press and hold for the menu
    let press = null;
    document.addEventListener('pointerdown', (e) => {
        const day = e.target.closest('.mv-day');
        if (!day || e.pointerType !== 'touch' || e.target.closest('a, button')) return;
        press = { day, x: e.clientX, y: e.clientY, fired: false };
        press.timer = setTimeout(() => { press.fired = true; dayMenu(press.x, press.y, day); }, 550);
    });
    document.addEventListener('pointermove', (e) => {
        if (press && Math.hypot(e.clientX - press.x, e.clientY - press.y) > 10) { clearTimeout(press.timer); press = null; }
    });
    document.addEventListener('pointerup', () => {
        if (!press) return;
        clearTimeout(press.timer);
        const p = press;
        press = null;
        if (!p.fired && Date.now() - menuOpenedAt > 700) window.location.href = p.day.dataset.dayUrl;
    });
    document.addEventListener('pointercancel', () => { if (press) { clearTimeout(press.timer); press = null; } });

    // Scroll the timeline so that working hours are in view on narrow screens
    const scroller = $('[data-tl-scroll]');
    if (scroller) {
        const frac = parseFloat(scroller.dataset.scrollTo || '0.33');
        const track = $('.tl-track', scroller);
        const label = $('.tl-label', scroller);
        if (track && scroller.scrollWidth > scroller.clientWidth) {
            const left = track.getBoundingClientRect().left - scroller.getBoundingClientRect().left + scroller.scrollLeft;
            scroller.scrollLeft = Math.max(0, left + frac * track.offsetWidth - (label ? label.offsetWidth : 0) - 8);
        }
    }
})();
