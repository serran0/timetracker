<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use DateTimeImmutable;
use TimeTracker\Db;

/**
 * The user's own work-free days (a single day or a range such as a vacation week).
 */
final class FreeDays
{
    private const MAX_SPAN_DAYS = 366;

    /** Upcoming and current first, past ones last. */
    public static function all(int $uid): array
    {
        return Db::all('SELECT * FROM free_days WHERE user_id = ? ORDER BY (end_date < CURDATE()), start_date, id', [$uid]);
    }

    /** Pure validation: @return array{0: array, 1: string[]} */
    public static function parse(array $in): array
    {
        $errors = [];
        $from = valid_date(trim(to_str($in['from'] ?? '')));
        $toRaw = trim(to_str($in['to'] ?? ''));
        $to = $toRaw === '' ? $from : valid_date($toRaw);
        if (!$from) {
            $errors[] = t('Choose a start date.');
        } elseif (!$to) {
            $errors[] = t('The end date is not valid.');
        } elseif ($to < $from) {
            $errors[] = t('The end date must not be before the start date.');
        } elseif ($from->diff($to)->days >= self::MAX_SPAN_DAYS) {
            $errors[] = t('A work-free period can be at most {n} days.', ['n' => self::MAX_SPAN_DAYS]);
        }
        $name = trim(to_str($in['name'] ?? ''));
        if (mb_strlen($name) > 120) {
            $errors[] = t('The name is too long (max 120 characters).');
        }
        return [[
            'start_date' => $from?->format('Y-m-d'),
            'end_date'   => $to?->format('Y-m-d'),
            'name'       => $name !== '' ? $name : null,
        ], $errors];
    }

    public static function add(int $uid, array $d): int
    {
        return Db::insert('INSERT INTO free_days (user_id, start_date, end_date, name) VALUES (?,?,?,?)', [$uid, $d['start_date'], $d['end_date'], $d['name']]);
    }

    public static function delete(int $uid, int $id): void
    {
        Db::run('DELETE FROM free_days WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    /** Label shown for a day off ("Day off" when the user gave no name). */
    public static function label(?string $name): string
    {
        return ($name !== null && trim($name) !== '') ? $name : t('Day off');
    }

    /**
     * Every date of the user's free periods inside a range.
     * @return array<string, string> Y-m-d => name
     */
    public static function expand(int $uid, string $from, string $to): array
    {
        if ($uid <= 0) {
            return [];
        }
        $out = [];
        $rows = Db::all('SELECT start_date, end_date, name FROM free_days WHERE user_id = ? AND start_date <= ? AND end_date >= ? ORDER BY start_date, id', [$uid, $to, $from]);
        foreach ($rows as $r) {
            $d = new DateTimeImmutable(max($r['start_date'], $from));
            $last = new DateTimeImmutable(min($r['end_date'], $to));
            for (; $d <= $last; $d = $d->modify('+1 day')) {
                $key = $d->format('Y-m-d');
                $label = self::label($r['name']);
                $out[$key] = isset($out[$key]) ? $out[$key] . ' · ' . $label : $label;
            }
        }
        return $out;
    }
}
