<?php
declare(strict_types=1);

namespace TimeTracker;

/**
 * Minimal translation layer. The English text is the key, so source code stays readable and any string
 * without a translation simply falls back to English. Dictionaries live in src/lang/<locale>.php.
 *
 * Month/day names come from the tables below (not from the server's locale), so output does not depend
 * on which system locales or PHP extensions the host has.
 */
final class I18n
{
    public const LOCALES = ['en' => 'English', 'sv' => 'Svenska'];
    public const DEFAULT = 'en';

    private const MONTHS = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'sv' => ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'],
    ];
    private const MONTHS_SHORT = [
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'sv' => ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'],
    ];
    /** Index 1 = Monday ... 7 = Sunday (ISO). */
    private const DAYS = [
        'en' => [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'sv' => [1 => 'måndag', 'tisdag', 'onsdag', 'torsdag', 'fredag', 'lördag', 'söndag'],
    ];
    private const DAYS_SHORT = [
        'en' => [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'sv' => [1 => 'mån', 'tis', 'ons', 'tors', 'fre', 'lör', 'sön'],
    ];

    /** Strings the browser-side scripts need (the keys used with t() in public/assets/js). */
    public const JS_KEYS = [
        'Copied Monday to Tuesday–Friday. Remember to save.',
        'Add a client with at least one action first.',
        '— no actions —',
        'Archived',
        '{n} report',
        '{n} reports',
        '{h} h net',
        'already has {n} report',
        'already has {n} reports',
        'Choose a date.',
        'Enter times as HH:MM (e.g. 08:30).',
        'End time must be after the start time.',
        'The break must be shorter than the time span.',
        'Tick at least one day.',
        'This client has no actions yet – add some on the Actions page.',
        'Session expired.',
        'Unexpected server response.',
        'Could not save.',
        'Could not delete.',
        'Delete this time report?',
        'Day {n}',
        'Edit time report',
        'New time report',
        'New time reports',
        'Duplicate',
        'Delete',
        'Open day',
        'Open week',
        'New time report…',
        'Report whole working day {from}–{to}',
        'Report whole working day {from}–{to} (−{min} min break)',
        'New time report {from}–{to}',
        'New report {from}–{to}',
        'Report whole working week (minus daily lunch break)',
        'Week {n}',
    ];

    private static string $locale = self::DEFAULT;
    /** @var array<string, array<string,string>> */
    private static array $dicts = [];

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function isValid(string $locale): bool
    {
        return isset(self::LOCALES[$locale]);
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = self::isValid($locale) ? $locale : self::DEFAULT;
    }

    /** Language for visitors who are not signed in: cookie first, then the browser's Accept-Language. */
    public static function detect(): string
    {
        $cookie = $_COOKIE['tt_lang'] ?? '';
        if (is_string($cookie) && self::isValid($cookie)) {
            return $cookie;
        }
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            $lang = substr($tag, 0, 2);
            if (self::isValid($lang)) {
                return $lang;
            }
        }
        return self::DEFAULT;
    }

    private static function dict(): array
    {
        $l = self::$locale;
        if (!isset(self::$dicts[$l])) {
            $file = __DIR__ . '/lang/' . $l . '.php';
            self::$dicts[$l] = ($l !== self::DEFAULT && is_file($file)) ? (array) require $file : [];
        }
        return self::$dicts[$l];
    }

    /** Translate and substitute {placeholders}. Unknown strings fall back to the English text. */
    public static function t(string $text, array $vars = []): string
    {
        $out = self::dict()[$text] ?? $text;
        if ($vars) {
            $map = [];
            foreach ($vars as $k => $v) {
                $map['{' . $k . '}'] = (string) $v;
            }
            $out = strtr($out, $map);
        }
        return $out;
    }

    /** Label for an action: the standard names are translated, anything the user renamed is shown as stored. */
    public static function actionLabel(string $name): string
    {
        return self::dict()['action:' . $name] ?? $name;
    }

    public static function monthName(int $m, bool $short = false): string
    {
        $l = self::$locale;
        return ($short ? self::MONTHS_SHORT : self::MONTHS)[$l][$m - 1];
    }

    public static function dayName(int $isoDay, bool $short = false): string
    {
        $l = self::$locale;
        return ($short ? self::DAYS_SHORT : self::DAYS)[$l][$isoDay];
    }

    public static function decimalMark(): string
    {
        return self::$locale === 'sv' ? ',' : '.';
    }

    /** Everything the JavaScript needs: its strings, weekday names and the decimal mark. */
    public static function forJs(): array
    {
        $strings = [];
        foreach (self::JS_KEYS as $k) {
            $strings[$k] = self::dict()[$k] ?? $k;
        }
        $dow = [];
        for ($i = 0; $i < 7; $i++) {
            $dow[] = self::dayName($i === 0 ? 7 : $i, true); // JS getDay(): 0 = Sunday
        }
        return ['locale' => self::$locale, 'decimal' => self::decimalMark(), 'dow' => $dow, 'strings' => $strings];
    }
}
