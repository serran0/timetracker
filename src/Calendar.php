<?php
declare(strict_types=1);

namespace TimeTracker;

use DateTimeImmutable;

/**
 * Date-range, filter and layout helpers shared by the calendar views.
 */
final class Calendar
{
    public const VIEWS = ['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'list' => 'List'];
    private const MAX_LIST_DAYS = 3660;

    /** Parses the shared filter query params (clients[], actions[], billable, color). */
    public static function filters(array $q): array
    {
        $ids = static fn($v): array => array_values(array_unique(array_filter(
            array_map('intval', is_array($v) ? $v : []),
            static fn(int $i): bool => $i > 0
        )));
        return [
            'clients'  => $ids($q['client'] ?? []),
            'actions'  => $ids($q['action'] ?? []),
            'billable' => in_array($q['billable'] ?? '', ['0', '1'], true) ? $q['billable'] : '',
            'color'    => ($q['color'] ?? '') === 'action' ? 'action' : 'client',
        ];
    }

    /** Filter state as URL params (so links and forms keep the filters). */
    public static function filterParams(array $f): array
    {
        return [
            'client'   => $f['clients'] ?: null,
            'action'   => $f['actions'] ?: null,
            'billable' => $f['billable'] !== '' ? $f['billable'] : null,
            'color'    => $f['color'] === 'action' ? 'action' : null,
        ];
    }

    /**
     * Visible date range, title and prev/next dates for a view.
     * @return array{from: DateTimeImmutable, to: DateTimeImmutable, date: DateTimeImmutable, title: string, subtitle: string, prev: array, next: array}
     */
    public static function range(string $view, array $q): array
    {
        $today = new DateTimeImmutable('today');
        $date = valid_date((string) ($q['date'] ?? '')) ?? $today;

        switch ($view) {
            case 'month':
                $first = $date->modify('first day of this month');
                $last = $date->modify('last day of this month');
                return [
                    'from'     => $first->modify('monday this week'),
                    'to'       => $last->modify('sunday this week'),
                    'date'     => $date,
                    'month'    => $first,
                    'title'    => $first->format('F Y'),
                    'subtitle' => '',
                    'prev'     => ['date' => $first->modify('-1 month')->format('Y-m-d')],
                    'next'     => ['date' => $first->modify('+1 month')->format('Y-m-d')],
                ];
            case 'week':
                $mon = $date->modify('monday this week');
                $sun = $mon->modify('+6 days');
                return [
                    'from'     => $mon,
                    'to'       => $sun,
                    'date'     => $date,
                    'title'    => 'Week ' . $mon->format('W'),
                    'subtitle' => $mon->format('j M') . ' – ' . $sun->format('j M Y'),
                    'prev'     => ['date' => $mon->modify('-7 days')->format('Y-m-d')],
                    'next'     => ['date' => $mon->modify('+7 days')->format('Y-m-d')],
                ];
            case 'day':
                return [
                    'from'     => $date,
                    'to'       => $date,
                    'date'     => $date,
                    'title'    => $date->format('l j F Y'),
                    'subtitle' => 'Week ' . $date->format('W'),
                    'prev'     => ['date' => $date->modify('-1 day')->format('Y-m-d')],
                    'next'     => ['date' => $date->modify('+1 day')->format('Y-m-d')],
                ];
            default: // list
                $from = valid_date((string) ($q['from'] ?? '')) ?? $today->modify('first day of this month');
                $to = valid_date((string) ($q['to'] ?? '')) ?? $from->modify('last day of this month');
                if ($to < $from) {
                    [$from, $to] = [$to, $from];
                }
                if ($from->diff($to)->days > self::MAX_LIST_DAYS) {
                    $to = $from->modify('+' . self::MAX_LIST_DAYS . ' days');
                }
                $wholeMonth = $from->format('j') === '1' && $to->format('Y-m-d') === $from->modify('last day of this month')->format('Y-m-d');
                if ($wholeMonth) {
                    $prev = [$from->modify('-1 month'), $from->modify('-1 month')->modify('last day of this month')];
                    $next = [$from->modify('+1 month'), $from->modify('+1 month')->modify('last day of this month')];
                    $title = $from->format('F Y');
                } else {
                    $span = $from->diff($to)->days + 1;
                    $prev = [$from->modify("-$span days"), $to->modify("-$span days")];
                    $next = [$from->modify("+$span days"), $to->modify("+$span days")];
                    $title = $from->format('j M Y') . ' – ' . $to->format('j M Y');
                }
                return [
                    'from'     => $from,
                    'to'       => $to,
                    'date'     => $from,
                    'title'    => $title,
                    'subtitle' => '',
                    'prev'     => ['from' => $prev[0]->format('Y-m-d'), 'to' => $prev[1]->format('Y-m-d')],
                    'next'     => ['from' => $next[0]->format('Y-m-d'), 'to' => $next[1]->format('Y-m-d')],
                ];
        }
    }

    /** Quick period presets for the list view and export page. */
    public static function presets(): array
    {
        $t = new DateTimeImmutable('today');
        $fmt = static fn(DateTimeImmutable $d): string => $d->format('Y-m-d');
        $thisMonth = $t->modify('first day of this month');
        $lastMonth = $thisMonth->modify('-1 month');
        $quarterStart = $t->setDate((int) $t->format('Y'), (int) (floor(((int) $t->format('n') - 1) / 3) * 3 + 1), 1);
        return [
            'Today'        => [$fmt($t), $fmt($t)],
            'This week'    => [$fmt($t->modify('monday this week')), $fmt($t->modify('sunday this week'))],
            'Last week'    => [$fmt($t->modify('monday last week')), $fmt($t->modify('sunday last week'))],
            'This month'   => [$fmt($thisMonth), $fmt($t->modify('last day of this month'))],
            'Last month'   => [$fmt($lastMonth), $fmt($lastMonth->modify('last day of this month'))],
            'This quarter' => [$fmt($quarterStart), $fmt($quarterStart->modify('+2 months')->modify('last day of this month'))],
            'This year'    => [$t->format('Y') . '-01-01', $t->format('Y') . '-12-31'],
            'Last year'    => [((int) $t->format('Y') - 1) . '-01-01', ((int) $t->format('Y') - 1) . '-12-31'],
        ];
    }

    /**
     * Packs overlapping entries of one day into lanes so they can be drawn on separate rows.
     * @return array{0: array, 1: int} entries with a 'lane' key, and the number of lanes
     */
    public static function assignLanes(array $entries): array
    {
        usort($entries, static fn($a, $b) => [$a['start_min'], $a['end_min']] <=> [$b['start_min'], $b['end_min']]);
        $laneEnds = [];
        foreach ($entries as &$e) {
            $placed = false;
            foreach ($laneEnds as $i => $end) {
                if ($end <= $e['start_min']) {
                    $laneEnds[$i] = $e['end_min'];
                    $e['lane'] = $i;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $e['lane'] = count($laneEnds);
                $laneEnds[] = $e['end_min'];
            }
        }
        unset($e);
        return [$entries, max(1, count($laneEnds))];
    }

    /** Group decorated entries by date string. */
    public static function byDate(array $entries): array
    {
        $out = [];
        foreach ($entries as $e) {
            $out[$e['entry_date']][] = $e;
        }
        return $out;
    }

    /** JSON payload embedded in data attributes so the JS edit dialog needs no extra request. */
    public static function entryPayload(array $e): string
    {
        return json_encode([
            'id'          => (int) $e['id'],
            'date'        => $e['entry_date'],
            'start'       => $e['start'],
            'end'         => $e['end'],
            'break'       => (int) $e['break_minutes'],
            'client_id'   => (int) $e['client_id'],
            'action_id'   => (int) $e['action_id'],
            'description' => (string) ($e['description'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** CSS custom properties colouring an entry by client or action. */
    public static function entryStyle(array $e, string $colorBy): string
    {
        [$main, $accent] = $colorBy === 'action'
            ? [$e['action_color'], $e['client_color']]
            : [$e['client_color'], $e['action_color']];
        return '--c:' . $main . ';--a:' . $accent;
    }
}
