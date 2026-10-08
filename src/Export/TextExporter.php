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
        $mark = $opts['decimal'];
        $dualDur = static fn(int|float $m): string => fmt_dur($m) . ' (' . fmt_dec($m, 2, $mark) . ' h)';
        $money = static fn(float $v): string => fmt_money($v, $cur, $mark);
        // "net (gross)": the VAT-inclusive figure follows in parentheses when the VAT option is on and VAT applies.
        $amt = static function (float $net, float $gross) use ($opts, $money): string {
            return (!empty($opts['vat']) && abs($gross - $net) >= 0.005) ? $money($net) . ' (' . $money($gross) . ')' : $money($net);
        };
        $hasAmount = $has('amount');
        $rule = static fn(string $ch): string => str_repeat($ch, self::WIDTH);
        $L = [];

        $L[] = mb_strtoupper(t('Time report'));
        $L[] = $rule('=');
        $info = [
            [t('Consultant'), $meta['consultant']],
            [t('Period'), t('{from} to {to}', ['from' => $meta['from'], 'to' => $meta['to']])],
        ];
        if ($meta['filters'] !== '') {
            $info[] = [t('Filters'), $meta['filters']];
        }
        $info[] = [t('Generated'), date('Y-m-d H:i')];
        $labelW = max(array_map(static fn($i) => mb_strlen($i[0]), $info));
        foreach ($info as [$label, $value]) {
            $L[] = $label . str_repeat(' ', $labelW - mb_strlen($label)) . ' : ' . $value;
        }
        $L[] = '';

        if (!$entries) {
            $L[] = t('No time reports found for this selection.');
            return implode("\r\n", $L) . "\r\n";
        }

        $showHeadings = $has('date') || $has('weekday') || $has('week');
        $showTotals = !empty($opts['totals']);

        // One entry line from the ticked columns.
        $line = static function (array $r) use ($has, $dur, $money, $amt, $hasAmount): array {
            $parts = [];
            if ($has('start') && $has('end')) {
                $parts[] = $r['start'] . '-' . $r['end'];
            } elseif ($has('start')) {
                $parts[] = t('from {time}', ['time' => $r['start']]);
            } elseif ($has('end')) {
                $parts[] = t('until {time}', ['time' => $r['end']]);
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
                $who = $who !== '' ? $who . ' / ' . $r['action_label'] : $r['action_label'];
            }
            if ($who !== '') {
                $parts[] = $who;
            }
            if ($has('billable')) {
                $parts[] = $r['billable'] ? '[' . t('billable') . ']' : '[' . t('non-billable') . ']';
            }
            if ($has('rate') && $r['effective_rate'] !== null) {
                $parts[] = '@ ' . $money($r['effective_rate']) . '/h';
            }
            if ($hasAmount && $r['amount'] !== null) {
                $parts[] = '[' . $amt($r['amount'], $r['amount_vat']) . ']';
            }
            return $parts;
        };

        $indent = '  ';
        foreach (Calendar::byDate($entries) as $date => $rows) {
            $d = new DateTimeImmutable($date);
            if ($showHeadings) {
                $heading = array_filter([
                    $has('date') ? $d->format('Y-m-d') : '',
                    $has('weekday') ? date_l10n($d, 'l') : '',
                    $has('week') ? '(' . t('week {n}', ['n' => $d->format('W')]) . ')' : '',
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
                $L[] = $indent . t('Day total: {hours}', ['hours' => $dualDur($dayMin)]);
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
            $showAmount = $hasAmount && $sum['amount'] > 0;

            // Per-client / per-action summaries, only when that column is ticked and there is a figure to show.
            $groups = [];
            if ($has('client')) {
                $groups[mb_strtoupper(t('Summary by client'))] = $sum['by_client'];
            }
            if ($has('action')) {
                $groups[mb_strtoupper(t('Summary by action'))] = $sum['by_action'];
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
                            $cells[] = $amt($it['amount'], $it['amount_vat']);
                        }
                        $L[] = sprintf('  %-30s %s', mb_strimwidth($it['label'] ?? $it['name'], 0, 30, '…'), rtrim(implode('  ', $cells)));
                    }
                    $L[] = '';
                }
            }

            $totals = [];
            if ($showHours) {
                $totals[] = [mb_strtoupper(t('Total hours')), $dualDur($sum['minutes'])];
                $totals[] = [mb_strtoupper(t('Billable hours')), $dualDur($sum['billable_minutes'])];
            }
            if ($showAmount) {
                $totals[] = [mb_strtoupper(t('Total amount')), $amt($sum['amount'], $sum['amount_vat'])];
            }
            if ($totals) {
                $L[] = $rule('=');
                $w = max(array_map(static fn($x) => mb_strlen($x[0]), $totals));
                foreach ($totals as [$label, $value]) {
                    $L[] = $label . str_repeat(' ', $w - mb_strlen($label)) . ' : ' . $value;
                }
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
