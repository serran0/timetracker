# Changelog

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
