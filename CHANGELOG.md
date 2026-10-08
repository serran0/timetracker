# Changelog

## 0.2.25 – 2026-10-08

- Actions page, quick setup: the "Copy actions from…" button now comes before the client drop-down (they were the other way round), and both have the same height.

## 0.2.24 – 2026-10-08

- Export page: the format choices from 0.2.22 are now saved the moment you change them (file format, columns, duration format, delimiter, decimal mark, totals, VAT) — no need to press *Update preview* or *Download export* first. A quick "✓ Saved" appears next to the section hint; several quick changes are sent as one request. Pressing the buttons still saves too.

## 0.2.23 – 2026-10-08

- Actions page: the *Client* drop-down now starts at a "— Select client —" placeholder (Swedish: "— Välj kund —"). No actions (and no add-action form) are shown until a client is chosen. Links that name a client, such as the one after creating a client, still open that client directly.

## 0.2.22 – 2026-10-08

- Export page: all the *Format & columns* choices are remembered per user and used the next time you open the page, on any device: file format, ticked columns, duration format, CSV delimiter, decimal mark, the totals row and the VAT option. They are saved when you press *Update preview* or *Download export*. The period and the client/action/billing filters are not remembered.
- Database schema 8: `users.export_prefs`; existing installs upgrade automatically.

## 0.2.21 – 2026-10-08

- Clients page: the client table can be sorted by *Client* (name), *Rate / h*, *Actions* and *Reports*. Click a header to sort (numbers start with the highest first), click again to reverse; the active column shows an arrow. Clients without a rate always come last, archived clients stay at the bottom, and equal values fall back to name order. The choice is remembered for the session, so it survives editing or archiving a client.

## 0.2.20 – 2026-10-08

- Summary at the bottom of every calendar view: the *Per action* list now shows the client in parentheses to the left of the action, e.g. `(Acme AB) Overtime`. Since actions belong to a client, the same action name is listed once per client instead of being merged. Exports are unchanged.

## 0.2.19 – 2026-10-08

- Week and day views: drag an existing report sideways to move it within its day. It snaps to whole hours (the whole block shifts by the number of hours dragged, so its length and unpaid break stay the same), stays inside the day, shows the new time while dragging and is saved when you let go. Press Esc to cancel. A plain click still opens the report. Mouse and pen only; on touch screens tap the report and change its times in the dialog (a sideways swipe scrolls the timeline).

## 0.2.18 – 2026-10-08

- Colour field on the Clients and Actions forms redone: the current colour is a larger rounded box, vertically centred beside a neat 5 × 2 palette grid (it used to sit lower and touch the palette dots). The palette swatch matching the current colour is ringed, and it follows the colour picker too.

## 0.2.17 – 2026-10-08

- **VAT per client.** Each client has a *VAT (%)* field (empty/0 = no VAT). Wherever an amount is calculated, the figure including VAT follows in parentheses, e.g. `3 325.00 (4 172.88)`: report list and its total, the summary, and the export preview. Totals add up the per-report VAT-inclusive amounts. The clients list shows each client's VAT. Clients without VAT show no parentheses.
- **Exports:** a new *Include VAT* option (on by default) on the export page. When on, CSV and Excel get an *Amount incl. VAT* column after *Amount* (and the Excel summary sheet a matching column), and the plain-text report shows `[800.00 SEK (1 000.00 SEK)]` on lines, summaries and the total. When off, exports contain no VAT figures.
- Database schema 7: `clients.vat_percent`; existing installs upgrade automatically with VAT 0, so nothing changes until you set a rate.

## 0.2.16 – 2026-10-08

- List view: the "Colour by…" control is removed from the filter bar, since the list has no colour choice to make. The other views keep it.

## 0.2.15 – 2026-10-08

- Fix: the week numbers in the week picker were one too low for most of the year in time zones with daylight saving (the browser computed them from local-time differences that are an hour short in summer). They are now calculated in UTC and match the calendar's own ISO week numbers.

## 0.2.14 – 2026-10-08

- New setting under *Account & settings*: **Default calendar colouring** (by client or by action). The calendar opens with it, and the *Colour by* menu in the filter bar still overrides it for the current session of browsing. Existing users keep colouring by client. (Database schema 6: `users.default_color`; existing installs upgrade automatically.)

## 0.2.13 – 2026-10-08

- Month view: each report chip now shows the reported (net) hours instead of the start time, e.g. "8 hrs Customer Name" ("7,5 tim" in Swedish). The tooltip still shows the start and end time.

## 0.2.12 – 2026-10-08

- The date picker is back in the calendar filter bar, with a variant per view:
  - **Month:** a month picker with year arrows and a 12-month grid.
  - **Week:** a month calendar showing all days with a week-number column on the left; hovering a row highlights the whole week, and clicking it opens that week.
  - **Day:** the normal date picker, as before.
- All three keep the active filters, are localised (English/Swedish) and work on touch screens. Escape or a click outside closes the picker.

## 0.2.11 – 2026-10-08

- The "Go to date" picker is removed from the filter bar in the month and week views (navigate with the arrows, *Today* and the view tabs). The day view keeps it, and the list view keeps its From/To period. The current period is still kept when filters are applied.

## 0.2.10 – 2026-10-08

- Calendar filter bar, properly this time: the date pickers carried a stray 5px top margin from the generic form styling (it outranked the filter bar's own rule), so they sat lower than the other controls. All controls now have identical size (34px) and identical position (11px above and below) in the bar.

## 0.2.9 – 2026-10-08

- Calendar filter bar: every control (date picker, dropdowns, selects and buttons) now has the same height (34px). The date picker used to be taller than the rest.

## 0.2.8 – 2026-10-08

- Calendar filter bar: the controls are now centred vertically in the bar. Before, the dropdown buttons carried a top margin that made the bar taller than its controls and left empty space above them.

## 0.2.7 – 2026-10-08

- Clicking the Timetracker logo/title now always opens the month view (of the current month), instead of the view you used last. The *Calendar* menu item still remembers your last view.

## 0.2.6 – 2026-10-08

- Month view: the right-hand column that showed the weekly total is now headed "Hrs" (Swedish: "Tim") instead of "Week", with a tooltip explaining it.

## 0.2.5 – 2026-10-08

- **Swedish red days in every calendar view.** Public holidays plus the customary days off (Midsommarafton, Julafton, Nyårsafton) are tinted faded red with their name: in the month view under the date, in the week and day views on the whole row and in the row label, in the list view as a badge on the day heading, and next to the title in the day view. Names follow the user's language (Julafton / Christmas Eve).
- **Calculated, not downloaded.** The days are derived from their rules (fixed dates, Easter-based days, and the Midsummer and All Saints' weekend rules) in `src/Holidays.php`, so there is no subscription to keep up to date and it works offline for any year. Unit tests check Easter for nine years and the movable days for eight.
- A red day has no shaded working hours and no "Report whole working day" shortcut, so work done on it stands out. Reports can still be created on it.
- **Setting:** *Account & settings → Show Swedish public holidays in the calendar*. On for new users, off for existing users (they opt in).
- **Work-free days:** users can add their own days off, a single day or a range such as a vacation week, with an optional name (*Account & settings → Work-free days*). They are tinted amber and are always shown, independent of the holiday setting.
- **Whole-week report skips red days and own days off:** they are still listed, unticked and with a note (for example "Christmas Eve"), so you can tick one if you did work.
- Database: `users.show_holidays` and a new `free_days` table (schema 5, added automatically).
- Fixed: on tall row labels the week view track no longer leaves a gap below it.

## 0.2.3 – 2026-10-08

- **Swedish translation and a per-user language setting.** Each user picks English or Swedish under *Account & settings*; administrators can choose it for new users. Everything is translated: menus, pages, dialogs, right-click menus, messages and errors, the installer, month/day names, number formatting (decimal comma), and all exports (CSV, Excel and text: headers, weekday names, yes/no, totals, summaries).
- **Standard action names are translated, customised ones are not.** Normal working time, Overtime, Emergency / call-out, Travel and Internal / non-billable are shown in the user's language only while they are unmodified. Renamed actions are shown exactly as stored, and nothing in the database is rewritten, so switching language is always reversible. Opening a translated standard action and saving it without changing the name does not freeze the translation.
- The login and setup pages follow a cookie or the browser's language, with a language switch on the login page; the setup form has a language field for the administrator. The login page remembers your language after you sign out.
- Database: new `users.locale` column (schema 4, added automatically; existing users stay on English).
- Adding another language: copy `src/lang/sv.php` to `src/lang/<code>.php`, translate it, add the code to `I18n::LOCALES` and the month/day names to `I18n`. `php tests/i18n.php` verifies that no key is missing and that placeholders match.

## 0.2.2 – 2026-10-08

- **Plain-text export respects the ticked columns.** Previously only Description and Amount were honoured; times, duration, client, action and the headings were always printed. Now each line contains only the ticked columns (start/end, duration, client, client reference, action, billable flag, rate, amount, description). Day headings are built from the ticked date / weekday / week columns (none ticked = a flat list without headings). "Add a totals row" switches day totals, summaries and grand totals on or off, and they only show figures for ticked columns (hours, amount, client, action).
- No database changes.

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
