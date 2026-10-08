<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Auth;
use TimeTracker\Db;

final class Users
{
    public const PER_PAGE = 50;

    /** Escape LIKE wildcards so a search for "100%" or "a_b" matches literally. */
    public static function likeEscape(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    /**
     * One page of users, optionally filtered by a search text (username or display name).
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public static function page(string $q = '', int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $where = '';
        $params = [];
        $q = trim($q);
        if ($q !== '') {
            $like = '%' . self::likeEscape($q) . '%';
            $where = ' WHERE username LIKE ? OR display_name LIKE ?';
            $params = [$like, $like];
        }
        $total = (int) Db::value('SELECT COUNT(*) FROM users' . $where, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = Db::all(
            'SELECT id, username, display_name, is_admin, is_active, timezone, locale, created_at, last_login_at FROM users'
            . $where . ' ORDER BY username LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function validateUsername(string $u): ?string
    {
        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $u)) {
            return t('Username must be 3-64 characters: letters, digits, dot, dash or underscore.');
        }
        return null;
    }

    public static function validatePassword(string $p): ?string
    {
        if (mb_strlen($p) < 10) {
            return t('Password must be at least 10 characters.');
        }
        return null;
    }

    /** Creates the user with default working hours and lunch window (actions are created per client). */
    public static function create(string $username, string $password, string $displayName, bool $admin, string $timezone, string $currency = 'EUR', string $locale = 'en', ?\PDO $pdo = null): int
    {
        $pdo ??= Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (username, display_name, password_hash, is_admin, timezone, currency, locale) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$username, $displayName !== '' ? $displayName : $username, Auth::hash($password), $admin ? 1 : 0, $timezone, $currency, \TimeTracker\I18n::isValid($locale) ? $locale : 'en']);
        $id = (int) $pdo->lastInsertId();
        self::seedDefaults($id, $pdo);
        return $id;
    }

    public static function seedDefaults(int $userId, \PDO $pdo): void
    {
        $pdo->prepare('UPDATE users SET lunch_start = ?, lunch_end = ? WHERE id = ?')->execute(['12:00:00', '13:00:00', $userId]);
        $w = $pdo->prepare('INSERT INTO working_hours (user_id, weekday, start_time, end_time) VALUES (?,?,?,?)');
        for ($d = 1; $d <= 5; $d++) {
            $w->execute([$userId, $d, '08:00:00', '17:00:00']);
        }
    }

    public static function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        return (bool) Db::value('SELECT 1 FROM users WHERE username = ? AND id <> ?', [$username, $exceptId ?? 0]);
    }
}
