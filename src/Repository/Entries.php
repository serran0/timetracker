<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Db;

final class Entries
{
    /**
     * Filter array understood by search(): ['clients' => int[], 'actions' => int[], 'billable' => ''|'1'|'0'].
     * Rows come back decorated with minutes, HH:MM times and an amount.
     */
    public static function search(int $uid, string $from, string $to, array $filter = []): array
    {
        $sql = 'SELECT e.id, e.entry_date, e.start_time, e.end_time, e.description,
                       e.client_id, c.name AS client_name, c.color AS client_color, c.hourly_rate, c.reference AS client_reference,
                       e.action_id, a.name AS action_name, a.color AS action_color, a.rate_multiplier, a.is_billable
                FROM time_entries e
                JOIN clients c ON c.id = e.client_id
                JOIN actions a ON a.id = e.action_id
                WHERE e.user_id = ? AND e.entry_date BETWEEN ? AND ?';
        $params = [$uid, $from, $to];

        if (!empty($filter['clients'])) {
            $sql .= ' AND e.client_id IN (' . Db::placeholders($filter['clients']) . ')';
            array_push($params, ...$filter['clients']);
        }
        if (!empty($filter['actions'])) {
            $sql .= ' AND e.action_id IN (' . Db::placeholders($filter['actions']) . ')';
            array_push($params, ...$filter['actions']);
        }
        if (($filter['billable'] ?? '') === '1') {
            $sql .= ' AND a.is_billable = 1';
        } elseif (($filter['billable'] ?? '') === '0') {
            $sql .= ' AND a.is_billable = 0';
        }
        $sql .= ' ORDER BY e.entry_date, e.start_time, e.id';

        return array_map([self::class, 'decorate'], Db::all($sql, $params));
    }

    public static function find(int $uid, int $id): ?array
    {
        return Db::one('SELECT * FROM time_entries WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function decorate(array $r): array
    {
        $s = time_to_minutes($r['start_time']);
        $e = time_to_minutes($r['end_time']);
        $minutes = max(0, $e - $s);
        $rate = $r['hourly_rate'] !== null ? (float) $r['hourly_rate'] : null;
        $billable = (bool) $r['is_billable'];
        $r['start_min'] = $s;
        $r['end_min'] = $e;
        $r['start'] = minutes_to_hhmm($s);
        $r['end'] = minutes_to_hhmm($e);
        $r['minutes'] = $minutes;
        $r['billable'] = $billable;
        $r['effective_rate'] = ($rate !== null && $billable) ? round($rate * (float) $r['rate_multiplier'], 2) : null;
        $r['amount'] = $r['effective_rate'] !== null ? round($minutes / 60 * $r['effective_rate'], 2) : null;
        return $r;
    }

    /** Totals over a list of decorated rows. */
    public static function summarize(array $rows): array
    {
        $sum = ['minutes' => 0, 'billable_minutes' => 0, 'amount' => 0.0, 'count' => count($rows), 'by_client' => [], 'by_action' => []];
        foreach ($rows as $r) {
            $sum['minutes'] += $r['minutes'];
            if ($r['billable']) {
                $sum['billable_minutes'] += $r['minutes'];
            }
            $sum['amount'] += (float) $r['amount'];

            $c = &$sum['by_client'][$r['client_id']];
            $c ??= ['name' => $r['client_name'], 'color' => $r['client_color'], 'minutes' => 0, 'amount' => 0.0];
            $c['minutes'] += $r['minutes'];
            $c['amount'] += (float) $r['amount'];
            unset($c);

            $a = &$sum['by_action'][$r['action_id']];
            $a ??= ['name' => $r['action_name'], 'color' => $r['action_color'], 'minutes' => 0, 'amount' => 0.0];
            $a['minutes'] += $r['minutes'];
            $a['amount'] += (float) $r['amount'];
            unset($a);
        }
        uasort($sum['by_client'], static fn($x, $y) => $y['minutes'] <=> $x['minutes']);
        uasort($sum['by_action'], static fn($x, $y) => $y['minutes'] <=> $x['minutes']);
        return $sum;
    }

    /**
     * Validates posted entry data. Ownership of client/action is verified against the user.
     * @return array{0: array, 1: string[]}
     */
    public static function validate(int $uid, array $in, ?array $existing = null): array
    {
        $errors = [];

        $date = valid_date((string) ($in['date'] ?? ''));
        if (!$date) {
            $errors[] = 'Date is not valid.';
        }

        $start = parse_time_minutes((string) ($in['start'] ?? ''));
        $end = parse_time_minutes((string) ($in['end'] ?? ''));
        if ($start === null || $start >= 1440) {
            $errors[] = 'Start time is not valid (use HH:MM).';
        }
        if ($end === null) {
            $errors[] = 'End time is not valid (use HH:MM, 24:00 means midnight).';
        }
        if ($start !== null && $end !== null && $end <= $start) {
            $errors[] = 'End time must be after the start time.';
        }

        $clientId = (int) ($in['client_id'] ?? 0);
        $client = $clientId ? Clients::find($uid, $clientId) : null;
        if (!$client) {
            $errors[] = 'Choose a client.';
        } elseif ($client['is_archived'] && (!$existing || (int) $existing['client_id'] !== $clientId)) {
            $errors[] = 'That client is archived.';
        }

        $actionId = (int) ($in['action_id'] ?? 0);
        $action = $actionId ? Actions::find($uid, $actionId) : null;
        if (!$action) {
            $errors[] = 'Choose an action.';
        } elseif ($action['is_archived'] && (!$existing || (int) $existing['action_id'] !== $actionId)) {
            $errors[] = 'That action is archived.';
        }

        $desc = trim((string) ($in['description'] ?? ''));
        if (mb_strlen($desc) > 5000) {
            $errors[] = 'Description is too long (max 5000 characters).';
        }

        $data = [
            'client_id'   => $clientId,
            'action_id'   => $actionId,
            'entry_date'  => $date?->format('Y-m-d'),
            'start_time'  => $start !== null ? minutes_to_hhmm($start) . ':00' : null,
            'end_time'    => $end !== null ? minutes_to_hhmm($end) . ':00' : null,
            'description' => $desc !== '' ? $desc : null,
        ];
        return [$data, $errors];
    }

    public static function create(int $uid, array $d): int
    {
        return Db::insert(
            'INSERT INTO time_entries (user_id, client_id, action_id, entry_date, start_time, end_time, description) VALUES (?,?,?,?,?,?,?)',
            [$uid, $d['client_id'], $d['action_id'], $d['entry_date'], $d['start_time'], $d['end_time'], $d['description']]
        );
    }

    public static function update(int $uid, int $id, array $d): void
    {
        Db::run(
            'UPDATE time_entries SET client_id=?, action_id=?, entry_date=?, start_time=?, end_time=?, description=? WHERE id=? AND user_id=?',
            [$d['client_id'], $d['action_id'], $d['entry_date'], $d['start_time'], $d['end_time'], $d['description'], $id, $uid]
        );
    }

    public static function delete(int $uid, int $id): void
    {
        Db::run('DELETE FROM time_entries WHERE id = ? AND user_id = ?', [$id, $uid]);
    }
}
