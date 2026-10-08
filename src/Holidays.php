<?php
declare(strict_types=1);

namespace TimeTracker;

use DateTimeImmutable;
use TimeTracker\Repository\FreeDays;

/**
 * Swedish red days (public holidays plus the de-facto days off Midsommarafton, Julafton and Nyårsafton),
 * calculated from their rules, so there is nothing to download or keep up to date and it works offline.
 * Names are translated through the normal dictionary, so they follow the user's language.
 *
 * Kinds: 'public' (statutory), 'eve' (customary day off), 'custom' (the user's own work-free days).
 */
final class Holidays
{
    /** Easter Sunday (anonymous Gregorian algorithm – no PHP calendar extension needed). */
    public static function easter(int $year): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /** First given ISO weekday (1 = Monday ... 7 = Sunday) on or after $from. */
    private static function onOrAfter(string $from, int $isoDay): DateTimeImmutable
    {
        $d = new DateTimeImmutable($from);
        while ((int) $d->format('N') !== $isoDay) {
            $d = $d->modify('+1 day');
        }
        return $d;
    }

    /**
     * All Swedish red days of a year.
     * @return array<string, array{kind: string, name: string}> keyed by Y-m-d
     */
    public static function sweden(int $year): array
    {
        $easter = self::easter($year);
        $rows = [
            ["$year-01-01", 'public', t("New Year's Day")],
            ["$year-01-06", 'public', t('Epiphany')],
            [$easter->modify('-2 days'), 'public', t('Good Friday')],
            [$easter, 'public', t('Easter Sunday')],
            [$easter->modify('+1 day'), 'public', t('Easter Monday')],
            ["$year-05-01", 'public', t('May Day')],
            [$easter->modify('+39 days'), 'public', t('Ascension Day')],
            [$easter->modify('+49 days'), 'public', t('Whit Sunday')],
            ["$year-06-06", 'public', t('National Day')],
            [self::onOrAfter("$year-06-19", 5), 'eve', t('Midsummer Eve')],      // the Friday of 19-25 June
            [self::onOrAfter("$year-06-20", 6), 'public', t('Midsummer Day')],   // the Saturday of 20-26 June
            [self::onOrAfter("$year-10-31", 6), 'public', t("All Saints' Day")], // the Saturday of 31 Oct-6 Nov
            ["$year-12-24", 'eve', t('Christmas Eve')],
            ["$year-12-25", 'public', t('Christmas Day')],
            ["$year-12-26", 'public', t('Boxing Day')],
            ["$year-12-31", 'eve', t("New Year's Eve")],
        ];
        $out = [];
        foreach ($rows as [$date, $kind, $name]) {
            $key = $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : $date;
            $out[$key] = ['kind' => $kind, 'name' => $name];
        }
        ksort($out);
        return $out;
    }

    /**
     * Red days and the user's own work-free days within a date range.
     * Public holidays only appear when the user has the setting switched on; own days are always shown.
     * @return array<string, array{kind: string, name: string}> keyed by Y-m-d
     */
    public static function forRange(array $user, string $from, string $to): array
    {
        $out = [];
        if (!empty($user['show_holidays'])) {
            for ($y = (int) substr($from, 0, 4); $y <= (int) substr($to, 0, 4); $y++) {
                foreach (self::sweden($y) as $date => $h) {
                    if ($date >= $from && $date <= $to) {
                        $out[$date] = $h;
                    }
                }
            }
        }
        foreach (FreeDays::expand((int) ($user['id'] ?? 0), $from, $to) as $date => $name) {
            $out[$date] = isset($out[$date])
                ? ['kind' => $out[$date]['kind'], 'name' => $out[$date]['name'] . ' · ' . $name]
                : ['kind' => 'custom', 'name' => $name];
        }
        ksort($out);
        return $out;
    }
}
