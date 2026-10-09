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
        $sql = 'SELECT e.id, e.entry_date, e.start_time, e.end_time, e.break_minutes, e.description,
                       e.client_id, c.name AS client_name, c.color AS client_color, c.hourly_rate, c.vat_percent, c.reference AS client_reference,
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
            // Actions are per client, so the filter matches by name ("all Overtime").
            $sql .= ' AND a.name IN (' . Db::placeholders($filter['actions']) . ')';
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

    /** Number of reports per date in a range, regardless of filters (date => count). */
    public static function dayCounts(int $uid, string $from, string $to): array
    {
        $out = [];
        foreach (Db::all('SELECT entry_date, COUNT(*) AS n FROM time_entries WHERE user_id = ? AND entry_date BETWEEN ? AND ? GROUP BY entry_date', [$uid, $from, $to]) as $r) {
            $out[$r['entry_date']] = (int) $r['n'];
        }
        return $out;
    }

    public static function find(int $uid, int $id): ?array
    {
        return Db::one('SELECT * FROM time_entries WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function decorate(array $r): array
    {
        $s = time_to_minutes($r['start_time']);
        $e = time_to_minutes($r['end_time']);
        $break = (int) ($r['break_minutes'] ?? 0);
        $gross = max(0, $e - $s);
        $minutes = max(0, $gross - $break); // net time: this is what is summed, billed and exported
        $rate = $r['hourly_rate'] !== null ? (float) $r['hourly_rate'] : null;
        $billable = (bool) $r['is_billable'];
        $r['start_min'] = $s;
        $r['end_min'] = $e;
        $r['start'] = minutes_to_hhmm($s);
        $r['end'] = minutes_to_hhmm($e);
        $r['minutes'] = $minutes;
        $r['action_label'] = \TimeTracker\I18n::actionLabel(to_str($r['action_name'] ?? ''));
        $r['gross_minutes'] = $gross;
        $r['break_minutes'] = $break;
        $r['billable'] = $billable;
        $r['effective_rate'] = ($rate !== null && $billable) ? round($rate * (float) $r['rate_multiplier'], 2) : null;
        $r['amount'] = $r['effective_rate'] !== null ? round($minutes / 60 * $r['effective_rate'], 2) : null;
        // Amount including the client's VAT; equal to the amount when the client has no VAT.
        $vat = (float) ($r['vat_percent'] ?? 0);
        $r['vat_percent'] = $vat;
        $r['amount_vat'] = $r['amount'] !== null ? round($r['amount'] * (1 + $vat / 100), 2) : null;
        return $r;
    }

    /** Totals over a list of decorated rows. */
    public static function summarize(array $rows): array
    {
        $sum = ['minutes' => 0, 'billable_minutes' => 0, 'amount' => 0.0, 'amount_vat' => 0.0, 'count' => count($rows), 'by_client' => [], 'by_action' => []];
        foreach ($rows as $r) {
            $sum['minutes'] += $r['minutes'];
            if ($r['billable']) {
                $sum['billable_minutes'] += $r['minutes'];
            }
            $sum['amount'] += (float) $r['amount'];
            $sum['amount_vat'] += (float) $r['amount_vat'];

            $c = &$sum['by_client'][$r['client_id']];
            $c ??= ['name' => $r['client_name'], 'label' => $r['client_name'], 'color' => $r['client_color'], 'minutes' => 0, 'amount' => 0.0, 'amount_vat' => 0.0];
            $c['minutes'] += $r['minutes'];
            $c['amount'] += (float) $r['amount'];
            $c['amount_vat'] += (float) $r['amount_vat'];
            unset($c);

            $a = &$sum['by_action'][$r['action_name']]; // grouped by name across clients
            $a ??= ['name' => $r['action_name'], 'label' => $r['action_label'], 'color' => $r['action_color'], 'minutes' => 0, 'amount' => 0.0, 'amount_vat' => 0.0];
            $a['minutes'] += $r['minutes'];
            $a['amount'] += (float) $r['amount'];
            $a['amount_vat'] += (float) $r['amount_vat'];
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
    /**
     * Default unpaid break for a time span: the part of it that falls inside the user's lunch window.
     */
    public static function defaultBreak(?array $user, ?int $startMin, ?int $endMin): int
    {
        if (!$user || empty($user['lunch_start']) || empty($user['lunch_end']) || $startMin === null || $endMin === null) {
            return 0;
        }
        return interval_overlap($startMin, $endMin, time_to_minutes($user['lunch_start']), time_to_minutes($user['lunch_end']));
    }

    public static function validate(int $uid, array $in, ?array $existing = null, ?array $user = null): array
    {
        $errors = [];

        $date = valid_date(to_str($in['date'] ?? ''));
        if (!$date) {
            $errors[] = t('Date is not valid.');
        }

        $start = parse_time_minutes(to_str($in['start'] ?? ''));
        $end = parse_time_minutes(to_str($in['end'] ?? ''));
        if ($start === null || $start >= 1440) {
            $errors[] = t('Start time is not valid (use HH:MM).');
        }
        if ($end === null) {
            $errors[] = t('End time is not valid (use HH:MM, 24:00 means midnight).');
        }
        if ($start !== null && $end !== null && $end <= $start) {
            $errors[] = t('End time must be after the start time.');
        }

        // Break: explicit value wins; if not sent, keep the stored one (edit) or derive it from the lunch window (new).
        if (!array_key_exists('break', $in) || $in['break'] === null) {
            $break = $existing ? (int) $existing['break_minutes'] : self::defaultBreak($user, $start, $end);
        } elseif (preg_match('/^\d{1,4}$/', trim((string) $in['break']))) {
            $break = (int) trim((string) $in['break']);
        } elseif (trim((string) $in['break']) === '') {
            $break = 0;
        } else {
            $break = 0;
            $errors[] = t('Break must be a whole number of minutes.');
        }
        if ($start !== null && $end !== null && $end > $start && $break >= $end - $start) {
            $errors[] = t('The break must be shorter than the time span.');
        }

        $clientId = (int) ($in['client_id'] ?? 0);
        $client = $clientId ? Clients::find($uid, $clientId) : null;
        if (!$client) {
            $errors[] = t('Choose a client.');
        } elseif ($client['is_archived'] && (!$existing || (int) $existing['client_id'] !== $clientId)) {
            $errors[] = t('That client is archived.');
        }

        $actionId = (int) ($in['action_id'] ?? 0);
        $action = $actionId ? Actions::find($uid, $actionId) : null;
        if (!$action) {
            $errors[] = t('Choose an action.');
        } elseif ($client && (int) $action['client_id'] !== $clientId) {
            $errors[] = t('That action does not belong to the selected client.');
        } elseif ($action['is_archived'] && (!$existing || (int) $existing['action_id'] !== $actionId)) {
            $errors[] = t('That action is archived.');
        }

        $desc = trim(to_str($in['description'] ?? ''));
        if (mb_strlen($desc) > 5000) {
            $errors[] = t('Description is too long (max 5000 characters).');
        }

        $data = [
            'client_id'   => $clientId,
            'action_id'   => $actionId,
            'entry_date'  => $date?->format('Y-m-d'),
            'start_time'  => $start !== null ? minutes_to_hhmm($start) . ':00' : null,
            'end_time'    => $end !== null ? minutes_to_hhmm($end) . ':00' : null,
            'break_minutes' => $break,
            'description' => $desc !== '' ? $desc : null,
        ];
        return [$data, $errors];
    }

    public static function create(int $uid, array $d): int
    {
        return Db::insert(
            'INSERT INTO time_entries (user_id, client_id, action_id, entry_date, start_time, end_time, break_minutes, description) VALUES (?,?,?,?,?,?,?,?)',
            [$uid, $d['client_id'], $d['action_id'], $d['entry_date'], $d['start_time'], $d['end_time'], $d['break_minutes'], $d['description']]
        );
    }

    public static function update(int $uid, int $id, array $d): void
    {
        Db::run(
            'UPDATE time_entries SET client_id=?, action_id=?, entry_date=?, start_time=?, end_time=?, break_minutes=?, description=? WHERE id=? AND user_id=?',
            [$d['client_id'], $d['action_id'], $d['entry_date'], $d['start_time'], $d['end_time'], $d['break_minutes'], $d['description'], $id, $uid]
        );
    }

    public static function delete(int $uid, int $id): void
    {
        Db::run('DELETE FROM time_entries WHERE id = ? AND user_id = ?', [$id, $uid]);
    }
}
