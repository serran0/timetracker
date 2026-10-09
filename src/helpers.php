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

/** Does $ip fall inside $cidr ("10.0.0.0/8", "2001:db8::/32") or equal a single address? Handles IPv4 and IPv6. */
function ip_in_range(string $ip, string $cidr): bool
{
    [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    $a = @inet_pton($ip);
    $b = @inet_pton((string) $net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) {
        return false;
    }
    $max = strlen($a) * 8;
    $bits = $bits === null ? $max : (int) $bits;
    if ($bits < 0 || $bits > $max) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if ($bytes && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
}

/**
 * The visitor's IP address. X-Forwarded-For is only believed when the request really came from one of the
 * configured trusted proxies (config key "trusted_proxies"); then the right-most entry that is not itself a trusted
 * proxy is the client. Without that setting REMOTE_ADDR is used, so the header cannot be forged.
 * @param string[] $trusted IP addresses or CIDR ranges
 */
function client_ip_from(array $server, array $trusted): string
{
    $remote = to_str($server['REMOTE_ADDR'] ?? '');
    $isTrusted = static function (string $ip) use ($trusted): bool {
        foreach ($trusted as $range) {
            if (is_string($range) && ip_in_range($ip, $range)) {
                return true;
            }
        }
        return false;
    };
    if ($remote === '' || !$trusted || !$isTrusted($remote)) {
        return $remote !== '' ? $remote : '0.0.0.0';
    }
    $chain = array_reverse(array_map('trim', explode(',', to_str($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
    foreach ($chain as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            break; // garbage in the chain: stop trusting anything further left
        }
        if (!$isTrusted($ip)) {
            return $ip;
        }
    }
    return $remote;
}

function client_ip(): string
{
    $trusted = \TimeTracker\Config::get('trusted_proxies', []);
    return client_ip_from($_SERVER, is_array($trusted) ? $trusted : []);
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
        exit(t('Invalid or expired form token. Go back, reload the page and try again.'));
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

/** Minutes two ranges [a1,a2) and [b1,b2) have in common. */
function interval_overlap(int $a1, int $a2, int $b1, int $b2): int
{
    return max(0, min($a2, $b2) - max($a1, $b1));
}

/** Duration as h:mm, e.g. 450 -> "7:30". */
function fmt_dur(int|float $minutes): string
{
    $m = (int) round($minutes);
    return intdiv($m, 60) . ':' . sprintf('%02d', $m % 60);
}

/** Compact decimal hours without trailing zeros, e.g. 480 -> "8", 450 -> "7.5" ("7,5" in Swedish). */
function fmt_hours_short(int|float $minutes): string
{
    $s = fmt_dec($minutes);
    $mark = \TimeTracker\I18n::decimalMark();
    return str_contains($s, $mark) ? rtrim(rtrim($s, '0'), $mark) : $s;
}

/** Duration as decimal hours, e.g. 450 -> "7.50" ("7,50" in Swedish). Pass $mark to force a decimal mark. */
function fmt_dec(int|float $minutes, int $decimals = 2, ?string $mark = null): string
{
    return number_format($minutes / 60, $decimals, $mark ?? \TimeTracker\I18n::decimalMark(), '');
}

/** "1 000,00 SEK (1 250,00 SEK)": the amount with the VAT-inclusive figure in parentheses when VAT applies. */
function fmt_money_vat(float $net, ?float $gross, string $currency = '', ?string $mark = null): string
{
    $out = fmt_money($net, $currency, $mark);
    return ($gross !== null && abs($gross - $net) >= 0.005) ? $out . ' (' . fmt_money($gross, $currency, $mark) . ')' : $out;
}

function fmt_money(float $amount, string $currency = '', ?string $mark = null): string
{
    return number_format($amount, 2, $mark ?? \TimeTracker\I18n::decimalMark(), ' ') . ($currency !== '' ? ' ' . $currency : '');
}

function valid_color(string $c, string $fallback = '#4f46e5'): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $fallback;
}

/** A request value as a string: arrays, nulls and objects (from ?x[]=1 style input) become the default instead of "Array". */
function to_str(mixed $v, string $default = ''): string
{
    return is_string($v) ? $v : ((is_int($v) || is_float($v)) ? (string) $v : $default);
}

function valid_date(string $d): ?DateTimeImmutable
{
    // Strict shape first: createFromFormat() throws on NUL bytes and is lenient about odd input.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $d)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $dt : null;
}

/** Build a relative URL with query parameters (null values are dropped). */
function url(string $page, array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '' && $v !== []);
    return $page . ($params ? '?' . http_build_query($params) : '');
}

/* -------------------------------------------------------------------- i18n */

/** Remember the chosen language for the login/setup pages (not sensitive, so a plain cookie). */
function set_lang_cookie(string $locale): void
{
    if (!headers_sent()) {
        setcookie('tt_lang', $locale, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'secure' => is_https()]);
    }
    $_COOKIE['tt_lang'] = $locale;
}


/** Translate (raw). {placeholders} in the text are replaced with $vars. */
function t(string $text, array $vars = []): string
{
    return \TimeTracker\I18n::t($text, $vars);
}

/** Translate and HTML-escape; $vars are treated as plain text. */
function te(string $text, array $vars = []): string
{
    return e(\TimeTracker\I18n::t($text, $vars));
}

/** Translate, escape the text and insert $vars verbatim – they must already be safe HTML (e.g. a link). */
function th(string $text, array $vars = []): string
{
    $safe = [];
    foreach ($vars as $k => $v) {
        $safe[$k] = "\0" . $k . "\0";
    }
    $out = e(\TimeTracker\I18n::t($text, $safe));
    foreach ($vars as $k => $v) {
        $out = str_replace("\0" . $k . "\0", (string) $v, $out);
    }
    return $out;
}

/** Label for an action name (standard names are translated; customised names are left untouched). */
function action_label(string $name): string
{
    return \TimeTracker\I18n::actionLabel($name);
}

/** Upper-case the first letter (multibyte safe) – used for headings built from lower-case Swedish month/day names. */
function ucf(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

/**
 * DateTime::format() with localised month and day names. Supports the usual letters; D, l, M and F are
 * translated, a backslash escapes the next character.
 */
function date_l10n(DateTimeInterface $d, string $format): string
{
    $out = '';
    for ($i = 0, $n = strlen($format); $i < $n; $i++) {
        $c = $format[$i];
        if ($c === '\\' && $i + 1 < $n) {
            $out .= $format[++$i];
        } elseif ($c === 'D') {
            $out .= \TimeTracker\I18n::dayName((int) $d->format('N'), true);
        } elseif ($c === 'l') {
            $out .= \TimeTracker\I18n::dayName((int) $d->format('N'));
        } elseif ($c === 'M') {
            $out .= \TimeTracker\I18n::monthName((int) $d->format('n'), true);
        } elseif ($c === 'F') {
            $out .= \TimeTracker\I18n::monthName((int) $d->format('n'));
        } elseif (ctype_alpha($c)) {
            $out .= $d->format($c);
        } else {
            $out .= $c;
        }
    }
    return $out;
}
