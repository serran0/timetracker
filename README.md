# Timetracker

A light, modern PHP web app for consultants who bill clients by the hour. Track worked time in a calendar, then export it at the end of the month. Administrators manage users, settings, backups and the audit log in a separate admin panel.

**Version 0.4.2** · PHP 8.4 (runs on 8.2–8.4) · MySQL / MariaDB · no Composer packages, no build step.

## Features

### Time reporting (regular users)

- **Calendar views:** month, week, day and a detailed list, with ISO week numbers, responsive layouts and colours (by client or by action; the default is a setting under *Account & settings*).
  - The month view shows each report as a chip with its net hours (`4 hrs Client`, `Tim` in Swedish); the tooltip has the start and end time. Each view has its own date picker: a year-and-month picker, a week picker (all days of a month, week numbers on the left, the whole week highlights on hover) and the normal date picker for the day view.
  - Week and day views show the whole 24-hour day, one row per day, with your working hours shaded.
  - **Create time reports:** click an hour for a one-hour report, drag across hours for a longer one, right-click an hour (week/day) or a day (month), or double-click a day (month). On touch screens tap an hour, or press and hold a day.
  - **Move reports by dragging:** in the week view drag a block to other hours (it snaps to whole hours) and onto other days; in the day view to other hours; in the month view drag a chip onto another day. Esc cancels. (Mouse and pen; on touch screens open the report and change its date and times.)
  - Click a report to edit it; right-click it to edit, duplicate or delete.
  - **Filters** for clients, actions, billable/non-billable and period. A **summary** (total, billable, amount, per client, per action) is shown under every view.
- **The New time report dialog creates several reports at once:** pick the client, then give every row its own day, times, break, **action** and **Notes**. **Add action** appends another row (same day, starting where the previous one ends). **Notes** opens a text box for that row; rows with notes have a highlighted button and the note is marked with ✎ in the calendar. Editing an existing report keeps the single-report layout, with a *Description* box (the same field as Notes). In the month view, right-click a **week number** and choose *Report whole working week* to get a pre-filled row for every working day.
- **Time reports** have start/end time, client, action and a free-text description. Times accept `9`, `930`, `09:30`, `9.30`; `24:00` means midnight.
- **Clients** with a colour, optional hourly rate, **VAT percentage** and reference (PO number etc.). The client table can be sorted by name, rate, actions and reports. Adding and editing happen in a modal.
- **Time actions per client:** normal working time, overtime, emergency… each with a colour, a rate multiplier (e.g. overtime ×1.5) and a billable flag. New clients start from a standard set or a copy of another client's actions. Adding and editing happen in a modal.
- **Working hours** per weekday (several intervals per day). A **lunch window** (e.g. 12:00–13:00) is pre-filled as an **unpaid break** on new reports and deducted from the hours; the break is stored per report and can be changed. Right-click a day and choose **Report whole working day** for a one-step 08–17 report with lunch deducted.
- **Swedish red days** (public holidays plus Midsommarafton, Julafton and Nyårsafton) can be shown in every calendar view as a faded red overlay with a translated name, switched on or off per user. They are calculated from their rules, so nothing is downloaded. You can also add your own **work-free days** (single days or ranges). Both are left out of whole-week reports.
- **VAT:** wherever an amount is calculated, the figure including the client's VAT follows in parentheses, e.g. `3 325.00 (4 172.88)`. Clients without VAT show no parentheses.
- **Export** to CSV, Excel (.xlsx) or plain text (all three follow the ticked columns), with chosen columns, decimal hours or h:mm, delimiter, decimal mark, a totals row and an *Include VAT* option. Your format choices are saved the moment you change them and come back next time. Spreadsheet formulas in text fields are neutralised.
- **Languages:** English and Swedish, chosen per user. The whole interface, dates, numbers and exports follow it. The standard action names are translated while unmodified; actions you have renamed are never touched.

### Accounts and the admin panel

- **Two kinds of account, kept apart.** *Regular users* report time (everything above). *Administrators* only manage Timetracker in the **admin panel**: they have no calendar, clients, actions or reports, and a regular user can never become an administrator (or the reverse). The kind is chosen when the account is created and cannot be changed. A regular user cannot change their own username.
- **Admin panel** (administrators only):
  - **Overview:** user counts, sign-ins and failed sign-ins in the last 24 hours, recent activity, notices.
  - **Users:** create regular or administrator accounts, search and page through them, rename any account (including other administrators), reset passwords (with a forced change at the next sign-in), enable or disable accounts. The last active administrator cannot be disabled.
  - **Audit log:** who did what and when, for everyone, with filters for date range, action type and user, sorting on the date column, and 50/100/250/500 rows per page. Sign-ins (with the IP address), failed attempts and manual sign-outs are included. Time-reporting actions are logged *opaquely*: "a client was created", "a time report was deleted" – never client names, action labels, descriptions, times or amounts.
  - **Settings:** defaults for new accounts, sign-in throttling, and the **mail server** (SMTP with STARTTLS/SSL, sender, a list of administrator recipients, a test button) for notifications that will be added later.
  - **System:** versions of all components (Timetracker, PHP and extensions, web server, database server, libraries), PHP/MySQL/file checks like the setup page's, a privilege test and a comparison of the live database structure with `database/schema.sql`.
  - **Backup:** download the database as `.sql` or `.sql.gz` (consistent snapshot, no mysqldump needed) and restore from such a file. A restore is validated completely before anything is changed, writes a safety copy to `config/` when it can, and upgrades backups from older versions. Plain `mysql < backup.sql` works too.

## Install

Requirements: PHP 8.2 to 8.4 (8.4 recommended; newer versions are not supported) with `pdo_mysql`, `mbstring`, `ctype`, `json` (and `zip` for Excel export, `zlib` for compressed backups), and a MySQL 5.7+/8 or MariaDB 10.3+ server.

1. Put the project on your server and point the web server's **document root at `public/`**.
   (If you can only use the project folder as the root, the included `.htaccess` forwards requests to `public/` and blocks everything else – Apache only.)
2. Make `config/` writable by the web server user.
3. Open the site in a browser – you are sent to **`setup.php`**:
   - enter the MySQL host, port, database name, user and password;
   - choose the first administrator's username/password, timezone and currency (regular users who report time are created afterwards in the admin panel);
   - **Run checks** shows PHP/extension results and tests the database permissions for real; **Check & install** does the same and then installs.
4. Sign in as the administrator, create a regular user under *Users*, and sign in as that user to add a client and start reporting time.

Setup writes `config/config.php` (mode `0640`, git-ignored). Once it exists the installer is disabled; delete that file to run setup again. Setup refuses to touch a database that already contains Timetracker tables. **Finish setup before exposing the site**: until it is done, anyone who can reach it can run it.

### Upgrading

Replace the files and open the site; the database is upgraded automatically (the database user needs ALTER and CREATE privileges). The current schema number is shown on the admin *System* page.

**Upgrading to 0.3 or later:** administrator and regular accounts are now separate kinds. On the first run, administrator rights are removed from every existing account (they stay regular users with all their data) and a placeholder administrator **`admin1` / `admin1`** is created. Sign in with it right away: you are required to choose a new password before anything else, and then create your own administrator accounts under *Users*. If `admin1` already exists the placeholder is named `admin2` and so on (the audit log says which). Until the password is changed anyone who knows the default could sign in, so do the upgrade and the first sign-in together.

### Configuration

Everything is set up by the installer; these optional keys in `config/config.php` are for special cases:

```php
return [
    'db'  => ['host' => 'localhost', 'port' => 3306, 'name' => 'timetracker', 'user' => '…', 'pass' => '…'],
    'app' => [
        'timezone' => 'Europe/Stockholm',  // timezone of the audit log and of visitors who are not signed in
        'debug'    => false,               // true shows error details on the page – never on a production server
    ],

    // Behind a load balancer / reverse proxy: list its addresses so the real visitor IP is used
    // (login throttling and the audit log are per IP; without this every visitor shares the proxy's address).
    'trusted_proxies' => ['10.0.0.0/8', '192.168.1.5'],

    // Sessions shared between web servers. 'files' is the default and fine for one server.
    'session' => ['handler' => 'db'],                                          // the `sessions` table, no extra software
    // 'session' => ['handler' => 'redis', 'save_path' => 'tcp://redis:6379'], // needs the PHP redis extension (also 'memcached')
];
```

`trusted_proxies` makes the app read `X-Forwarded-For`, but only from those addresses, and it takes the right-most entry that is not itself a trusted proxy, so a visitor cannot forge their IP. Switching the session handler signs everybody out once. Upgrade first (the upgrade creates the `sessions` table), then change the setting.

Application settings that administrators can change at runtime (defaults for new accounts, sign-in throttling, mail server) live in the database and are edited under *Settings* in the admin panel.

### Scaling (many users, several web servers)

Everything is scoped by user, so the web tier is stateless apart from the session; use the `trusted_proxies` and shared `session` settings above behind a load balancer. All user data queries use the `(user_id, …)` indexes, so a request costs about the same whatever the total size. Things to plan for on a large installation: the `time_entries` and `audit_log` tables grow without a retention limit (partitioning or archiving), schema changes on big tables should be run with an online-DDL tool rather than by the lazy upgrade, exports are built in memory per request, and a connection pool such as ProxySQL helps because every request opens its own database connection.

### nginx

Serve `public/` as root and send `*.php` to PHP-FPM; do not expose the parent directories (`config/`, `src/`, `views/`, `database/`, `tests/`).

## Security

- **Authentication:** usernames and passwords (`password_hash`, 10–72 characters), sign-in throttling per username and per IP (configurable), the same response time for unknown usernames, a new session id at sign-in, idle timeout, and a password change or reset ends the account's other sessions. Changing the password or restoring a backup asks for the current password, which is throttled as well.
- **Requests:** CSRF tokens on every state-changing request (forms and the JSON APIs), POST only for changes, every query parameterised, every output HTML-escaped, strict Content-Security-Policy without inline scripts, `X-Frame-Options`, `nosniff`, `no-store` caching of pages, `HttpOnly` + `SameSite` session cookie (`Secure` over HTTPS).
- **Separation:** a regular user can only reach their own data (checked by every query), and admin pages and actions return 403 for regular users. Administrators have no access to time reports.
- **Audit log:** time-reporting events carry no client, action or report details; a username mistyped into the password field is never stored.
- **Inputs** were reviewed and fuzzed with injection, markup, NUL-byte, header-injection, traversal and wrong-type payloads (see the 0.3.11 entry in the changelog). Redirects only go to local pages, uploads are validated line by line before a restore, exports neutralise spreadsheet formulas.
- **Things to know:** backups contain password hashes, the mail password and all time reports, so store them like the database; the mail password is kept in the database; the content-security policy still allows inline *styles*; locking a username after repeated failures lets someone deliberately lock a known account for the lock-out time.
- Serve the site over HTTPS, keep `config/` out of the web root, and change the placeholder administrator's password immediately after upgrading.

## Development

```bash
php -S 127.0.0.1:8080 -t public
php tests/run.php        # logic tests (dates, calendar, holidays, VAT, exports, backups, audit, security helpers), no database needed
php tests/i18n.php       # checks that every translation key used in the code exists in src/lang/*.php
```

Conventions: English text is the translation key (`t()` raw, `te()` escaped, `th()` escaped with raw HTML variables; strings used by JavaScript go through `I18n::JS_KEYS`), one script per page in `public/`, JavaScript only in `public/assets/js/` (no inline scripts), and every release bumps `TT_VERSION` in `src/bootstrap.php`, `VERSION`, this file and `CHANGELOG.md`. Database changes are a new `toSchemaN()` step in `src/Migrator.php` plus the same change in `database/schema.sql`, with `TT_SCHEMA` bumped.

## Project layout

```
public/       web root: one script per page (calendar, clients, actions, hours, export, account, login, password, setup,
              admin*.php for the admin panel), api/ (JSON endpoints for reports and export options), assets/ (css, js, icons)
src/          classes: Auth, Audit, Backup, Calendar, Config, Db, DbSessionHandler, Holidays, I18n, Installer, Migrator,
              Settings, Smtp, SystemInfo, View, helpers.php, bootstrap.php; Repository/* (Actions, Clients, Entries,
              FreeDays, Users, WorkingHours); Export/* (Csv, Xlsx, Text exporters and the shared report builder)
views/        PHP templates (layout, pages, partials)
database/     schema.sql
config/       generated config.php (not committed); safety copies written by a restore
src/lang/     translations (sv.php)
tests/        run.php, i18n.php
```

## Notes

- Time reports are stored as local wall-clock time (date + start + end) and are limited to a single day; use `24:00` to run to midnight.
- Hours, summaries, amounts and exports always use **net** time (time span minus unpaid break).
- Estimated amounts = net hours × client hourly rate × action multiplier, for billable actions only; the VAT-inclusive figure is the amount × (1 + the client's VAT %).
- Excel export needs the PHP `zip` extension; CSV and text export always work.
- The month view shows the first three reports of a day; the rest are behind "+N more" (open the day or week to see and move them).

See [CHANGELOG.md](CHANGELOG.md) for the history of every release.
