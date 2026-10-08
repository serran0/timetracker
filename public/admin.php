<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\Repository\Users;
use TimeTracker\Settings;
use TimeTracker\SystemInfo;
use TimeTracker\View;

$user = Auth::requireAdmin();

$since = (new DateTimeImmutable('-24 hours', new DateTimeZone((string) TimeTracker\Config::get('app.timezone', 'UTC'))))->format('Y-m-d H:i:s');
$count = static fn(string $action): int => (int) Db::value('SELECT COUNT(*) FROM audit_log WHERE action = ? AND created_at >= ?', [$action, $since]);
$counts = Users::counts();

$notices = [];
if ($counts['users'] === 0) {
    $notices[] = ['info', t('There are no regular users yet. Create the first one on the Users page.')];
}
if (Settings::get('mail.host') === '' || Settings::get('mail.enabled') !== '1') {
    $notices[] = ['info', t('Email notifications are not set up. You can configure the mail server under Settings.')];
}
if (PHP_VERSION_ID >= 80500) {
    $notices[] = ['warn', t('This server runs PHP {v}, which is newer than the newest version Timetracker supports (8.4).', ['v' => PHP_VERSION])];
}
if (SystemInfo::schemaVersion() !== (string) TT_SCHEMA) {
    $notices[] = ['warn', t('The database schema is not up to date. See the System page.')];
}

View::render('admin', [
    'title'   => t('Admin overview'),
    'active'  => 'admin',
    'user'    => $user,
    'counts'  => $counts,
    'signins' => $count('auth.login'),
    'failed'  => $count('auth.login_failed') + $count('auth.login_unknown'),
    'recent'  => Db::all('SELECT id, created_at, user_id, username, action, params FROM audit_log ORDER BY created_at DESC, id DESC LIMIT 10'),
    'notices' => $notices,
    'dbVersion' => (string) Db::value('SELECT VERSION()'),
]);
