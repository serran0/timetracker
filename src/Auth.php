<?php
declare(strict_types=1);

namespace TimeTracker;

/**
 * Session-based authentication with brute-force throttling.
 */
final class Auth
{
    private const MAX_FAILURES_PER_USER = 5;
    private const MAX_FAILURES_PER_IP = 20;
    private const LOCK_MINUTES = 15;
    public const IDLE_SECONDS = 12 * 3600;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        if (time() - (int) ($_SESSION['last_activity'] ?? 0) > self::IDLE_SECONDS) {
            self::logout();
            self::$loaded = true;
            return null;
        }
        $user = Db::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $id]);
        if (!$user) {
            self::logout();
            self::$loaded = true;
            return null;
        }
        $_SESSION['last_activity'] = time();
        \TimeTracker\I18n::setLocale((string) ($user['locale'] ?? 'en'));
        // Each user works in their own timezone ("today", "now" markers).
        if (in_array($user['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            date_default_timezone_set($user['timezone']);
        }
        return self::$user = $user;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    /** Redirect to the login page unless logged in. Returns the user row. */
    public static function require(): array
    {
        $user = self::user();
        if (!$user) {
            $next = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && preg_match('/^[a-z_]+\.php$/', $next) && $next !== 'login.php') {
                $qs = $_SERVER['QUERY_STRING'] ?? '';
                $next .= $qs !== '' ? '?' . $qs : '';
                redirect('login.php?next=' . urlencode($next));
            }
            redirect('login.php');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::require();
        if (!$user['is_admin']) {
            http_response_code(403);
            exit(t('Administrator access required.'));
        }
        return $user;
    }

    /** Only allow redirects to a local page like "calendar.php?view=week". */
    public static function safeNext(string $next): string
    {
        return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_%=&\[\].:+\-]*)?$/', $next) ? $next : 'calendar.php';
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public static function attempt(string $username, string $password): array
    {
        $ip = substr(client_ip(), 0, 45);
        $username = substr($username, 0, 64);

        if (self::isLocked($username, $ip)) {
            return ['ok' => false, 'error' => t('Too many failed attempts. Please wait {min} minutes and try again.', ['min' => self::LOCK_MINUTES])];
        }

        $user = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        // Always run a hash verification so response time does not reveal valid usernames.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ3F1mQ0Jz0y0gk8pXxO3wYkq0qk0e';
        $valid = password_verify($password, $hash) && $user && $user['is_active'];

        if (!$valid) {
            Db::run('INSERT INTO login_attempts (username, ip) VALUES (?, ?)', [$username, $ip]);
            // Housekeeping off the hot path: now and then, in small indexed batches (never on a successful login).
            if (random_int(1, 25) === 1) {
                Db::run('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 2000');
            }
            return ['ok' => false, 'error' => t('Invalid username or password.')];
        }

        session_regenerate_id(true);
        set_lang_cookie(\TimeTracker\I18n::isValid((string) ($user['locale'] ?? '')) ? $user['locale'] : 'en');
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['_csrf']);

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        Db::run('DELETE FROM login_attempts WHERE username = ?', [$username]);
        self::$loaded = false;
        self::$user = null;
        return ['ok' => true, 'error' => null];
    }

    private static function isLocked(string $username, string $ip): bool
    {
        $window = 'attempted_at > DATE_SUB(NOW(), INTERVAL ' . self::LOCK_MINUTES . ' MINUTE)';
        $byUser = (int) Db::value("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND $window", [$username]);
        $byIp = (int) Db::value("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND $window", [$ip]);
        return $byUser >= self::MAX_FAILURES_PER_USER || $byIp >= self::MAX_FAILURES_PER_IP;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$user = null;
        self::$loaded = false;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
