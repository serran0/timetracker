<?php
declare(strict_types=1);

namespace TimeTracker;

/**
 * Application settings edited in the admin panel (table `settings`). Loaded on first use and cached for the request;
 * the time-reporting pages never touch it, so it adds no query to them.
 */
final class Settings
{
    public const DEFAULTS = [
        // general: defaults offered when an administrator creates an account
        'default_timezone'      => '',
        'default_currency'      => 'SEK',
        'default_locale'        => 'en',
        // security
        'login.max_per_user'    => '5',
        'login.max_per_ip'      => '20',
        'login.lock_minutes'    => '15',
        // mail
        'mail.enabled'          => '0',
        'mail.host'             => '',
        'mail.port'             => '587',
        'mail.encryption'       => 'tls',   // tls (STARTTLS), ssl (implicit TLS) or none
        'mail.username'         => '',
        'mail.password'         => '',
        'mail.from_email'       => '',
        'mail.from_name'        => 'Timetracker',
        'mail.recipients'       => '',      // administrator notification addresses, one per line
    ];

    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (Db::all('SELECT setting_key, setting_value FROM settings') as $r) {
                    if (array_key_exists($r['setting_key'], self::DEFAULTS)) {
                        self::$cache[$r['setting_key']] = (string) $r['setting_value'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('Timetracker settings could not be read: ' . $e->getMessage());
            }
        }
        return self::$cache;
    }

    public static function get(string $key): string
    {
        return self::load()[$key] ?? (self::DEFAULTS[$key] ?? '');
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /** @param array<string, string> $values only known keys are stored */
    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, self::DEFAULTS)) {
                continue;
            }
            Db::run('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$k, $v]);
        }
        self::$cache = null;
    }

    /** Valid, de-duplicated addresses from a free-text list (separated by newlines, commas or semicolons). */
    public static function recipients(?string $text = null): array
    {
        $text ??= self::get('mail.recipients');
        $out = [];
        foreach (preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $a) {
            if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($a)] ??= $a;
            }
        }
        return array_values($out);
    }
}
