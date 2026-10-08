<?php
declare(strict_types=1);

namespace TimeTracker\Export;

final class CsvExporter
{
    public static function send(array $entries, array $opts, array $meta): never
    {
        $table = ReportBuilder::table($entries, $opts);
        $delimiter = $opts['delimiter'] === 'tab' ? "\t" : $opts['delimiter'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . ReportBuilder::filename($meta, 'csv') . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
        fputcsv($out, $table['headers'], $delimiter, '"', '');

        $format = static function (array $row) use ($table, $opts): array {
            $cells = [];
            foreach ($row as $i => $v) {
                $cells[] = match ($table['types'][$i]) {
                    'dur'   => $v === ReportBuilder::TOTAL ? '' : ($v === null ? '' : ReportBuilder::formatDuration((int) $v, $opts)),
                    'money' => $v === ReportBuilder::TOTAL ? '' : ($v === null ? '' : ReportBuilder::formatMoney((float) $v, $opts)),
                    default => $v === ReportBuilder::TOTAL ? t('Total') : self::safe((string) $v),
                };
            }
            return $cells;
        };
        foreach ($table['rows'] as $row) {
            fputcsv($out, $format($row), $delimiter, '"', '');
        }
        if ($table['totals'] !== null) {
            fputcsv($out, $format($table['totals']), $delimiter, '"', '');
        }
        fclose($out);
        exit;
    }

    /** Neutralise spreadsheet formula injection in free-text cells. */
    private static function safe(string $v): string
    {
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    }
}
