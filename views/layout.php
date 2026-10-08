<?php
/** @var string $content */
/** @var string $title */
/** @var ?array $user */
/** @var array $flashes */
$active ??= '';
$bodyClass ??= '';
$scripts ??= [];
$isAdmin = !empty($user['is_admin']);
$forced = !empty($user['must_change_password']); // must choose a new password first: no navigation yet
if ($forced) {
    $nav = [];
} elseif ($isAdmin) {
    // Administrators only see the admin panel: no calendar, clients, actions or any other time reporting.
    $nav = [
        'admin'          => ['admin.php', t('Overview')],
        'admin_users'    => ['admin_users.php', t('Users')],
        'admin_audit'    => ['admin_audit.php', t('Audit log')],
        'admin_settings' => ['admin_settings.php', t('Settings')],
        'admin_system'   => ['admin_system.php', t('System')],
        'admin_backup'   => ['admin_backup.php', t('Backup')],
    ];
} else {
    $nav = [
        'calendar' => ['calendar.php', t('Calendar')],
        'clients'  => ['clients.php', t('Clients')],
        'actions'  => ['actions.php', t('Actions')],
        'hours'    => ['hours.php', t('Working hours')],
        'export'   => ['export.php', t('Export')],
    ];
}
$initial = $user ? mb_strtoupper(mb_substr($user['display_name'] ?: $user['username'], 0, 1)) : '';
?>
<!DOCTYPE html>
<html lang="<?= e(TimeTracker\I18n::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · Timetracker</title>
    <script type="application/json" id="tt-i18n"><?= json_encode(TimeTracker\I18n::forJs(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= e(TT_VERSION) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($user): ?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $forced ? 'password.php' : ($isAdmin ? 'admin.php' : 'calendar.php?view=month') ?>">
            <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#4f46e5"/><path d="M12 6v6l4 2.5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Timetracker</span><?php if ($isAdmin): ?><span class="role-badge"><?= te('Admin') ?></span><?php endif; ?>
        </a>
        <button class="nav-toggle" type="button" aria-label="<?= te('Menu') ?>" aria-expanded="false" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>
        <nav class="main-nav" data-nav>
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <details class="user-menu">
                <summary><span class="avatar"><?= e($initial) ?></span><span class="user-name"><?= e($user['display_name'] ?: $user['username']) ?></span></summary>
                <div class="menu">
                    <?php if (!$forced): ?><a href="<?= $isAdmin ? 'admin_account.php' : 'account.php' ?>"><?= te($isAdmin ? 'My account' : 'Account & settings') ?></a><?php endif; ?>
                    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit"><?= te('Sign out') ?></button></form>
                </div>
            </details>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="container">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="footer">Timetracker v<?= e(TT_VERSION) ?></footer>
<script src="assets/js/app.js?v=<?= e(TT_VERSION) ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="assets/js/<?= e($s) ?>?v=<?= e(TT_VERSION) ?>"></script>
<?php endforeach; ?>
</body>
</html>
