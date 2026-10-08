<?php
declare(strict_types=1);

/**
 * Global helper functions (escaping, redirects, flash messages, CSRF, time formatting).
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from POST (falls back to GET). */
function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/* ------------------------------------------------------------------ flash */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ------------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $given = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($given) && $given !== '' && hash_equals(csrf_token(), $given);
}

/** Abort the request unless it is a POST carrying a valid CSRF token. */
function require_csrf(): void
{
    if (!is_post() || !csrf_valid()) {
        http_response_code(419);
        exit('Invalid or expired form token. Go back, reload the page and try again.');
    }
}

/* ------------------------------------------------------------ time helpers */

/**
 * Parse a user-typed time ("9", "9:30", "0930", "09.30", "24:00") into minutes since midnight.
 * Returns null when invalid. 24:00 (1440) is accepted as "end of day".
 */
function parse_time_minutes(string $s): ?int
{
    $s = trim($s);
    if (!preg_match('/^(\d{1,2})[:.]?(\d{2})$|^(\d{1,2})$/', $s, $m)) {
        return null;
    }
    if (isset($m[3]) && $m[3] !== '') {
        $h = (int) $m[3];
        $min = 0;
    } else {
        $h = (int) $m[1];
        $min = (int) $m[2];
    }
    if ($min > 59 || $h > 24 || ($h === 24 && $min > 0)) {
        return null;
    }
    return $h * 60 + $min;
}

/** "08:30:00" or "24:00:00" -> minutes. */
function time_to_minutes(string $t): int
{
    $p = explode(':', $t);
    return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0);
}

/** Minutes -> "HH:MM" (1440 -> "24:00"). */
function minutes_to_hhmm(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/** Duration as h:mm, e.g. 450 -> "7:30". */
function fmt_dur(int|float $minutes): string
{
    $m = (int) round($minutes);
    return intdiv($m, 60) . ':' . sprintf('%02d', $m % 60);
}

/** Duration as decimal hours, e.g. 450 -> "7.50". */
function fmt_dec(int|float $minutes, int $decimals = 2): string
{
    return number_format($minutes / 60, $decimals, '.', '');
}

function fmt_money(float $amount, string $currency = ''): string
{
    return number_format($amount, 2, '.', ' ') . ($currency !== '' ? ' ' . $currency : '');
}

function valid_color(string $c, string $fallback = '#4f46e5'): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $fallback;
}

function valid_date(string $d): ?DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $dt : null;
}

/** Build a relative URL with query parameters (null values are dropped). */
function url(string $page, array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '' && $v !== []);
    return $page . ($params ? '?' . http_build_query($params) : '');
}
