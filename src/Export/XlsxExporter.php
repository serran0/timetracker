<?php
declare(strict_types=1);

namespace TimeTracker\Export;

use TimeTracker\Repository\Entries;

/**
 * Minimal dependency-free .xlsx writer (needs ext-zip). Strings are inline, so no formula injection is possible.
 */
final class XlsxExporter
{
    // Style ids defined in styles()
    private const S_DEFAULT = 0, S_DATE = 1, S_NUM = 2, S_HM = 3, S_HEAD = 4, S_BOLD = 5, S_BOLD_NUM = 6, S_BOLD_HM = 7;

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public static function send(array $entries, array $opts, array $meta): never
    {
        if (!self::available()) {
            http_response_code(500);
            exit('Excel export needs the PHP zip extension.');
        }
        $table = ReportBuilder::table($entries, $opts);
        $summary = Entries::summarize($entries);

        $sheet1 = self::sheet(
            $table['headers'],
            array_map(fn($r) => self::dataRow($r, $table['types'], $opts, false), $table['rows'])
                + ($table['totals'] ? [count($table['rows']) => self::dataRow($table['totals'], $table['types'], $opts, true)] : []),
            true,
            $table['keys']
        );

        $sumRows = [];
        $hmStyle = $opts['duration'] === 'hm';
        foreach (['by_client' => t('Client'), 'by_action' => t('Action')] as $key => $label) {
            $sumRows[] = [self::cell($label, self::S_HEAD), self::cell(t('Duration'), self::S_HEAD), self::cell(t('Amount'), self::S_HEAD)];
            foreach ($summary[$key] as $row) {
                $sumRows[] = [
                    self::cell($row['label'] ?? $row['name']),
                    $hmStyle ? self::num($row['minutes'] / 1440, self::S_HM) : self::num($row['minutes'] / 60, self::S_NUM),
                    $row['amount'] > 0 ? self::num($row['amount'], self::S_NUM) : self::cell(''),
                ];
            }
            $sumRows[] = [
                self::cell(t('Total'), self::S_BOLD),
                $hmStyle ? self::num($summary['minutes'] / 1440, self::S_BOLD_HM) : self::num($summary['minutes'] / 60, self::S_BOLD_NUM),
                $summary['amount'] > 0 ? self::num($summary['amount'], self::S_BOLD_NUM) : self::cell(''),
            ];
            $sumRows[] = [];
        }
        $sheet2 = self::sheet([], $sumRows, false, []);

        $tmp = tempnam(sys_get_temp_dir(), 'ttx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml(t('Time report')) . '" sheetId="1" r:id="rId1"/><sheet name="' . self::xml(t('Summary')) . '" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . ReportBuilder::filename($meta, 'xlsx') . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store');
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    /** @return string[] cell XML fragments */
    private static function dataRow(array $row, array $types, array $opts, bool $bold): array
    {
        $cells = [];
        foreach ($row as $i => $v) {
            $type = $types[$i];
            if ($v === null || $v === '') {
                $cells[] = self::cell('', $bold ? self::S_BOLD : self::S_DEFAULT);
            } elseif ($v === ReportBuilder::TOTAL) {
                $cells[] = self::cell(t('Total'), self::S_BOLD);
            } elseif ($type === 'date') {
                $serial = (int) (strtotime($v . ' UTC') / 86400) + 25569;
                $cells[] = self::num($serial, self::S_DATE);
            } elseif ($type === 'dur') {
                $cells[] = $opts['duration'] === 'hm'
                    ? self::num($v / 1440, $bold ? self::S_BOLD_HM : self::S_HM)
                    : self::num($v / 60, $bold ? self::S_BOLD_NUM : self::S_NUM);
            } elseif ($type === 'money') {
                $cells[] = self::num((float) $v, $bold ? self::S_BOLD_NUM : self::S_NUM);
            } elseif ($type === 'int') {
                $cells[] = self::num((int) $v, $bold ? self::S_BOLD : self::S_DEFAULT);
            } else {
                $cells[] = self::cell((string) $v, $bold ? self::S_BOLD : self::S_DEFAULT);
            }
        }
        return $cells;
    }

    private static function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function cell(string $text, int $style = self::S_DEFAULT): string
    {
        // Strip control characters that are illegal in XML 1.0.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
        return '<c t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }

    private static function num(int|float $n, int $style): string
    {
        return '<c s="' . $style . '"><v>' . rtrim(rtrim(number_format((float) $n, 10, '.', ''), '0'), '.') . '</v></c>';
    }

    /**
     * @param string[] $headers
     * @param array<int, string[]> $rows
     */
    private static function sheet(array $headers, array $rows, bool $freeze, array $keys): string
    {
        $all = [];
        if ($headers) {
            $all[] = array_map(fn($h) => self::cell($h, self::S_HEAD), $headers);
        }
        foreach ($rows as $r) {
            $all[] = $r;
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($freeze && $headers) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        }
        $widthCols = max(array_map('count', $all ?: [[]]));
        $xml .= '<cols>';
        for ($c = 0; $c < $widthCols; $c++) {
            $w = 14;
            if (($keys[$c] ?? '') === 'description') {
                $w = 60;
            } elseif (in_array($keys[$c] ?? '', ['client', 'action', 'reference'], true)) {
                $w = 24;
            } elseif (!$headers && $c === 0) {
                $w = 28;
            }
            $xml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($all as $ri => $cells) {
            $xml .= '<row r="' . ($ri + 1) . '">';
            foreach ($cells as $ci => $cellXml) {
                $xml .= str_replace('<c ', '<c r="' . self::col($ci) . ($ri + 1) . '" ', $cellXml);
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy\-mm\-dd"/><numFmt numFmtId="165" formatCode="[h]:mm"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EAF6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="2" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            . '<xf numFmtId="165" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            . '</cellXfs></styleSheet>';
    }
}
