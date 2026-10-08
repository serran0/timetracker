# Timetracker

A light, modern PHP web app for consultants who bill clients by the hour. Track worked time in a calendar, then export it at the end of the month.

**Version 0.2.5** · PHP 8.5 (runs on 8.2+) · MySQL / MariaDB · no Composer packages, no build step.

## Features

- **Languages:** English and Swedish, chosen per user under *Account & settings*. The whole interface, dates, numbers and exports follow it. The standard action names are translated while unmodified; actions you have renamed are never touched.
- **Swedish red days** (public holidays plus Midsommarafton, Julafton and Nyårsafton) can be shown in every calendar view as a faded red overlay with a translated name, switched on or off per user. They are calculated from their rules, so nothing is downloaded. You can also add your own **work-free days** (single days or ranges). Both are left out of whole-week reports.
- **Clients** with a colour, optional hourly rate and reference (PO number etc.).
- **Time actions** (templates) **per client**: normal working time, overtime, emergency… each with a colour, a rate multiplier (e.g. overtime ×1.5) and a billable flag. New clients start from a standard set or a copy of another client's actions.
- **Working hours** per weekday (several intervals per day). They are highlighted in the week and day views. A **lunch window** (e.g. 12:00–13:00) is pre-filled as an **unpaid break** on new reports and deducted from the hours; the break is stored per report and can be changed. Right-click a day and choose **Report whole working day** for a one-step 08–17 report with lunch deducted.
- **Calendar views:** month, week, day and a detailed list, with ISO week numbers, responsive layouts and colours (by client or by action).
  - Week and day views show the whole 24-hour day, one row per day.
  - **Create time reports** by dragging across hours, or by right-clicking an hour (week/day) or a day (month). On touch screens tap an hour, or press and hold a day.
  - Click a report to edit it; right-click it to edit, duplicate or delete.
  - **Filters** for clients, actions, billable/non-billable and date period. A **summary** (total, billable, amount, per client, per action) is shown under every view.
- **The New time report dialog creates one or several days at once**: pick client and action, then add days with **+ Day** (each with its own start, end and break). In the month view, right-click a **week number** and choose *Report whole working week* to get a pre-filled row for every working day.
- **Time reports** have start/end time, client, action and a free-text description. Times accept `9`, `930`, `09:30`, `9.30`; `24:00` means midnight.
- **Export** to CSV, Excel (.xlsx) or plain text (all three follow the ticked columns), with chosen columns, decimal hours or h:mm, delimiter and decimal mark.
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

Upgrading from an older version: replace the files and open the site; the database is upgraded automatically (the database user needs ALTER privilege).

Setup writes `config/config.php` (mode `0640`, git-ignored). Once it exists the installer is disabled; delete that file to run setup again. Setup refuses to touch a database that already contains Timetracker tables.

### Local development

```bash
php -S 127.0.0.1:8080 -t public
php tests/run.php        # logic tests, no database needed
php tests/i18n.php       # checks every translation key exists in src/lang/*.php
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
src/lang/    translations (sv.php)
tests/       run.php, i18n.php
```

## Notes

- Time reports are stored as local wall-clock time (date + start + end) and are limited to a single day; use `24:00` to run to midnight.
- Hours, summaries, amounts and exports always use **net** time (time span minus unpaid break).
- Estimated amounts = net hours × client hourly rate × action multiplier, for billable actions only.
- Excel export needs the PHP `zip` extension; CSV and text export always work.

See [CHANGELOG.md](CHANGELOG.md).
