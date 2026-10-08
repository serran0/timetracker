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

    const tr = (key, vars) => window.TT.t(key, vars);
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

    /** Lunch window [startMin, endMin] from the dialog's data attribute, or null. */
    const lunch = (() => {
        const m = (dialog.dataset.lunch || '').match(/^(\d+)-(\d+)$/);
        return m ? [parseInt(m[1], 10), parseInt(m[2], 10)] : null;
    })();
    const overlap = (a1, a2, b1, b2) => Math.max(0, Math.min(a2, b2) - Math.max(a1, b1));
    const lunchBreak = (s, e) => (lunch && s !== null && e !== null ? overlap(s, e, lunch[0], lunch[1]) : 0);

    /**
     * The "whole working day" report for a day's working intervals: first start to last end, with the time
     * between intervals plus the lunch window (inside working time) taken off as unpaid break.
     */
    function wholeDay(wh) {
        if (!wh.length) return null;
        const start = Math.min(...wh.map((i) => i[0]));
        const end = Math.max(...wh.map((i) => i[1]));
        const net = wh.reduce((sum, [s, e]) => sum + (e - s) - lunchBreak(s, e), 0);
        return { start, end, brk: Math.max(0, end - start - net) };
    }

    const store = {
        get(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* ignore */ } },
    };

    /* ---------------------------------------------------------------- dialog */

    const allActions = JSON.parse((document.getElementById('entry-actions') || { textContent: '[]' }).textContent);
    const rowsBox = document.getElementById('entry-rows');
    const rowTpl = document.getElementById('entry-row-tpl');
    const daysBox = document.getElementById('entry-days');
    const addDayBtn = document.getElementById('entry-add-day');
    const noActions = document.getElementById('entry-no-actions');
    const DOW = window.TT.i18n.dow; // localized, index = JS getDay()

    function showErrors(list) {
        errorsBox.hidden = !list || !list.length;
        errorsBox.innerHTML = '';
        (list || []).forEach((msg) => {
            const p = document.createElement('div');
            p.textContent = msg;
            errorsBox.appendChild(p);
        });
        if (list && list.length) errorsBox.scrollIntoView({ block: 'nearest' });
    }

    const parseDate = (str) => {
        const m = String(str).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    };
    const fmtDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    /* --- client -> actions (actions are per client) --- */

    function refreshActions(preferredActionId) {
        const clientId = form.client_id.value;
        const list = allActions.filter((a) => String(a.client_id) === String(clientId));
        const select = form.action_id;
        select.innerHTML = '';
        const add = (a, parent) => {
            const o = document.createElement('option');
            o.value = a.id;
            o.textContent = a.name;
            parent.appendChild(o);
        };
        list.filter((a) => !a.archived).forEach((a) => add(a, select));
        const keepArchived = list.filter((a) => a.archived && String(a.id) === String(preferredActionId));
        if (keepArchived.length) {
            const g = document.createElement('optgroup');
            g.label = tr('Archived');
            keepArchived.forEach((a) => add(a, g));
            select.appendChild(g);
        }
        const none = !select.options.length;
        noActions.hidden = !none;
        if (none) {
            const o = document.createElement('option');
            o.value = '';
            o.textContent = tr('— no actions —');
            select.appendChild(o);
            document.getElementById('entry-no-actions-link').href = 'actions.php?client=' + encodeURIComponent(clientId);
            return;
        }
        const wanted = preferredActionId || store.get('tt_action_' + clientId);
        const match = Array.from(select.options).find((o) => o.value === String(wanted));
        select.value = (match || select.options[0]).value;
    }

    function selectClient(preferred) {
        const options = Array.from(form.client_id.options);
        const wanted = preferred || store.get('tt_client');
        const match = options.find((o) => o.value === String(wanted) && !o.parentElement.label);
        const firstActive = options.find((o) => !o.parentElement.label);
        form.client_id.value = (match || firstActive || options[0] || {}).value || '';
    }

    form.client_id.addEventListener('change', () => refreshActions(null));

    /* --- day rows --- */

    const rowField = (row, name) => row.querySelector(`[data-f="${name}"]`);

    function rowValues(row) {
        const s = parseTime(rowField(row, 'start').value);
        const e = parseTime(rowField(row, 'end').value);
        const brk = parseInt(rowField(row, 'break').value, 10) || 0;
        return { s, e, brk, date: rowField(row, 'date').value, include: rowField(row, 'include').checked };
    }

    function refreshRow(row) {
        const { s, e, brk, date } = rowValues(row);
        const net = rowField(row, 'net');
        if (s !== null && e !== null && e > s && brk < e - s) {
            net.textContent = fmtDur(e - s - brk);
            net.classList.remove('bad');
        } else {
            net.textContent = '–';
            net.classList.add('bad');
        }
        const d = parseDate(date);
        rowField(row, 'dow').textContent = d ? DOW[d.getDay()] : '';
        row.classList.toggle('off', !rowField(row, 'include').checked);
    }

    function updateTotals() {
        const rows = Array.from(rowsBox.children);
        const multi = rows.length > 1;
        daysBox.classList.toggle('multi', multi);
        let net = 0;
        let n = 0;
        rows.forEach((row) => {
            refreshRow(row);
            const v = rowValues(row);
            if (v.include && v.s !== null && v.e !== null && v.e > v.s && v.brk < v.e - v.s) {
                net += v.e - v.s - v.brk;
                n += 1;
            }
        });
        const editing = !!form.id.value;
        titleEl.textContent = editing ? tr('Edit time report') : (multi ? tr('New time reports') : tr('New time report'));
        durationEl.textContent = n ? `${tr(n === 1 ? '{n} report' : '{n} reports', { n })} · ${tr('{h} h net', { h: fmtDur(net) })} (${window.TT.dec(net / 60)} h)` : '';
    }

    /** Adds a day row. `v`: {date, start, end, break?, include?, note?}. A numeric break is kept as typed. */
    function addRow(v) {
        const row = rowTpl.content.firstElementChild.cloneNode(true);
        rowField(row, 'date').value = v.date || '';
        rowField(row, 'start').value = v.start || '';
        rowField(row, 'end').value = v.end || '';
        rowField(row, 'include').checked = v.include !== false;
        rowField(row, 'note').textContent = v.note || '';
        if (typeof v.break === 'number') {
            rowField(row, 'break').value = v.break;
            row.dataset.dirty = '1';
        } else {
            rowField(row, 'break').value = lunchBreak(parseTime(v.start || ''), parseTime(v.end || ''));
        }
        rowsBox.appendChild(row);
        updateTotals();
        return row;
    }

    /** Re-derive a row's break from the lunch window unless the user typed one. */
    function autoBreak(row) {
        if (row.dataset.dirty === '1') return;
        const { s, e } = rowValues(row);
        rowField(row, 'break').value = lunchBreak(s, e);
    }

    rowsBox.addEventListener('input', (ev) => {
        const row = ev.target.closest('.dlg-row');
        if (!row) return;
        const f = ev.target.dataset.f;
        if (f === 'break') row.dataset.dirty = '1';
        if (f === 'start' || f === 'end') autoBreak(row);
        updateTotals();
    });
    rowsBox.addEventListener('change', (ev) => { if (ev.target.closest('.dlg-row')) updateTotals(); });
    rowsBox.addEventListener('focusout', (ev) => {
        const f = ev.target.dataset && ev.target.dataset.f;
        if (f !== 'start' && f !== 'end') return;
        const m = parseTime(ev.target.value);
        if (m !== null && (f === 'end' || m < 1440)) ev.target.value = toHHMM(m);
        const row = ev.target.closest('.dlg-row');
        autoBreak(row);
        updateTotals();
    });
    rowsBox.addEventListener('click', (ev) => {
        if (!ev.target.closest('[data-remove]')) return;
        if (rowsBox.children.length > 1) ev.target.closest('.dlg-row').remove();
        updateTotals();
    });

    addDayBtn.addEventListener('click', () => {
        const last = rowsBox.lastElementChild;
        const lastDate = last ? parseDate(rowField(last, 'date').value) : null;
        const next = lastDate ? new Date(lastDate.getFullYear(), lastDate.getMonth(), lastDate.getDate() + 1) : new Date();
        const row = addRow({
            date: fmtDate(next),
            start: last ? rowField(last, 'start').value : '08:00',
            end: last ? rowField(last, 'end').value : '17:00',
            break: last && last.dataset.dirty === '1' ? (parseInt(rowField(last, 'break').value, 10) || 0) : undefined,
        });
        rowField(row, 'date').focus();
    });

    document.getElementById('entry-use-lunch').addEventListener('click', () => {
        Array.from(rowsBox.children).forEach((row) => { delete row.dataset.dirty; autoBreak(row); });
        updateTotals();
    });

    /**
     * Opens the dialog.
     * data: {id?} for editing a report, plus either a single day (date/start/end/break) or `rows: [...]`.
     */
    function openDialog(data) {
        if (dialog.dataset.ready !== '1') {
            window.TT.toast(tr('Add a client with at least one action first.'));
            return;
        }
        showErrors([]);
        const editing = !!data.id;
        deleteBtn.hidden = !editing;
        form.id.value = editing ? data.id : '';
        form.description.value = data.description || '';
        addDayBtn.hidden = editing;
        daysBox.classList.toggle('editing', editing);
        rowsBox.innerHTML = '';
        const rows = data.rows && data.rows.length ? data.rows : [{ date: data.date, start: data.start, end: data.end, break: data.break }];
        rows.forEach(addRow);
        selectClient(data.client_id);
        refreshActions(data.action_id);
        updateTotals();
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        (data.focusSave ? saveBtn : editing ? form.description : form.client_id).focus();
    }

    function openNew(date, startMin, endMin, extra) {
        openDialog({ date, start: toHHMM(startMin), end: toHHMM(endMin), ...(extra || {}) });
    }

    /** Opens the dialog for a full working day, ready to save with Enter (client/action = last used). */
    function openWholeDay(date, day) {
        openNew(date, day.start, day.end, { break: day.brk, focusSave: true });
    }

    function wholeDayLabel(day) {
        return day.brk
            ? tr('Report whole working day {from}–{to} (−{min} min break)', { from: toHHMM(day.start), to: toHHMM(day.end), min: day.brk })
            : tr('Report whole working day {from}–{to}', { from: toHHMM(day.start), to: toHHMM(day.end) });
    }

    /** One row per working day of a week; days that already have reports start unticked. */
    function weekRows(days) {
        return days.map((d) => {
            const day = wholeDay(parseWhStr(d.wh));
            if (!day) return null;
            return {
                date: d.date, start: toHHMM(day.start), end: toHHMM(day.end), break: day.brk,
                // red days and days that already have reports are listed but not ticked
                include: !d.n && !d.h,
                note: [d.h, d.n ? tr(d.n === 1 ? 'already has {n} report' : 'already has {n} reports', { n: d.n }) : ''].filter(Boolean).join(' · '),
            };
        }).filter(Boolean);
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
            return { ok: false, errors: [tr('Session expired.')] };
        }
        try {
            return await res.json();
        } catch (e) {
            return { ok: false, errors: [tr('Unexpected server response.')] };
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editing = !!form.id.value;
        const multi = rowsBox.children.length > 1;
        const errors = [];
        const entries = [];
        Array.from(rowsBox.children).forEach((row, i) => {
            const v = rowValues(row);
            if (!v.include) return;
            const label = multi ? (v.date || tr('Day {n}', { n: i + 1 })) + ': ' : '';
            if (!parseDate(v.date)) errors.push(label + tr('Choose a date.'));
            else if (v.s === null || v.e === null) errors.push(label + tr('Enter times as HH:MM (e.g. 08:30).'));
            else if (v.e <= v.s) errors.push(label + tr('End time must be after the start time.'));
            else if (v.brk >= v.e - v.s) errors.push(label + tr('The break must be shorter than the time span.'));
            else entries.push({ date: v.date, start: toHHMM(v.s), end: toHHMM(v.e), break: v.brk });
        });
        if (!errors.length && !entries.length) errors.push(tr('Tick at least one day.'));
        if (!form.action_id.value) errors.push(tr('This client has no actions yet – add some on the Actions page.'));
        if (errors.length) return showErrors(errors);

        saveBtn.disabled = true;
        const common = {
            client_id: parseInt(form.client_id.value, 10) || 0,
            action_id: parseInt(form.action_id.value, 10) || 0,
            description: form.description.value,
        };
        const result = editing
            ? await post({ op: 'save', id: parseInt(form.id.value, 10), ...entries[0], ...common })
            : await post({ op: 'save_many', entries, ...common });
        saveBtn.disabled = false;
        if (result.ok) {
            store.set('tt_client', form.client_id.value);
            store.set('tt_action_' + form.client_id.value, form.action_id.value);
            window.location.reload();
        } else {
            showErrors(result.errors || [tr('Could not save.')]);
        }
    });

    async function deleteEntry(id) {
        const result = await post({ op: 'delete', id });
        if (result.ok) window.location.reload();
        else window.TT.toast((result.errors || [tr('Could not delete.')])[0]);
    }

    deleteBtn.addEventListener('click', () => {
        if (form.id.value && window.confirm(tr('Delete this time report?'))) deleteEntry(parseInt(form.id.value, 10));
    });
    $$('[data-dialog-close]', dialog).forEach((b) => b.addEventListener('click', closeDialog));
    dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });

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
            { label: tr('Edit time report'), run: () => openDialog(p) },
            { label: tr('Duplicate'), run: () => openDialog({ ...p, id: null }) },
            '-',
            { label: tr('Delete'), danger: true, run: () => { if (window.confirm(tr('Delete this time report?'))) deleteEntry(p.id); } },
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

    const parseWhStr = (str) => (str || '').split(',').filter(Boolean).map((s) => s.split('-').map(Number));
    const parseWh = (el) => parseWhStr(el.dataset.wh);
    const hourAt = (track, clientX) => {
        const r = track.getBoundingClientRect();
        const f = Math.min(0.99999, Math.max(0, (clientX - r.left) / r.width));
        return Math.floor(f * 24);
    };

    function trackMenu(x, y, track, hour) {
        const date = track.dataset.date;
        const wh = parseWh(track);
        const day = wholeDay(wh);
        const items = [];
        if (day) items.push({ label: wholeDayLabel(day), run: () => openWholeDay(date, day) });
        items.push({ label: tr('New time report {from}–{to}', { from: toHHMM(hour * 60), to: toHHMM((hour + 1) * 60) }), run: () => openNew(date, hour * 60, (hour + 1) * 60) });
        if (wh.length > 1) {
            wh.forEach(([s, e]) => items.push({ label: tr('New report {from}–{to}', { from: toHHMM(s), to: toHHMM(e) }), run: () => openNew(date, s, e) }));
        }
        if (view !== 'day') {
            items.push('-', { label: tr('Open day'), run: () => { window.location.href = dayUrl(date); } });
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

    /* ------------------------------------------- move a report within its day */

    // Drag a report block sideways to shift it by whole hours (its length and break stay as they are).
    // Mouse and pen only: on touch screens a sideways swipe scrolls the timeline, so tap the report to edit its times.
    let mv = null;
    let suppressEntryClick = false;
    document.addEventListener('pointerdown', (e) => {
        const el = e.target.closest('.tl-track .te');
        if (!el || e.pointerType === 'touch' || e.button !== 0 || mv) return;
        const track = el.closest('.tl-track');
        const p = payloadOf(el);
        const start = parseTime(p.start);
        const end = parseTime(p.end);
        if (start === null || end === null) return;
        mv = { el, track, p, start, end, x: e.clientX, delta: 0, active: false, left: el.style.left, label: $('.te-time', el), labelText: ($('.te-time', el) || {}).textContent, pointerId: e.pointerId };
    });

    function paintMove() {
        const s = mv.start + mv.delta * 60;
        const e = mv.end + mv.delta * 60;
        mv.el.style.left = (s / 1440 * 100) + '%';
        if (mv.label) mv.label.textContent = `${toHHMM(s)}–${toHHMM(e)}`;
    }

    function endMove(restore) {
        const m = mv;
        mv = null;
        document.body.classList.remove('is-moving');
        m.el.classList.remove('moving');
        try { m.el.releasePointerCapture(m.pointerId); } catch (err) { /* ignore */ }
        if (restore) {
            m.el.style.left = m.left;
            if (m.label) m.label.textContent = m.labelText;
        }
        return m;
    }

    document.addEventListener('pointermove', (e) => {
        if (!mv) return;
        if (!mv.active) {
            if (Math.abs(e.clientX - mv.x) < 5) return; // below the threshold it is still a click
            mv.active = true;
            hideMenu();
            mv.el.classList.add('moving');
            document.body.classList.add('is-moving');
            try { mv.el.setPointerCapture(mv.pointerId); } catch (err) { /* ignore */ }
        }
        const perHour = mv.track.getBoundingClientRect().width / 24;
        let delta = Math.round((e.clientX - mv.x) / perHour);
        delta = Math.max(Math.ceil(-mv.start / 60), Math.min(Math.floor((1440 - mv.end) / 60), delta)); // stay inside the day
        if (delta !== mv.delta) { mv.delta = delta; paintMove(); }
    });

    document.addEventListener('pointerup', async () => {
        if (!mv) return;
        if (!mv.active) { mv = null; return; }
        suppressEntryClick = true;
        setTimeout(() => { suppressEntryClick = false; }, 0);
        if (mv.delta === 0) { endMove(true); return; }
        const m = endMove(false);
        const start = toHHMM(m.start + m.delta * 60);
        const end = toHHMM(m.end + m.delta * 60);
        m.el.classList.add('saving');
        const result = await post({ op: 'save', ...m.p, start, end });
        if (result.ok) {
            try { sessionStorage.setItem('tt_toast', tr('Moved to {from}–{to}', { from: start, to: end })); } catch (err) { /* ignore */ }
            window.location.reload();
        } else {
            m.el.classList.remove('saving');
            m.el.style.left = m.left;
            if (m.label) m.label.textContent = m.labelText;
            window.TT.toast((result.errors || [tr('Could not save.')])[0]);
        }
    });

    document.addEventListener('pointercancel', () => { if (mv && mv.active) endMove(true); mv = null; });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && mv && mv.active) { endMove(true); suppressEntryClick = true; setTimeout(() => { suppressEntryClick = false; }, 0); } });
    // A drag ends with a click on the block: do not open the edit dialog for that
    document.addEventListener('click', (e) => {
        if (suppressEntryClick && e.target.closest('.tl-track .te')) { e.preventDefault(); e.stopImmediatePropagation(); }
    }, true);
    try {
        const msg = sessionStorage.getItem('tt_toast');
        if (msg) { sessionStorage.removeItem('tt_toast'); window.TT.toast(msg); }
    } catch (err) { /* ignore */ }

    /* ----------------------------------------------------------- month view */

    function dayMenu(x, y, day) {
        const date = day.dataset.date;
        const wh = parseWh(day);
        const whole = wholeDay(wh);
        const items = [];
        if (whole) items.push({ label: wholeDayLabel(whole), run: () => openWholeDay(date, whole) });
        items.push({ label: tr('New time report…'), run: () => openNew(date, 9 * 60, 10 * 60) });
        if (wh.length > 1) {
            wh.forEach(([s, e]) => items.push({ label: tr('New report {from}–{to}', { from: toHHMM(s), to: toHHMM(e) }), run: () => openNew(date, s, e) }));
        }
        items.push('-',
            { label: tr('Open day'), run: () => { window.location.href = day.dataset.dayUrl; } },
            { label: tr('Open week'), run: () => { window.location.href = day.dataset.weekUrl; } });
        showMenu(x, y, day.dataset.label, items);
    }

    /** Right-click / long-press on a week number in the month view. */
    function weekMenu(x, y, cell) {
        const days = JSON.parse(cell.dataset.week || '[]');
        const rows = weekRows(days);
        const items = [];
        if (rows.length) {
            items.push({
                label: tr('Report whole working week (minus daily lunch break)'),
                run: () => openDialog({ rows, focusSave: rows.every((r) => r.include) }),
            });
        }
        items.push({ label: tr('Open week'), run: () => { window.location.href = cell.getAttribute('href'); } });
        showMenu(x, y, tr('Week {n}', { n: cell.dataset.weekNo }), items);
    }

    // Right click anywhere in the calendar
    document.addEventListener('contextmenu', (e) => {
        const wk = e.target.closest('.wk-col[data-week]');
        if (wk) {
            e.preventDefault();
            return weekMenu(e.clientX, e.clientY, wk);
        }
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
    let suppressClickUntil = 0;
    document.addEventListener('click', (e) => {
        if (Date.now() < suppressClickUntil && e.target.closest('.wk-col')) { e.preventDefault(); e.stopPropagation(); }
    }, true);
    document.addEventListener('pointerdown', (e) => {
        const wk = e.target.closest('.wk-col[data-week]');
        if (wk && e.pointerType === 'touch') {
            press = { wk, x: e.clientX, y: e.clientY, fired: false };
            press.timer = setTimeout(() => { press.fired = true; suppressClickUntil = Date.now() + 700; weekMenu(press.x, press.y, wk); }, 550);
            return;
        }
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
        if (!p.fired && p.day && Date.now() - menuOpenedAt > 700) window.location.href = p.day.dataset.dayUrl;
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
