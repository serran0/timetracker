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
use TimeTracker\Holidays;
use TimeTracker\I18n;
use TimeTracker\Repository\FreeDays;
use TimeTracker\Repository\Actions;
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

// Remembered export options round-trip
$o1 = ReportBuilder::options(['submitted' => '1', 'format' => 'txt', 'cols' => ['hours', 'date'], 'duration' => 'hm', 'delimiter' => ';', 'decimal' => ',', 'vat' => '1']);
$o2 = ReportBuilder::options(ReportBuilder::restore(json_encode(ReportBuilder::storable($o1))));
check('export prefs round-trip', $o2, $o1);
check('export prefs: switched-off options stay off', [$o2['totals'], $o2['vat']], [false, true]);
check('export prefs: nothing saved', ReportBuilder::restore(null), null);
check('export prefs: garbage is ignored', ReportBuilder::options(ReportBuilder::restore('{"format":"evil","decimal":"x"}'))['format'], 'csv');

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

// i18n
I18n::setLocale('en');
check('en: text unchanged', t('Overtime'), 'Overtime');
check('en: placeholder', t('Week {n}', ['n' => 41]), 'Week 41');
check('en: action label', action_label('Overtime'), 'Overtime');
check('en: decimal mark', fmt_dec(450), '7.50');
check('en: month name', date_l10n(new DateTime('2026-10-05'), 'l j F Y'), 'Monday 5 October 2026');

I18n::setLocale('sv');
check('sv: translated', t('Calendar'), 'Kalender');
check('sv: placeholder kept', t('Week {n}', ['n' => 41]), 'Vecka 41');
check('sv: unknown text falls back to English', t('No such string here'), 'No such string here');
check('sv: standard action translated', action_label('Overtime'), 'Övertid');
check('sv: renamed action untouched', action_label('Overtime (weekend)'), 'Overtime (weekend)');
check('sv: custom action that equals a UI word is not translated', action_label('Calendar'), 'Calendar');
check('sv: all standard actions have a translation', array_filter(array_map(static fn($a) => action_label($a[0]) === $a[0] ? $a[0] : null, Actions::STANDARD)), []);
check('sv: date', date_l10n(new DateTime('2026-10-05'), 'l j F Y'), 'måndag 5 oktober 2026');
check('sv: short date', date_l10n(new DateTime('2026-03-02'), 'D j M'), 'mån 2 mar');
check('sv: heading capitalised', ucf(date_l10n(new DateTime('2026-10-05'), 'F Y')), 'Oktober 2026');
check('sv: decimal comma', fmt_dec(450), '7,50');
check('sv: explicit decimal mark wins', fmt_dec(450, 2, '.'), '7.50');
check('sv: money', fmt_money(22800.5, 'SEK'), '22 800,50 SEK');
check('sv: th keeps html vars and escapes text', th('To start reporting time, {link}.', ['link' => '<a>x</a>']), 'För att börja tidrapportera, <a>x</a>.');
$row = Entries::decorate(['entry_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'break_minutes' => 0, 'hourly_rate' => null,
    'rate_multiplier' => '1.00', 'is_billable' => 1, 'description' => null, 'action_name' => 'Normal working time']);
check('sv: decorate adds display label, keeps stored name', [$row['action_label'], $row['action_name']], ['Normal arbetstid', 'Normal working time']);
// editing: the translated label submitted unchanged keeps the stored name; any other text is a rename
check('nameToStore: unchanged label keeps stored name', Actions::nameToStore(['name' => 'Overtime'], 'Övertid'), 'Overtime');
check('nameToStore: rename wins', Actions::nameToStore(['name' => 'Overtime'], 'Helgjour'), 'Helgjour');
check('nameToStore: custom name unchanged', Actions::nameToStore(['name' => 'Helgjour'], 'Helgjour'), 'Helgjour');
check('nameToStore: new action', Actions::nameToStore(null, ' Övertid '), 'Övertid');
$tableRow = ReportBuilder::table([$row + ['client_name' => 'A', 'client_reference' => null, 'client_id' => 1, 'client_color' => '#000', 'action_id' => 1, 'action_color' => '#000', 'effective_rate' => null, 'amount' => null]],
    ['cols' => ['weekday', 'action', 'billable'], 'duration' => 'decimal', 'decimal' => ',', 'totals' => false]);
check('sv: export row translated', $tableRow['rows'][0], ['mån', 'Normal arbetstid', 'Ja']);
check('sv: export headers translated', $tableRow['headers'], ['Veckodag', 'Aktivitet', 'Debiterbar']);
check('sv: export duration follows the option, not the locale', ReportBuilder::formatDuration(450, ['duration' => 'decimal', 'decimal' => '.']), '7.50');
check('sv: working-hours day names', TimeTracker\Repository\WorkingHours::label(3), 'Onsdag');
check('sv: view labels', array_map('t', array_values(Calendar::VIEWS)), ['Månad', 'Vecka', 'Dag', 'Lista']);
I18n::setLocale('en');

// detection for visitors that are not signed in
unset($_COOKIE['tt_lang']);
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'sv-SE,sv;q=0.9,en;q=0.8';
check('detect: browser Swedish', I18n::detect(), 'sv');
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9,fr;q=0.8';
check('detect: unsupported falls back to English', I18n::detect(), 'en');
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
$_COOKIE['tt_lang'] = 'sv';
check('detect: cookie wins over browser', I18n::detect(), 'sv');
$_COOKIE['tt_lang'] = '<script>';
check('detect: invalid cookie ignored', I18n::detect(), 'en');
check('isValid', [I18n::isValid('sv'), I18n::isValid('xx'), I18n::isValid('')], [true, false, false]);

// Holidays (Swedish red days, calculated)
foreach ([2020 => '2020-04-12', 2023 => '2023-04-09', 2024 => '2024-03-31', 2025 => '2025-04-20', 2026 => '2026-04-05', 2027 => '2027-03-28', 2028 => '2028-04-16', 2030 => '2030-04-21', 2038 => '2038-04-25'] as $y => $easter) {
    check("easter $y", Holidays::easter($y)->format('Y-m-d'), $easter);
}
I18n::setLocale('en');
$h26 = Holidays::sweden(2026);
check('2026: 16 days', count($h26), 16);
check('2026: all keys are dates in 2026', count(array_filter(array_keys($h26), static fn($k) => str_starts_with($k, '2026-'))), 16);
check('2026: Good Friday', $h26['2026-04-03']['name'], 'Good Friday');
check('2026: Easter Monday', $h26['2026-04-06']['name'], 'Easter Monday');
check('2026: Ascension', $h26['2026-05-14']['name'], 'Ascension Day');
check('2026: Whit Sunday', $h26['2026-05-24']['name'], 'Whit Sunday');
check('2026: Midsummer Eve is a Friday 19-25 Jun', [$h26['2026-06-19']['name'], $h26['2026-06-19']['kind']], ['Midsummer Eve', 'eve']);
check('2026: Midsummer Day is a Saturday 20-26 Jun', $h26['2026-06-20']['name'], 'Midsummer Day');
check('2026: All Saints', $h26['2026-10-31']['name'], "All Saints' Day");
check('2026: Christmas Eve', [$h26['2026-12-24']['name'], $h26['2026-12-24']['kind']], ['Christmas Eve', 'eve']);
check('2026: New Years Eve', $h26['2026-12-31']['kind'], 'eve');
$h27 = Holidays::sweden(2027);
check('2027: Midsummer Eve moves to 25 Jun', $h27['2027-06-25']['name'], 'Midsummer Eve');
check('2027: Midsummer Day 26 Jun', $h27['2027-06-26']['name'], 'Midsummer Day');
check('2027: All Saints 6 Nov', $h27['2027-11-06']['name'], "All Saints' Day");
check('2028: Midsummer Eve 23 Jun (Fri)', Holidays::sweden(2028)['2028-06-23']['name'], 'Midsummer Eve');
foreach ([2024, 2025, 2026, 2027, 2028, 2029, 2030, 2031] as $y) {
    $hy = Holidays::sweden($y);
    $mid = array_keys(array_filter($hy, static fn($v) => $v['name'] === 'Midsummer Eve'))[0];
    $day = array_keys(array_filter($hy, static fn($v) => $v['name'] === 'Midsummer Day'))[0];
    $sat = array_keys(array_filter($hy, static fn($v) => $v['name'] === "All Saints' Day"))[0];
    check("$y: Midsummer Eve is Friday, in 19-25 Jun", [(new DateTime($mid))->format('N'), $mid >= "$y-06-19" && $mid <= "$y-06-25"], ['5', true]);
    check("$y: Midsummer Day is Saturday the day after", [(new DateTime($day))->format('N'), $day], ['6', (new DateTime($mid))->modify('+1 day')->format('Y-m-d')]);
    check("$y: All Saints' Day is a Saturday, 31 Oct-6 Nov", [(new DateTime($sat))->format('N'), $sat >= "$y-10-31" && $sat <= "$y-11-06"], ['6', true]);
}
I18n::setLocale('sv');
$sv = Holidays::sweden(2026);
check('sv: Julafton', $sv['2026-12-24']['name'], 'Julafton');
check('sv: Midsommarafton', $sv['2026-06-19']['name'], 'Midsommarafton');
check('sv: Nyårsafton', $sv['2026-12-31']['name'], 'Nyårsafton');
check('sv: Annandag jul', $sv['2026-12-26']['name'], 'Annandag jul');
check('sv: every name translated', array_values(array_filter(array_map(static fn($x) => $x['name'], $sv), static fn($n) => preg_match('/^[A-Z][a-z]+ [A-Za-z]+$/', $n) && in_array($n, ['New Year\'s Day', 'Good Friday', 'May Day', 'Easter Monday', 'Easter Sunday', 'Whit Sunday', 'Christmas Day', 'Boxing Day'], true))), []);
I18n::setLocale('en');
// range + on/off setting (user id 0 = no database access for own days)
$off = ['id' => 0, 'show_holidays' => 0];
$on = ['id' => 0, 'show_holidays' => 1];
check('overlay off: no public holidays', Holidays::forRange($off, '2026-12-01', '2026-12-31'), []);
check('overlay on: December 2026', array_keys(Holidays::forRange($on, '2026-12-01', '2026-12-31')), ['2026-12-24', '2026-12-25', '2026-12-26', '2026-12-31']);
check('range is inclusive', array_keys(Holidays::forRange($on, '2026-12-24', '2026-12-24')), ['2026-12-24']);
check('range across the year boundary', array_keys(Holidays::forRange($on, '2026-12-30', '2027-01-06')), ['2026-12-31', '2027-01-01', '2027-01-06']);
check('range without holidays', Holidays::forRange($on, '2026-02-01', '2026-02-28'), []);
// own work-free days: validation
// VAT per client
$vrow = Entries::decorate(['entry_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_minutes' => 60,
    'hourly_rate' => '100.00', 'vat_percent' => '25.00', 'rate_multiplier' => '1.00', 'is_billable' => 1, 'description' => null,
    'client_id' => 1, 'client_name' => 'A', 'client_color' => '#000', 'client_reference' => null, 'action_id' => 1, 'action_name' => 'N', 'action_color' => '#000']);
check('vat: amount incl. VAT', [$vrow['amount'], $vrow['amount_vat']], [800.0, 1000.0]);
check('vat: no VAT keeps the amount', $ents[0]['amount_vat'], $ents[0]['amount']);
check('vat: non-billable has no amount', Entries::decorate(['entry_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'break_minutes' => 0,
    'hourly_rate' => '100.00', 'vat_percent' => '25.00', 'rate_multiplier' => '1.00', 'is_billable' => 0, 'description' => null])['amount_vat'], null);
$vs = Entries::summarize([$vrow, $vrow]);
check('vat: summary totals', [$vs['amount'], $vs['amount_vat'], $vs['by_client'][1]['amount_vat']], [1600.0, 2000.0, 2000.0]);
check('vat: money with parentheses', fmt_money_vat(800.0, 1000.0, 'SEK', '.'), '800.00 SEK (1 000.00 SEK)');
check('vat: no parentheses without VAT', fmt_money_vat(800.0, 800.0, 'SEK', '.'), '800.00 SEK');
$vents = [$vrow, $mk('2026-10-06', '09:00', '11:00', 0, 'Globex', 'Normal working time', 'No VAT here', '50.00')];
$vtxt = static fn(array $cols, bool $vat) => TextExporter::render($vents, ['cols' => $cols, 'duration' => 'decimal', 'decimal' => '.', 'totals' => true, 'vat' => $vat, 'format' => 'txt', 'delimiter' => ','], $meta);
$o = $vtxt(['date', 'amount'], true);
check('vat: text line has both figures', str_contains($o, '[800.00 SEK (1 000.00 SEK)]'), true);
check('vat: text line without VAT stays plain', str_contains($o, '[100.00 SEK]'), true);
check('vat: text total has both figures', str_contains($o, '900.00 SEK (1 100.00 SEK)'), true);
$o = $vtxt(['date', 'amount'], false);
check('vat: option off shows no VAT figures', [str_contains($o, '1 000.00'), str_contains($o, '1 100.00'), str_contains($o, '[800.00 SEK]')], [false, false, true]);
check('vat: no amount column, no VAT figures', str_contains($vtxt(['date', 'hours'], true), '1 000.00'), false);
$vt = ReportBuilder::table($vents, ['cols' => ['amount'], 'totals' => true, 'vat' => true]);
check('vat: export table adds the VAT column after Amount', [$vt['keys'], $vt['rows'][0], $vt['totals']], [['amount', 'amount_vat'], [800.0, 1000.0], [900.0, 1100.0]]);
check('vat: export table without the option', ReportBuilder::table($vents, ['cols' => ['amount'], 'totals' => true, 'vat' => false])['keys'], ['amount']);
check('vat: option defaults on, can be switched off', [ReportBuilder::options([])['vat'], ReportBuilder::options(['submitted' => '1'])['vat'], ReportBuilder::options(['submitted' => '1', 'vat' => '1'])['vat']], [true, false, true]);

[$d, $e] = FreeDays::parse(['from' => '2026-07-06', 'to' => '2026-07-17', 'name' => ' Summer holiday ']);
check('free days: valid range', [$d['start_date'], $d['end_date'], $d['name'], $e], ['2026-07-06', '2026-07-17', 'Summer holiday', []]);
[$d, $e] = FreeDays::parse(['from' => '2026-05-15', 'to' => '', 'name' => '']);
check('free days: single day, name optional', [$d['start_date'], $d['end_date'], $d['name'], $e], ['2026-05-15', '2026-05-15', null, []]);
check('free days: missing start', count(FreeDays::parse(['from' => '', 'to' => ''])[1]), 1);
check('free days: end before start', count(FreeDays::parse(['from' => '2026-05-15', 'to' => '2026-05-14'])[1]), 1);
check('free days: invalid end', count(FreeDays::parse(['from' => '2026-05-15', 'to' => '2026-02-31'])[1]), 1);
check('free days: too long', count(FreeDays::parse(['from' => '2026-01-01', 'to' => '2028-01-01'])[1]), 1);
check('free days: label fallback', [FreeDays::label(null), FreeDays::label('  '), FreeDays::label('Semester')], ['Day off', 'Day off', 'Semester']);

echo $failures ? "\n$failures of $total checks FAILED\n" : "All $total checks passed\n";
exit($failures ? 1 : 0);
