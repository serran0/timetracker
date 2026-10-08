<?php
declare(strict_types=1);

namespace TimeTracker;

use Throwable;

/**
 * The audit log: who did what and when.
 *
 * Privacy rule: entries are opaque about time-reporting data. They say that "a client was created" or "a time report was
 * deleted" but never record names of clients, action labels, descriptions, times or amounts. Account-level facts that an
 * administrator is responsible for (usernames, IP addresses of sign-ins, settings sections) are recorded.
 *
 * Each event has a stable code, a category, a type label and a description template. The template and its parameters are
 * stored separately and translated when the log is shown, so the log reads in the viewing administrator's language.
 */
final class Audit
{
    /** Category code => label (used for grouping in the filter). */
    public const CATEGORIES = [
        'auth'    => 'Sign-in',
        'account' => 'Account',
        'time'    => 'Time reporting',
        'admin'   => 'Administration',
    ];

    /** code => [category, type label, description template] */
    public const EVENTS = [
        'auth.login'            => ['auth', 'Sign in', 'Signed in from {ip}'],
        'auth.logout'           => ['auth', 'Sign out', 'Signed out'],
        'auth.login_failed'     => ['auth', 'Failed sign-in', 'Wrong password for {username} from {ip}'],
        'auth.login_unknown'    => ['auth', 'Failed sign-in (unknown user)', 'Sign-in attempt with an unknown username from {ip}'],
        'auth.locked'           => ['auth', 'Sign-in blocked', 'Too many failed attempts: sign-in blocked from {ip}'],
        'auth.password_changed' => ['auth', 'Password changed', 'Changed their own password'],
        'account.settings'      => ['account', 'Settings saved', 'Saved their account settings'],
        'account.profile'       => ['account', 'Profile saved', 'Saved their profile'],
        'account.renamed'       => ['account', 'Username changed', 'Changed their own username from {old} to {new}'],
        'hours.save'            => ['account', 'Working hours saved', 'Saved their working hours'],
        'freeday.add'           => ['account', 'Work-free day added', 'Added a work-free day'],
        'freeday.remove'        => ['account', 'Work-free day removed', 'Removed a work-free day'],
        'client.create'         => ['time', 'Client created', 'Created a client'],
        'client.update'         => ['time', 'Client changed', 'Changed a client'],
        'client.archive'        => ['time', 'Client archived', 'Archived a client'],
        'client.restore'        => ['time', 'Client restored', 'Restored a client'],
        'client.delete'         => ['time', 'Client deleted', 'Deleted a client'],
        'action.create'         => ['time', 'Action created', 'Created an action'],
        'action.update'         => ['time', 'Action changed', 'Changed an action'],
        'action.archive'        => ['time', 'Action archived', 'Archived an action'],
        'action.restore'        => ['time', 'Action restored', 'Restored an action'],
        'action.delete'         => ['time', 'Action deleted', 'Deleted an action'],
        'action.bulk'           => ['time', 'Actions added', 'Added standard actions or copied actions to a client'],
        'entry.create'          => ['time', 'Time report created', 'Created {count} time report(s)'],
        'entry.update'          => ['time', 'Time report changed', 'Changed a time report'],
        'entry.delete'          => ['time', 'Time report deleted', 'Deleted a time report'],
        'export.download'       => ['time', 'Export', 'Exported time reports as {format}'],
        'user.create'           => ['admin', 'User created', 'Created user account {username}'],
        'admin.create'          => ['admin', 'Administrator created', 'Created administrator account {username}'],
        'user.rename'           => ['admin', 'Username changed', 'Changed the username {old} to {new}'],
        'user.password_reset'   => ['admin', 'Password reset', 'Reset the password for {username}'],
        'user.enable'           => ['admin', 'Account enabled', 'Enabled the account {username}'],
        'user.disable'          => ['admin', 'Account disabled', 'Disabled the account {username}'],
        'settings.general'      => ['admin', 'Settings changed', 'Changed the general settings'],
        'settings.security'     => ['admin', 'Settings changed', 'Changed the security settings'],
        'settings.mail'         => ['admin', 'Settings changed', 'Changed the mail settings'],
        'mail.test'             => ['admin', 'Test email', 'Sent a test email'],
        'mail.test_failed'      => ['admin', 'Test email failed', 'The test email could not be sent'],
        'system.privilege_test' => ['admin', 'System test', 'Ran the database privilege test'],
        'backup.create'         => ['admin', 'Backup created', 'Downloaded a database backup ({format})'],
        'backup.restore'        => ['admin', 'Database restored', 'Restored the database from a backup file'],
        'backup.restore_failed' => ['admin', 'Restore failed', 'A database restore failed'],
        'system.install'        => ['admin', 'Installed', 'Installed Timetracker'],
        'system.roles'          => ['admin', 'Roles separated', 'Removed administrator rights from existing accounts and created the placeholder administrator {username}'],
    ];

    public const PER_PAGE_CHOICES = [50, 100, 250, 500];

    /**
     * Records an event. Never throws: a failing log must not break the action being logged.
     * @param array<string, scalar|null> $params values for the placeholders of the description template
     */
    public static function log(string $code, array $params = [], ?array $actor = null, ?\PDO $pdo = null): void
    {
        try {
            if (!isset(self::EVENTS[$code])) {
                return;
            }
            $actor ??= Auth::user();
            $row = [
                (new \DateTimeImmutable('now', new \DateTimeZone((string) Config::get('app.timezone', 'UTC'))))->format('Y-m-d H:i:s'), // one timezone for every actor
                $actor['id'] ?? null,
                mb_substr((string) ($actor['username'] ?? ''), 0, 64),
                $code,
                $params ? json_encode($params, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            ];
            $sql = 'INSERT INTO audit_log (created_at, user_id, username, action, params) VALUES (?,?,?,?,?)';
            if ($pdo) {
                $pdo->prepare($sql)->execute($row);
            } else {
                Db::run($sql, $row);
            }
        } catch (Throwable $e) {
            error_log('Timetracker audit log failed: ' . $e->getMessage());
        }
    }

    /** Translated type label of an event code. */
    public static function typeLabel(string $code): string
    {
        return isset(self::EVENTS[$code]) ? t(self::EVENTS[$code][1]) : $code;
    }

    /** Translated, human readable description of a stored row. */
    public static function describe(array $row): string
    {
        $ev = self::EVENTS[$row['action']] ?? null;
        if (!$ev) {
            return (string) $row['action'];
        }
        $params = $row['params'] ? (json_decode((string) $row['params'], true) ?: []) : [];
        return t($ev[2], array_map(static fn($v) => (string) $v, $params));
    }

    /** Codes in a category, or the code itself. */
    private static function codesFor(string $filter): array
    {
        if (str_starts_with($filter, 'cat:')) {
            $cat = substr($filter, 4);
            return array_keys(array_filter(self::EVENTS, static fn($e) => $e[0] === $cat));
        }
        return isset(self::EVENTS[$filter]) ? [$filter] : [];
    }

    /**
     * One page of the log, newest first.
     * @param array{from?: string, to?: string, action?: string, user?: string} $f
     * @return array{rows: array, total: int, page: int, pages: int, per_page: int}
     */
    public static function page(array $f, int $page, int $perPage): array
    {
        $perPage = in_array($perPage, self::PER_PAGE_CHOICES, true) ? $perPage : self::PER_PAGE_CHOICES[0];
        $where = [];
        $params = [];
        if (($f['from'] ?? '') !== '') {
            $where[] = 'created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $where[] = 'created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        if (($f['action'] ?? '') !== '') {
            $codes = self::codesFor($f['action']);
            $where[] = $codes ? 'action IN (' . Db::placeholders($codes) . ')' : '0 = 1';
            array_push($params, ...$codes);
        }
        if (trim((string) ($f['user'] ?? '')) !== '') {
            $where[] = 'username LIKE ?'; // prefix match, so the (username, created_at) index can be used
            $params[] = \TimeTracker\Repository\Users::likeEscape(trim($f['user'])) . '%';
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::value('SELECT COUNT(*) FROM audit_log' . $w, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = Db::all(
            'SELECT id, created_at, user_id, username, action, params FROM audit_log' . $w
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }
}
