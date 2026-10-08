<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Db;

final class WorkingHours
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * Intervals per ISO weekday, in minutes since midnight.
     * @return array<int, list<array{0:int,1:int}>>
     */
    public static function intervals(int $uid): array
    {
        $out = array_fill(1, 7, []);
        $rows = Db::all('SELECT weekday, start_time, end_time FROM working_hours WHERE user_id = ? ORDER BY weekday, start_time', [$uid]);
        foreach ($rows as $r) {
            $out[(int) $r['weekday']][] = [time_to_minutes($r['start_time']), time_to_minutes($r['end_time'])];
        }
        return $out;
    }

    /**
     * Parses and validates posted hours: wh[weekday][] = ['start' => .., 'end' => ..].
     * @return array{0: array<int, list<array{0:int,1:int}>>, 1: string[]}
     */
    public static function parse(array $posted): array
    {
        $result = array_fill(1, 7, []);
        $errors = [];
        foreach (self::DAYS as $d => $label) {
            $rows = $posted[$d] ?? [];
            if (!is_array($rows)) {
                continue;
            }
            $list = [];
            foreach ($rows as $row) {
                $s = trim((string) ($row['start'] ?? ''));
                $e = trim((string) ($row['end'] ?? ''));
                if ($s === '' && $e === '') {
                    continue;
                }
                $sm = parse_time_minutes($s);
                $em = parse_time_minutes($e);
                if ($sm === null || $em === null) {
                    $errors[] = "$label: \"$s\" – \"$e\" is not a valid time range (use HH:MM).";
                    continue;
                }
                if ($em <= $sm) {
                    $errors[] = "$label: end time must be after start time.";
                    continue;
                }
                $list[] = [$sm, $em];
            }
            usort($list, static fn($a, $b) => $a[0] <=> $b[0]);
            foreach ($list as $i => $iv) {
                if ($i > 0 && $iv[0] < $list[$i - 1][1]) {
                    $errors[] = "$label: working hour intervals overlap.";
                    break;
                }
            }
            $result[$d] = $list;
        }
        return [$result, $errors];
    }

    public static function save(int $uid, array $intervals): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::run('DELETE FROM working_hours WHERE user_id = ?', [$uid]);
            $ins = $pdo->prepare('INSERT INTO working_hours (user_id, weekday, start_time, end_time) VALUES (?,?,?,?)');
            foreach ($intervals as $day => $list) {
                foreach ($list as [$s, $e]) {
                    $ins->execute([$uid, $day, minutes_to_hhmm($s) . ':00', minutes_to_hhmm($e) . ':00']);
                }
            }
            $pdo->commit();
        } catch (\Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }
    }

    /** Total scheduled minutes in a week. */
    public static function weeklyMinutes(array $intervals): int
    {
        $sum = 0;
        foreach ($intervals as $list) {
            foreach ($list as [$s, $e]) {
                $sum += $e - $s;
            }
        }
        return $sum;
    }
}
