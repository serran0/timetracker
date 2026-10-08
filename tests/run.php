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
$f = Calendar::filters(['action' => ['Overtime', ' Overtime ', '', 'Emergency / call-out', str_repeat('x', 200)]]);
check('filters actions by name', $f['actions'], ['Overtime', 'Emergency / call-out']);
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

echo $failures ? "\n$failures of $total checks FAILED\n" : "All $total checks passed\n";
exit($failures ? 1 : 0);
