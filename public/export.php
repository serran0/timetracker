<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Calendar;
use TimeTracker\Export\CsvExporter;
use TimeTracker\Export\ReportBuilder;
use TimeTracker\Export\TextExporter;
use TimeTracker\Export\XlsxExporter;
use TimeTracker\Repository\Actions;
use TimeTracker\Repository\Clients;
use TimeTracker\Repository\Entries;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];

// Default period: the current month.
$presets = Calendar::presets();
[$defFrom, $defTo] = $presets['This month'];
$from = valid_date((string) ($_GET['from'] ?? '')) ?? new DateTimeImmutable($defFrom);
$to = valid_date((string) ($_GET['to'] ?? '')) ?? new DateTimeImmutable($defTo);
if ($to < $from) {
    [$from, $to] = [$to, $from];
}

$filters = Calendar::filters($_GET);
$opts = ReportBuilder::options($_GET);
$clients = Clients::selectable($uid);
$actions = Actions::names($uid); // action filter is by name across clients

$entries = Entries::search($uid, $from->format('Y-m-d'), $to->format('Y-m-d'), $filters);

if (isset($_GET['download'])) {
    if ($opts['format'] === 'xlsx' && !XlsxExporter::available()) {
        flash('error', 'Excel export needs the PHP zip extension on this server. Use CSV or plain text instead.');
        redirect('export.php');
    }

    $names = static fn(array $rows, array $ids): string => implode(', ', array_map(
        static fn($r) => $r['name'],
        array_filter($rows, static fn($r) => in_array((int) $r['id'], $ids, true))
    ));
    $actionNamesText = implode(', ', $filters['actions']);
    $filterDesc = array_filter([
        $filters['clients'] ? 'clients: ' . $names($clients, $filters['clients']) : '',
        $filters['actions'] ? 'actions: ' . $actionNamesText : '',
        $filters['billable'] === '1' ? 'billable only' : ($filters['billable'] === '0' ? 'non-billable only' : ''),
    ]);
    $meta = [
        'from'       => $from->format('Y-m-d'),
        'to'         => $to->format('Y-m-d'),
        'consultant' => $user['display_name'] ?: $user['username'],
        'currency'   => $user['currency'],
        'filters'    => implode('; ', $filterDesc),
    ];
    match ($opts['format']) {
        'xlsx'  => XlsxExporter::send($entries, $opts, $meta),
        'txt'   => TextExporter::send($entries, $opts, $meta),
        default => CsvExporter::send($entries, $opts, $meta),
    };
}

$previewOpts = $opts + [];
$table = ReportBuilder::table(array_slice($entries, 0, 8), ['totals' => false] + $previewOpts);

View::render('export', [
    'title'     => 'Export',
    'active'    => 'export',
    'user'      => $user,
    'from'      => $from,
    'to'        => $to,
    'filters'   => $filters,
    'opts'      => $opts,
    'clients'   => $clients,
    'actions'   => $actions,
    'presets'   => $presets,
    'summary'   => Entries::summarize($entries),
    'table'     => $table,
    'rowCount'  => count($entries),
    'xlsxOk'    => XlsxExporter::available(),
]);
