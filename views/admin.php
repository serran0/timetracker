<?php
use TimeTracker\Audit;

/** @var array $user */
/** @var array{users: int, admins: int, disabled: int} $counts */
/** @var int $signins */
/** @var int $failed */
/** @var array $recent */
/** @var array $notices */
/** @var string $dbVersion */
?>
<div class="page-head"><div><h1><?= te('Admin overview') ?></h1><p class="muted"><?= te('Manage users, settings, backups and see what has happened. Time reports are never visible here.') ?></p></div></div>

<?php foreach ($notices as [$kind, $text]): ?><div class="alert alert-<?= $kind === 'warn' ? 'error' : 'info' ?>"><?= e($text) ?></div><?php endforeach; ?>

<div class="kpis">
    <a class="kpi kpi-link" href="admin_users.php?role=user"><span class="kpi-label"><?= te('Regular users') ?></span><span class="kpi-value"><?= (int) $counts['users'] ?></span><span class="kpi-sub"><?= te('{n} disabled', ['n' => $counts['disabled']]) ?></span></a>
    <a class="kpi kpi-link" href="admin_users.php?role=admin"><span class="kpi-label"><?= te('Administrators') ?></span><span class="kpi-value"><?= (int) $counts['admins'] ?></span><span class="kpi-sub">&nbsp;</span></a>
    <a class="kpi kpi-link" href="<?= e(url('admin_audit.php', ['action' => 'auth.login', 'from' => date('Y-m-d', strtotime('-1 day'))])) ?>"><span class="kpi-label"><?= te('Sign-ins, last 24 h') ?></span><span class="kpi-value"><?= $signins ?></span><span class="kpi-sub">&nbsp;</span></a>
    <a class="kpi kpi-link" href="<?= e(url('admin_audit.php', ['action' => 'cat:auth', 'from' => date('Y-m-d', strtotime('-1 day'))])) ?>"><span class="kpi-label"><?= te('Failed sign-ins, last 24 h') ?></span><span class="kpi-value"><?= $failed ?></span><span class="kpi-sub">&nbsp;</span></a>
</div>

<div class="split">
    <section class="card">
        <h2><?= te('Recent activity') ?></h2>
        <?php if (!$recent): ?>
            <div class="empty"><?= te('No log entries match.') ?></div>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table table-compact audit-table">
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="nowrap"><small><?= e($r['created_at']) ?></small></td>
                    <td class="nowrap"><strong><?= $r['username'] !== '' ? e($r['username']) : '–' ?></strong></td>
                    <td><?= e(Audit::describe($r)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p><a class="btn btn-sm" href="admin_audit.php"><?= te('Open the audit log') ?></a></p>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2><?= te('This installation') ?></h2>
        <table class="table table-compact"><tbody>
            <tr><td>Timetracker</td><td><strong><?= e(TT_VERSION) ?></strong></td></tr>
            <tr><td>PHP</td><td><strong><?= e(PHP_VERSION) ?></strong></td></tr>
            <tr><td><?= te('Database server') ?></td><td><strong><?= e($dbVersion) ?></strong></td></tr>
        </tbody></table>
        <p>
            <a class="btn btn-sm" href="admin_system.php"><?= te('System checks') ?></a>
            <a class="btn btn-sm" href="admin_backup.php"><?= te('Backup') ?></a>
            <a class="btn btn-sm" href="admin_settings.php"><?= te('Settings') ?></a>
        </p>
    </section>
</div>
