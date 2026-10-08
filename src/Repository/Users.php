<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Auth;
use TimeTracker\Db;

final class Users
{
    public const DEFAULT_ACTIONS = [
        // name, color, rate multiplier, billable
        ['Normal working time', '#10b981', 1.00, 1],
        ['Overtime',            '#f59e0b', 1.50, 1],
        ['Emergency / call-out', '#ef4444', 2.00, 1],
        ['Travel',              '#0ea5e9', 1.00, 1],
        ['Internal / non-billable', '#94a3b8', 1.00, 0],
    ];

    public static function all(): array
    {
        return Db::all('SELECT id, username, display_name, is_admin, is_active, timezone, created_at, last_login_at FROM users ORDER BY username');
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function validateUsername(string $u): ?string
    {
        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $u)) {
            return 'Username must be 3-64 characters: letters, digits, dot, dash or underscore.';
        }
        return null;
    }

    public static function validatePassword(string $p): ?string
    {
        if (mb_strlen($p) < 10) {
            return 'Password must be at least 10 characters.';
        }
        return null;
    }

    /** Creates the user and gives them default action templates and working hours. */
    public static function create(string $username, string $password, string $displayName, bool $admin, string $timezone, string $currency = 'EUR', ?\PDO $pdo = null): int
    {
        $pdo ??= Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (username, display_name, password_hash, is_admin, timezone, currency) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$username, $displayName !== '' ? $displayName : $username, Auth::hash($password), $admin ? 1 : 0, $timezone, $currency]);
        $id = (int) $pdo->lastInsertId();
        self::seedDefaults($id, $pdo);
        return $id;
    }

    public static function seedDefaults(int $userId, \PDO $pdo): void
    {
        $a = $pdo->prepare('INSERT INTO actions (user_id, name, color, rate_multiplier, is_billable, sort_order) VALUES (?,?,?,?,?,?)');
        foreach (self::DEFAULT_ACTIONS as $i => [$name, $color, $mult, $billable]) {
            $a->execute([$userId, $name, $color, $mult, $billable, $i]);
        }
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
