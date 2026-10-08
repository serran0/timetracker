# Timetracker

A light, modern PHP web app for consultants who bill clients by the hour. Track worked time in a calendar, then export it at the end of the month.

**Version 0.1.0** · PHP 8.5 (runs on 8.2+) · MySQL / MariaDB · no Composer packages, no build step.

## Features

- **Clients** with a colour, optional hourly rate and reference (PO number etc.).
- **Time actions** (templates): normal working time, overtime, emergency… each with a colour, a rate multiplier (e.g. overtime ×1.5) and a billable flag.
- **Working hours** per weekday (several intervals per day, e.g. for lunch breaks). They are highlighted in the week and day views.
- **Calendar views:** month, week, day and a detailed list, with ISO week numbers, responsive layouts and colours (by client or by action).
  - Week and day views show the whole 24-hour day, one row per day.
  - **Create a time report** by dragging across hours, or by right-clicking an hour (week/day) or a day (month). On touch screens tap an hour, or press and hold a day.
  - Click a report to edit it; right-click it to edit, duplicate or delete.
  - **Filters** for clients, actions, billable/non-billable and date period. A **summary** (total, billable, amount, per client, per action) is shown under every view.
- **Time reports** have start/end time, client, action and a free-text description. Times accept `9`, `930`, `09:30`, `9.30`; `24:00` means midnight.
- **Export** to CSV, Excel (.xlsx) or plain text, with chosen columns, decimal hours or h:mm, delimiter and decimal mark.
- **Security:** username/password, hashed passwords (`password_hash`), CSRF tokens, login throttling, strict Content-Security-Policy, prepared statements everywhere. Each user has a fully separate environment (clients, actions, hours, reports).

## Install

Requirements: PHP 8.2+ (8.5 recommended) with `pdo_mysql`, `mbstring`, `ctype`, `json` (and `zip` for Excel export), and a MySQL 5.7+/8 or MariaDB 10.3+ server.

1. Put the project on your server and point the web server's **document root at `public/`**.
   (If you can only use the project folder as the root, the included `.htaccess` forwards requests to `public/` and blocks everything else – Apache only.)
2. Make `config/` writable by the web server user.
3. Open the site in a browser – you are sent to **`setup.php`**:
   - enter the MySQL host, port, database name, user and password;
   - choose the administrator username/password, timezone and currency;
   - **Run checks** shows PHP/extension results and tests the database permissions for real; **Check & install** does the same and then installs.
4. Sign in. Add a client, then start reporting time.

Setup writes `config/config.php` (mode `0640`, git-ignored). Once it exists the installer is disabled; delete that file to run setup again. Setup refuses to touch a database that already contains Timetracker tables.

### Local development

```bash
php -S 127.0.0.1:8080 -t public
php tests/run.php        # logic tests, no database needed
```

### nginx

Serve `public/` as root and send `*.php` to PHP-FPM; do not expose the parent directories.

## Project layout

```
public/      web root: one script per page, api/entry.php, assets/
src/         classes (Auth, Db, Calendar, Installer, Repository/*, Export/*)
views/       PHP templates
database/    schema.sql
config/      generated config.php (not committed)
tests/       run.php
```

## Notes

- Time reports are stored as local wall-clock time (date + start + end) and are limited to a single day; use `24:00` to run to midnight.
- Estimated amounts = hours × client hourly rate × action multiplier, for billable actions only.
- Excel export needs the PHP `zip` extension; CSV and text export always work.

See [CHANGELOG.md](CHANGELOG.md).
