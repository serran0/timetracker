<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\View;

$user = Auth::requireAdmin();

$date = static function (string $v): string {
    $d = valid_date($v);
    return $d ? $d->format('Y-m-d') : '';
};
$filters = [
    'from'   => $date((string) ($_GET['from'] ?? '')),
    'to'     => $date((string) ($_GET['to'] ?? '')),
    'action' => (string) ($_GET['action'] ?? ''),
    'user'   => mb_substr(trim((string) ($_GET['user'] ?? '')), 0, 64),
];
if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['to'] < $filters['from']) {
    [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
}
if ($filters['action'] !== '' && !isset(Audit::EVENTS[$filters['action']]) && !(str_starts_with($filters['action'], 'cat:') && isset(Audit::CATEGORIES[substr($filters['action'], 4)]))) {
    $filters['action'] = '';
}

// Rows per page: the choice is remembered for the session (default 50).
$perPage = (int) ($_GET['per_page'] ?? ($_SESSION['audit_per_page'] ?? Audit::PER_PAGE_CHOICES[0]));
$perPage = in_array($perPage, Audit::PER_PAGE_CHOICES, true) ? $perPage : Audit::PER_PAGE_CHOICES[0];
$_SESSION['audit_per_page'] = $perPage;

$result = Audit::page($filters, (int) ($_GET['page'] ?? 1), $perPage);

View::render('admin_audit', [
    'title'   => t('Audit log'),
    'active'  => 'admin_audit',
    'user'    => $user,
    'filters' => $filters,
    'result'  => $result,
    'tz'      => (string) TimeTracker\Config::get('app.timezone', 'UTC'),
]);
