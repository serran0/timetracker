<?php
declare(strict_types=1);

namespace TimeTracker\Export;

use DateTimeImmutable;
use TimeTracker\Calendar;
use TimeTracker\Repository\Entries;

/**
 * Human-readable report that can be pasted into an e-mail or opened in Notepad.
 *
 * Every line is built only from the columns ticked on the export page:
 *  - date / weekday / week     -> the day headings (none ticked = one flat list, no headings)
 *  - start / end / hours       -> time range and duration
 *  - client / reference / action / billable / rate / amount / description -> the rest of the line
 * "Add totals" switches the day totals, summaries and grand totals on or off; they only show
 * figures for columns that are ticked (hours, amount, client, action).
 */
final class TextExporter
{
    private const WIDTH = 60;

    public static function render(array $entries, array $opts, array $meta): string
    {
        $cols = array_flip($opts['cols']);
        $has = static fn(string $c): bool => isset($cols[$c]);
        $cur = $meta['currency'];
        $dur = static fn(int|float $m): string => ReportBuilder::formatDuration($m, $opts);
        $dualDur = static fn(int|float $m): string => fmt_dur($m) . ' (' . fmt_dec($m) . ' h)';
        $rule = static fn(string $ch): string => str_repeat($ch, self::WIDTH);
        $L = [];

        $L[] = 'TIME REPORT';
        $L[] = $rule('=');
        $L[] = 'Consultant : ' . $meta['consultant'];
        $L[] = 'Period     : ' . $meta['from'] . ' to ' . $meta['to'];
        if ($meta['filters'] !== '') {
            $L[] = 'Filters    : ' . $meta['filters'];
        }
        $L[] = 'Generated  : ' . date('Y-m-d H:i');
        $L[] = '';

        if (!$entries) {
            $L[] = 'No time reports found for this selection.';
            return implode("\r\n", $L) . "\r\n";
        }

        $showHeadings = $has('date') || $has('weekday') || $has('week');
        $showTotals = !empty($opts['totals']);

        // One entry line from the ticked columns.
        $line = static function (array $r) use ($has, $dur, $cur): array {
            $parts = [];
            if ($has('start') && $has('end')) {
                $parts[] = $r['start'] . '-' . $r['end'];
            } elseif ($has('start')) {
                $parts[] = 'from ' . $r['start'];
            } elseif ($has('end')) {
                $parts[] = 'until ' . $r['end'];
            }
            if ($has('hours')) {
                $parts[] = str_pad($dur($r['minutes']), 6, ' ', STR_PAD_LEFT);
            }

            $who = '';
            if ($has('client')) {
                $who = $r['client_name'];
            }
            if ($has('reference') && trim((string) $r['client_reference']) !== '') {
                $who = $who !== '' ? $who . ' (' . $r['client_reference'] . ')' : (string) $r['client_reference'];
            }
            if ($has('action')) {
                $who = $who !== '' ? $who . ' / ' . $r['action_name'] : $r['action_name'];
            }
            if ($who !== '') {
                $parts[] = $who;
            }
            if ($has('billable')) {
                $parts[] = $r['billable'] ? '[billable]' : '[non-billable]';
            }
            if ($has('rate') && $r['effective_rate'] !== null) {
                $parts[] = '@ ' . fmt_money($r['effective_rate'], $cur) . '/h';
            }
            if ($has('amount') && $r['amount'] !== null) {
                $parts[] = '[' . fmt_money($r['amount'], $cur) . ']';
            }
            return $parts;
        };

        $indent = '  ';
        foreach (Calendar::byDate($entries) as $date => $rows) {
            $d = new DateTimeImmutable($date);
            if ($showHeadings) {
                $heading = array_filter([
                    $has('date') ? $d->format('Y-m-d') : '',
                    $has('weekday') ? $d->format('l') : '',
                    $has('week') ? '(week ' . $d->format('W') . ')' : '',
                ]);
                $L[] = implode(' ', $heading);
                $L[] = $rule('-');
            }
            $dayMin = 0;
            foreach ($rows as $r) {
                $dayMin += $r['minutes'];
                $parts = $line($r);
                if ($parts) {
                    $L[] = $indent . implode('  ', $parts);
                }
                $desc = trim((string) $r['description']);
                if ($has('description') && $desc !== '') {
                    // Indented under the line (or flush with the list when the line itself is empty).
                    $pad = $parts ? $indent . '  ' : $indent;
                    foreach (preg_split('/\R/', wordwrap($desc, self::WIDTH - strlen($pad), "\n", true)) ?: [] as $dl) {
                        $L[] = $pad . $dl;
                    }
                }
            }
            if ($showHeadings && $showTotals && $has('hours')) {
                $L[] = $indent . 'Day total: ' . $dualDur($dayMin);
            }
            if ($showHeadings) {
                $L[] = '';
            }
        }
        if (!$showHeadings) {
            $L[] = '';
        }

        if ($showTotals) {
            $sum = Entries::summarize($entries);
            $showHours = $has('hours');
            $showAmount = $has('amount') && $sum['amount'] > 0;

            // Per-client / per-action summaries, only when that column is ticked and there is a figure to show.
            $groups = [];
            if ($has('client')) {
                $groups['SUMMARY BY CLIENT'] = $sum['by_client'];
            }
            if ($has('action')) {
                $groups['SUMMARY BY ACTION'] = $sum['by_action'];
            }
            if ($showHours || $showAmount) {
                foreach ($groups as $title => $items) {
                    $L[] = $title;
                    $L[] = $rule('=');
                    foreach ($items as $it) {
                        $cells = [];
                        if ($showHours) {
                            $cells[] = str_pad($dualDur($it['minutes']), 18);
                        }
                        if ($showAmount && $it['amount'] > 0) {
                            $cells[] = fmt_money($it['amount'], $cur);
                        }
                        $L[] = sprintf('  %-30s %s', mb_strimwidth($it['name'], 0, 30, '…'), rtrim(implode('  ', $cells)));
                    }
                    $L[] = '';
                }
            }

            $totals = [];
            if ($showHours) {
                $totals[] = 'TOTAL HOURS    : ' . $dualDur($sum['minutes']);
                $totals[] = 'BILLABLE HOURS : ' . $dualDur($sum['billable_minutes']);
            }
            if ($showAmount) {
                $totals[] = 'TOTAL AMOUNT   : ' . fmt_money($sum['amount'], $cur);
            }
            if ($totals) {
                $L[] = $rule('=');
                array_push($L, ...$totals);
            }
        }

        return rtrim(implode("\r\n", $L)) . "\r\n";
    }

    public static function send(array $entries, array $opts, array $meta): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . ReportBuilder::filename($meta, 'txt') . '"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF" . self::render($entries, $opts, $meta);
        exit;
    }
}
