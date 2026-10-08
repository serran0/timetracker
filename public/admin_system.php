<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\Installer;
use TimeTracker\SystemInfo;
use TimeTracker\View;

$user = Auth::requireAdmin();

// The privilege test creates and drops a scratch table, so it only runs on request.
$privileges = null;
if (is_post()) {
    require_csrf();
    if (input('op') === 'privileges') {
        $privileges = Installer::probePrivileges(Db::pdo());
        Audit::log('system.privilege_test', [], $user);
    }
}

$structure = SystemInfo::structure();
View::render('admin_system', [
    'title'      => t('System'),
    'active'     => 'admin_system',
    'user'       => $user,
    'components' => SystemInfo::components(),
    'php'        => SystemInfo::phpChecks(),
    'database'   => SystemInfo::databaseChecks(),
    'structure'  => $structure['checks'],
    'tables'     => $structure['tables'],
    'privileges' => $privileges,
]);
