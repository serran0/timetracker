<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Calendar;
use TimeTracker\Holidays;
use TimeTracker\Repository\Actions;
use TimeTracker\Repository\Clients;
use TimeTracker\Repository\Entries;
use TimeTracker\Repository\WorkingHours;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];

$view = to_str($_GET['view'] ?? $_SESSION['cal_view'] ?? 'month');
if (!isset(Calendar::VIEWS[$view])) {
    $view = 'month';
}
$_SESSION['cal_view'] = $view;

$range = Calendar::range($view, $_GET);
$filters = Calendar::filters($_GET, to_str($user['default_color'] ?? 'client'));
$from = $range['from']->format('Y-m-d');
$to = $range['to']->format('Y-m-d');

$entries = Entries::search($uid, $from, $to, $filters);

// Month view shows leading/trailing days of neighbouring months; the summary covers the month itself.
$summaryEntries = $entries;
if ($view === 'month') {
    $mFrom = $range['month']->format('Y-m-01');
    $mTo = $range['month']->format('Y-m-t');
    $summaryEntries = array_values(array_filter($entries, static fn($e) => $e['entry_date'] >= $mFrom && $e['entry_date'] <= $mTo));
}

$clients = Clients::selectable($uid);
$actions = Actions::selectable($uid);       // every action with its client (report dialog)
$actionNames = Actions::names($uid);        // distinct names (filter)

// Parameters that every link inside the calendar keeps (view + filters).
$baseParams = ['view' => $view] + Calendar::filterParams($filters);

View::render('calendar', [
    'title'      => t('Calendar'),
    'active'     => 'calendar',
    'user'       => $user,
    'scripts'    => ['calendar.js'],
    'view'       => $view,
    'range'      => $range,
    'filters'    => $filters,
    'entries'    => $entries,
    'byDate'     => Calendar::byDate($entries),
    'summary'    => Entries::summarize($summaryEntries),
    'summaryLabel' => $view === 'month' ? ucf(date_l10n($range['month'], 'F Y')) : $range['title'],
    'hours'      => WorkingHours::intervals($uid),
    'clients'    => $clients,
    'actions'    => $actions,
    'actionNames' => $actionNames,
    'holidays'   => Holidays::forRange($user, $from, $to), // red days + the user's own work-free days, by date
    'dayCounts'  => $view === 'month' ? Entries::dayCounts($uid, $from, $to) : [],
    'baseParams' => $baseParams,
    'presets'    => Calendar::presets(),
    'today'      => new DateTimeImmutable('today'),
]);
