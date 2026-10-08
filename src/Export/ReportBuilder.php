<?php
declare(strict_types=1);

namespace TimeTracker\Export;

use DateTimeImmutable;
use TimeTracker\Repository\Entries;

/**
 * Turns time entries into a format-neutral table and parses export options.
 * Cell values are raw: dates 'Y-m-d', durations in minutes, money as float|null, everything else string|int.
 * The exporters decide how to present them.
 */
final class ReportBuilder
{
    /** key => [label, type] */
    public const COLUMNS = [
        'date'        => ['Date', 'date'],
        'weekday'     => ['Weekday', 'text'],
        'week'        => ['Week', 'int'],
        'start'       => ['Start', 'time'],
        'end'         => ['End', 'time'],
        'hours'       => ['Duration', 'dur'],
        'client'      => ['Client', 'text'],
        'reference'   => ['Client reference', 'text'],
        'action'      => ['Action', 'text'],
        'billable'    => ['Billable', 'text'],
        'rate'        => ['Rate', 'money'],
        'amount'      => ['Amount', 'money'],
        'description' => ['Description', 'text'],
    ];

    /** Extra column that follows "Amount" when the VAT option is on (not selectable on its own). */
    public const VAT_COLUMN = ['Amount incl. VAT', 'money'];

    /** Marker for the first cell of the totals row; exporters print the translated word. */
    public const TOTAL = "\0TOTAL";

    public const DEFAULT_COLUMNS = ['date', 'start', 'end', 'hours', 'client', 'action', 'description'];

    public const FORMATS = [
        'csv'  => 'CSV (.csv)',
        'xlsx' => 'Excel (.xlsx)',
        'txt'  => 'Plain text (.txt)',
    ];

    /** Parses and sanitises export options from a query array. */
    public static function options(array $q): array
    {
        $cols = array_values(array_filter(
            is_array($q['cols'] ?? null) ? $q['cols'] : self::DEFAULT_COLUMNS,
            static fn($c) => is_string($c) && isset(self::COLUMNS[$c])
        ));
        // Keep the canonical column order regardless of the order posted.
        $cols = array_values(array_intersect(array_keys(self::COLUMNS), $cols ?: self::DEFAULT_COLUMNS));

        $format = (string) ($q['format'] ?? 'csv');
        return [
            'format'    => isset(self::FORMATS[$format]) ? $format : 'csv',
            'cols'      => $cols,
            'duration'  => ($q['duration'] ?? '') === 'hm' ? 'hm' : 'decimal',
            'delimiter' => in_array($q['delimiter'] ?? '', [',', ';', 'tab'], true) ? $q['delimiter'] : ',',
            'decimal'   => ($q['decimal'] ?? '') === ',' ? ',' : '.',
            'totals'    => !array_key_exists('submitted', $q) || !empty($q['totals']),
            'vat'       => !array_key_exists('submitted', $q) || !empty($q['vat']), // also show amounts including VAT
        ];
    }

    /**
     * @return array{headers: string[], types: string[], keys: string[], rows: array<int, array>, totals: ?array}
     */
    public static function table(array $entries, array $opts): array
    {
        $keys = [];
        foreach ($opts['cols'] as $k) {
            $keys[] = $k;
            if ($k === 'amount' && !empty($opts['vat'])) {
                $keys[] = 'amount_vat';
            }
        }
        $def = static fn(string $k): array => $k === 'amount_vat' ? self::VAT_COLUMN : self::COLUMNS[$k];
        $headers = array_map(static fn($k) => t($def($k)[0]), $keys);
        $types = array_map(static fn($k) => $def($k)[1], $keys);

        $rows = [];
        foreach ($entries as $e) {
            $d = new DateTimeImmutable($e['entry_date']);
            $all = [
                'date'        => $e['entry_date'],
                'weekday'     => date_l10n($d, 'D'),
                'week'        => (int) $d->format('W'),
                'start'       => $e['start'],
                'end'         => $e['end'],
                'hours'       => $e['minutes'],
                'client'      => $e['client_name'],
                'reference'   => (string) ($e['client_reference'] ?? ''),
                'action'      => $e['action_label'],
                'billable'    => $e['billable'] ? t('Yes') : t('No'),
                'rate'        => $e['effective_rate'],
                'amount'      => $e['amount'],
                'amount_vat'  => $e['amount_vat'],
                'description' => (string) ($e['description'] ?? ''),
            ];
            $rows[] = array_map(static fn($k) => $all[$k], $keys);
        }

        $totals = null;
        if (!empty($opts['totals']) && $rows) {
            $sum = Entries::summarize($entries);
            $totals = [];
            foreach ($keys as $i => $k) {
                $totals[$i] = match ($k) {
                    'hours'  => $sum['minutes'],
                    'amount' => $sum['amount'] > 0 ? round($sum['amount'], 2) : null,
                    'amount_vat' => $sum['amount'] > 0 ? round($sum['amount_vat'], 2) : null,
                    default  => null,
                };
            }
            $first = array_key_first($totals);
            if ($totals[$first] === null) {
                $totals[$first] = self::TOTAL;
            }
        }
        return ['headers' => $headers, 'types' => $types, 'keys' => $keys, 'rows' => $rows, 'totals' => $totals];
    }

    public static function filename(array $meta, string $ext): string
    {
        return 'timereport_' . $meta['from'] . '_' . $meta['to'] . '.' . $ext;
    }

    /** Duration in minutes as "7:30" or "7.50" / "7,50". */
    public static function formatDuration(int|float $minutes, array $opts): string
    {
        if ($opts['duration'] === 'hm') {
            return fmt_dur($minutes);
        }
        return fmt_dec($minutes, 2, $opts['decimal']);
    }

    public static function formatMoney(?float $v, array $opts): string
    {
        return $v === null ? '' : str_replace('.', $opts['decimal'], number_format($v, 2, '.', ''));
    }
}
