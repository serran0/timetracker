<?php
declare(strict_types=1);

namespace TimeTracker\Export;

use DateTimeImmutable;
use TimeTracker\Calendar;
use TimeTracker\Repository\Entries;

/**
 * Human-readable report that can be pasted into an e-mail or opened in Notepad.
 */
final class TextExporter
{
    public static function render(array $entries, array $opts, array $meta): string
    {
        $cur = $meta['currency'];
        $dur = static fn(int|float $m): string => ReportBuilder::formatDuration($m, $opts);
        $dualDur = static fn(int|float $m): string => fmt_dur($m) . ' (' . fmt_dec($m) . ' h)';
        $L = [];

        $L[] = 'TIME REPORT';
        $L[] = str_repeat('=', 60);
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

        $showAmount = in_array('amount', $opts['cols'], true);
        foreach (Calendar::byDate($entries) as $date => $rows) {
            $d = new DateTimeImmutable($date);
            $L[] = $d->format('Y-m-d l') . '  (week ' . $d->format('W') . ')';
            $L[] = str_repeat('-', 60);
            $dayMin = 0;
            foreach ($rows as $r) {
                $dayMin += $r['minutes'];
                $line = sprintf('  %s-%s  %6s  %s / %s', $r['start'], $r['end'], $dur($r['minutes']), $r['client_name'], $r['action_name']);
                if ($showAmount && $r['amount'] !== null) {
                    $line .= '  [' . fmt_money($r['amount'], $cur) . ']';
                }
                $L[] = $line;
                if (in_array('description', $opts['cols'], true) && trim((string) $r['description']) !== '') {
                    foreach (preg_split('/\R/', wordwrap(trim((string) $r['description']), 66, "\n", true)) ?: [] as $dl) {
                        $L[] = '                    ' . $dl;
                    }
                }
            }
            $L[] = '  Day total: ' . $dualDur($dayMin);
            $L[] = '';
        }

        $sum = Entries::summarize($entries);
        $L[] = 'SUMMARY BY CLIENT';
        $L[] = str_repeat('=', 60);
        foreach ($sum['by_client'] as $c) {
            $L[] = sprintf('  %-30s %s%s', mb_strimwidth($c['name'], 0, 30, '…'), str_pad($dualDur($c['minutes']), 18),
                $c['amount'] > 0 ? '  ' . fmt_money($c['amount'], $cur) : '');
        }
        $L[] = '';
        $L[] = 'SUMMARY BY ACTION';
        $L[] = str_repeat('=', 60);
        foreach ($sum['by_action'] as $a) {
            $L[] = sprintf('  %-30s %s%s', mb_strimwidth($a['name'], 0, 30, '…'), str_pad($dualDur($a['minutes']), 18),
                $a['amount'] > 0 ? '  ' . fmt_money($a['amount'], $cur) : '');
        }
        $L[] = '';
        $L[] = str_repeat('=', 60);
        $L[] = 'TOTAL HOURS    : ' . $dualDur($sum['minutes']);
        $L[] = 'BILLABLE HOURS : ' . $dualDur($sum['billable_minutes']);
        if ($sum['amount'] > 0) {
            $L[] = 'TOTAL AMOUNT   : ' . fmt_money($sum['amount'], $cur);
        }
        return implode("\r\n", $L) . "\r\n";
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
