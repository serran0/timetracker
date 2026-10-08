# Changelog

## 0.2.1 – 2026-10-08

- **Time actions are per client.** Each client has its own set of actions (name, colour, rate multiplier, billable), managed per client on the Actions page. New clients start from the standard set, a copy of another client's actions, or empty; "Add standard actions" and "Copy actions from…" are available later. The report dialog only offers the selected client's actions and remembers the last action used per client. The calendar and export action filters match by name across clients ("all Overtime"), and summaries group actions by name.
- **Upgrade:** existing actions are copied to every client and each report is re-pointed to its client's copy, so all existing reports keep their client, action name, times and break (schema 3, automatic on first request).
- **New time report dialog redone for several days.** It now has shared client, action and description plus a list of day rows (date, start, end, break, net), a **+ Day** button (next day, same times), per-row remove, and a running total. Each row derives its break from the lunch window until you edit it. Editing an existing report stays a single row. Saving several days is all-or-nothing.
- **Report whole working week:** in the month view, right-click (or long-press) a week number and choose *Report whole working week (minus daily lunch break)*. The dialog opens with one row per working day (08:00–17:00, 60 min break). Days that already have reports start unticked with a note.
- Fixed: Chrome's datalist arrow clipped the time fields on narrow screens.

## 0.2.0 – 2026-10-08

Unpaid breaks (e.g. lunch).

- **Lunch window** (Working hours page): your usual unpaid break, e.g. 12:00–13:00.
- **Break on every report:** a new "Unpaid break (minutes)" field. When you create a report it is pre-filled with the part of the report that overlaps the lunch window (08:00–17:00 → 60 min, 08:00–11:00 → 0), and you can change it. The break is stored on the report, so later changes to your settings never alter old reports. "Use lunch window" resets the field.
- **Net hours everywhere:** calendar totals, summaries, amounts and all exports use time minus break. Exports contain net hours only (no break column). Week/day blocks keep their full 08–17 span with a hatched stripe where the break is.
- **Report whole working day:** new first entry in the right-click menu of a day (week, day and month views). It opens the dialog with your working day and break filled in and the Save button focused, so with your usual client and action pre-selected it is right-click, then Enter.
- **Automatic upgrade:** installs from 0.1 are upgraded on the first request (`src/Migrator.php`; needs ALTER privilege). Existing reports keep a 0-minute break. Set your lunch window once on the Working hours page. New installs and new users get 12:00–13:00 by default.


## 0.1.0 – 2026-10-08

First release.

- Setup page: collects MySQL credentials, checks PHP/extensions/permissions (real CREATE/INSERT/SELECT/UPDATE/ALTER/INDEX/DELETE/DROP probe), creates the database and tables, creates the first administrator and writes `config/config.php`.
- Username/password login with CSRF protection, brute-force throttling and per-user isolated data. Administrators can add users.
- Clients (colour, optional hourly rate, reference, archive/delete).
- Time action templates (normal, overtime, emergency, travel, non-billable… with colour, rate multiplier and billable flag).
- Working hours per weekday (multiple intervals per day).
- Calendar views: month, week, day and detailed list, with ISO week numbers, colour by client or action, filters (clients, actions, billable, date/period) and a summary (hours, billable hours, amount, per client, per action) below each view.
- Week and day views show the full 24 hours with one row per day and the working hours highlighted. Create reports by dragging across hours or right-clicking; right-click a day in the month view. Touch support (tap / long-press).
- Export to CSV, Excel (.xlsx) and plain text with selectable columns, duration format, delimiter and decimal mark.
