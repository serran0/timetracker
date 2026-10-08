<?php
declare(strict_types=1);

/**
 * Dependency-free tests for the pure logic (no database needed).
 * Run with:  php tests/run.php
 */

define('TT_ROOT', dirname(__DIR__));
const TT_VERSION = 'test';
require TT_ROOT . '/src/helpers.php';
spl_autoload_register(static function (string $c): void {
    $f = TT_ROOT . '/src/' . str_replace('\\', '/', substr($c, strlen('TimeTracker\\'))) . '.php';
    if (str_starts_with($c, 'TimeTracker\\') && is_file($f)) {
        require $f;
    }
});

use TimeTracker\Calendar;
use TimeTracker\Export\ReportBuilder;
use TimeTracker\Export\TextExporter;
use TimeTracker\Repository\Entries;
use TimeTracker\Repository\WorkingHours;

$failures = 0;
$total = 0;
function check(string $name, mixed $actual, mixed $expected): void
{
    global $failures, $total;
    $total++;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL  $name\n      expected: " . var_export($expected, true) . "\n      actual:   " . var_export($actual, true) . "\n";
    }
}

// Time parsing
foreach ([['9', 540], ['09:30', 570], ['0930', 570], ['930', 570], ['9.30', 570], ['24', 1440], ['24:00', 1440], ['0', 0], ['00:00', 0],
          ['24:30', null], ['25', null], ['12:60', null], ['abc', null], ['', null], ['1:2', null]] as [$in, $out]) {
    check("parse_time_minutes('$in')", parse_time_minutes($in), $out);
}
check('minutes_to_hhmm', minutes_to_hhmm(1440), '24:00');
check('time_to_minutes 24:00:00', time_to_minutes('24:00:00'), 1440);
check('fmt_dur', fmt_dur(450), '7:30');
check('fmt_dur long', fmt_dur(9930), '165:30');
check('fmt_dec', fmt_dec(450), '7.50');
check('valid_date ok', valid_date('2026-02-28')?->format('Y-m-d'), '2026-02-28');
check('valid_date bad', valid_date('2026-02-30'), null);
check('valid_color', valid_color('#ABCDEF'), '#abcdef');
check('valid_color bad', valid_color('red'), '#4f46e5');

// Lanes: overlapping entries must not share a lane
[$lanes, $n] = Calendar::assignLanes([
    ['start_min' => 480, 'end_min' => 720],
    ['start_min' => 540, 'end_min' => 600],
    ['start_min' => 600, 'end_min' => 660],
    ['start_min' => 720, 'end_min' => 780],
]);
check('lane count', $n, 2);
check('lane assignment', array_column($lanes, 'lane'), [0, 1, 1, 0]);

// Ranges & ISO weeks
$r = Calendar::range('month', ['date' => '2026-10-08']);
check('month grid starts on Monday', $r['from']->format('Y-m-d l'), '2026-09-28 Monday');
check('month grid ends on Sunday', $r['to']->format('Y-m-d l'), '2026-11-01 Sunday');
$r = Calendar::range('week', ['date' => '2026-10-11']); // a Sunday belongs to the week that started Monday
check('week from Sunday', $r['from']->format('Y-m-d'), '2026-10-05');
check('week title', $r['title'], 'Week 41');
$r = Calendar::range('week', ['date' => '2026-12-31']);
check('ISO week at year end', $r['title'], 'Week 53');
$r = Calendar::range('list', ['from' => '2026-10-31', 'to' => '2026-10-01']);
check('list swaps reversed range', $r['from']->format('Y-m-d') . '..' . $r['to']->format('Y-m-d'), '2026-10-01..2026-10-31');
check('list prev month', $r['prev'], ['from' => '2026-09-01', 'to' => '2026-09-30']);

// Filters
$f = Calendar::filters(['client' => ['3', 'x', '3', '0', '7'], 'action' => 'nope', 'billable' => '1', 'color' => 'action']);
check('filters clients', $f['clients'], [3, 7]);
check('filters actions (non-array ignored)', $f['actions'], []);
$byName = Calendar::filters(['action' => ['Overtime', ' Overtime ', '', 'Emergency / call-out', str_repeat('x', 200)]]);
check('filters actions by name', $byName['actions'], ['Overtime', 'Emergency / call-out']);
check('filters billable/color', [$f['billable'], $f['color']], ['1', 'action']);

// Working hours parsing
[$wh, $err] = WorkingHours::parse([1 => [['start' => '8', 'end' => '12'], ['start' => '13:00', 'end' => '17:30'], ['start' => '', 'end' => '']]]);
check('wh no errors', $err, []);
check('wh intervals', $wh[1], [[480, 720], [780, 1050]]);
check('wh weekly minutes', WorkingHours::weeklyMinutes($wh), 510);
[, $err] = WorkingHours::parse([2 => [['start' => '08:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '14:00']]]);
check('wh overlap detected', count($err), 1);
[, $err] = WorkingHours::parse([3 => [['start' => '12:00', 'end' => '08:00']]]);
check('wh end before start', count($err), 1);

// Unpaid breaks
check('overlap partial', interval_overlap(480, 1020, 720, 780), 60);
check('overlap none', interval_overlap(480, 700, 720, 780), 0);
check('overlap clipped', interval_overlap(700, 750, 720, 780), 30);
$u = ['lunch_start' => '12:00:00', 'lunch_end' => '13:00:00'];
check('default break whole day', Entries::defaultBreak($u, 480, 1020), 60);
check('default break morning only', Entries::defaultBreak($u, 480, 720), 0);
check('default break half in window', Entries::defaultBreak($u, 690, 750), 30);
check('default break without window', Entries::defaultBreak(['lunch_start' => null, 'lunch_end' => null], 480, 1020), 0);
check('default break without user', Entries::defaultBreak(null, 480, 1020), 0);
$row = Entries::decorate(['entry_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_minutes' => 60,
    'hourly_rate' => '100.00', 'rate_multiplier' => '1.50', 'is_billable' => 1, 'description' => null]);
check('net minutes', $row['minutes'], 480);
check('gross minutes', $row['gross_minutes'], 540);
check('amount uses net hours', $row['amount'], 1200.0);
$s = Entries::summarize([$row + ['client_id' => 1, 'client_name' => 'A', 'client_color' => '#000', 'action_id' => 1, 'action_name' => 'N', 'action_color' => '#000']]);
check('summary uses net hours', $s['minutes'], 480);

// Export options
$o = ReportBuilder::options(['cols' => ['client', 'date', 'bogus'], 'format' => 'evil', 'delimiter' => ';', 'submitted' => '1']);
check('export cols canonical order', $o['cols'], ['date', 'client']);
check('export format fallback', $o['format'], 'csv');
check('export totals unchecked', $o['totals'], false);
check('export totals default on', ReportBuilder::options([])['totals'], true);
check('export duration decimal comma', ReportBuilder::formatDuration(450, ['duration' => 'decimal', 'decimal' => ',']), '7,50');
check('export duration h:mm', ReportBuilder::formatDuration(450, ['duration' => 'hm', 'decimal' => '.']), '7:30');

// Plain-text export: output follows the ticked columns
$mk = static fn(string $date, string $s, string $e, int $brk, string $client, string $action, string $desc, ?string $rate) => Entries::decorate([
    'id' => 1, 'entry_date' => $date, 'start_time' => $s . ':00', 'end_time' => $e . ':00', 'break_minutes' => $brk,
    'description' => $desc, 'client_id' => crc32($client), 'client_name' => $client, 'client_color' => '#000', 'hourly_rate' => $rate,
    'client_reference' => $client === 'Acme AB' ? 'PO-77' : null, 'action_id' => crc32($action), 'action_name' => $action, 'action_color' => '#000',
    'rate_multiplier' => '1.00', 'is_billable' => $action === 'Internal' ? 0 : 1,
]);
$ents = [
    $mk('2026-10-05', '08:00', '17:00', 60, 'Acme AB', 'Normal working time', 'Architecture review', '100.00'),
    $mk('2026-10-06', '09:00', '11:00', 0, 'Globex', 'Internal', 'Admin', null),
];
$meta = ['from' => '2026-10-01', 'to' => '2026-10-31', 'consultant' => 'Tester', 'currency' => 'SEK', 'filters' => ''];
$txt = static fn(array $cols, bool $totals = true) => TextExporter::render($ents, ['cols' => $cols, 'duration' => 'decimal', 'decimal' => '.', 'totals' => $totals] + ['format' => 'txt', 'delimiter' => ','], $meta);

$out = $txt(['date', 'hours', 'description']);
check('text: date heading', str_contains($out, '2026-10-05'), true);
check('text: hours shown', str_contains($out, '  8.00'), true);
check('text: description shown', str_contains($out, 'Architecture review'), true);
check('text: no client when unticked', str_contains($out, 'Acme AB') || str_contains($out, 'Globex'), false);
check('text: no times when unticked', str_contains($out, '08:00') || str_contains($out, '17:00'), false);
check('text: no action when unticked', str_contains($out, 'Normal working time'), false);
check('text: no client summary when client unticked', str_contains($out, 'SUMMARY BY CLIENT'), false);
check('text: no amount when unticked', str_contains($out, 'SEK'), false);
check('text: total hours still shown', str_contains($out, 'TOTAL HOURS    : 10:00'), true);

$out = $txt(['client', 'action']);
check('text: client/action only', str_contains($out, 'Acme AB / Normal working time'), true);
check('text: no dates without date columns', str_contains($out, '2026-10-05') || str_contains($out, 'Monday'), false);
check('text: no hours without hours column', str_contains($out, 'HOURS') || str_contains($out, 'Day total'), false);
check('text: no description when unticked', str_contains($out, 'Admin') || str_contains($out, 'Architecture'), false);
check('text: client summary has names only when no figures ticked', str_contains($out, 'SUMMARY BY CLIENT'), false);

$out = $txt(['weekday', 'week', 'start', 'end', 'reference', 'billable', 'rate', 'amount']);
check('text: weekday and week heading', str_contains($out, 'Monday (week 41)'), true);
check('text: time range', str_contains($out, '08:00-17:00'), true);
check('text: reference without client', str_contains($out, 'PO-77'), true);
check('text: billable flag', str_contains($out, '[non-billable]') && str_contains($out, '[billable]'), true);
check('text: rate', str_contains($out, '@ 100.00 SEK/h'), true);
check('text: amount', str_contains($out, '[800.00 SEK]'), true);
check('text: ISO date hidden when only weekday ticked', str_contains($out, '2026-10-05'), false);

$out = $txt(['date', 'hours', 'client'], false);
check('text: totals off hides totals and summaries', str_contains($out, 'TOTAL') || str_contains($out, 'SUMMARY') || str_contains($out, 'Day total'), false);
$out = $txt(['start'], true);
check('text: only start time', str_contains($out, 'from 08:00'), true);

echo $failures ? "\n$failures of $total checks FAILED\n" : "All $total checks passed\n";
exit($failures ? 1 : 0);
