<?php
/** @var array $components */
/** @var array $php */
/** @var array $database */
/** @var array $structure */
/** @var array $tables */
/** @var ?array $privileges */
$icons = ['ok' => '✓', 'warn' => '!', 'fail' => '✕'];
$render = static function (array $list) use ($icons): void {
    echo '<ul class="checks">';
    foreach ($list as $c) {
        echo '<li class="check check-' . e($c['status']) . '"><span class="check-icon">' . $icons[$c['status']] . '</span><span><strong>'
            . e($c['label']) . '</strong>' . ($c['detail'] !== '' ? '<br><small>' . e($c['detail']) . '</small>' : '') . '</span></li>';
    }
    echo '</ul>';
};
$summary = static function (array ...$lists): array {
    $n = ['ok' => 0, 'warn' => 0, 'fail' => 0];
    foreach ($lists as $l) {
        foreach ($l as $c) {
            $n[$c['status']]++;
        }
    }
    return $n;
};
$all = $summary($php, $database, $structure);
$size = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 0) . ' kB';
?>
<div class="page-head"><div><h1><?= te('System') ?></h1><p class="muted"><?= te('Versions of all components and health checks for PHP, the database and the table structure.') ?></p></div></div>

<div class="alert <?= $all['fail'] ? 'alert-error' : ($all['warn'] ? 'alert-info' : 'alert-success') ?>">
    <?= te('{ok} checks passed, {warn} warnings, {fail} failures.', ['ok' => $all['ok'], 'warn' => $all['warn'], 'fail' => $all['fail']]) ?>
</div>

<div class="split">
    <div>
        <section class="card">
            <h2><?= te('Component versions') ?></h2>
            <div class="table-wrap">
            <table class="table table-compact">
                <tbody>
                <?php foreach ($components as [$name, $version, $note]): ?>
                    <tr><td><?= e($name) ?></td><td><strong><?= e($version) ?></strong><?= $note !== '' ? ' <small class="muted">' . e($note) . '</small>' : '' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
        <section class="card">
            <h2><?= te('Database structure') ?></h2>
            <p class="muted"><?= te('Compared with database/schema.sql: tables, columns and their types, indexes and foreign keys.') ?></p>
            <?php $render($structure); ?>
            <?php if ($tables): ?>
            <h3><?= te('Table sizes') ?></h3>
            <div class="table-wrap">
            <table class="table table-compact">
                <thead><tr><th><?= te('Table') ?></th><th class="num"><?= te('Rows (approx.)') ?></th><th class="num"><?= te('Size') ?></th></tr></thead>
                <tbody>
                <?php foreach ($tables as $name => $s): ?>
                    <tr><td><?= e($name) ?></td><td class="num"><?= number_format($s['rows'], 0, '', ' ') ?></td><td class="num"><?= e($size($s['bytes'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
    <div>
        <section class="card">
            <h2><?= te('PHP and files') ?></h2>
            <?php $render($php); ?>
        </section>
        <section class="card">
            <h2><?= te('Database') ?></h2>
            <?php $render($database); ?>
            <form method="post" class="card-sub">
                <?= csrf_field() ?><input type="hidden" name="op" value="privileges">
                <button class="btn btn-sm"><?= te('Test database privileges') ?></button>
                <span class="muted"><?= te('Creates and removes a scratch table to check CREATE, INSERT, SELECT, UPDATE, ALTER, INDEX, DELETE and DROP.') ?></span>
            </form>
            <?php if ($privileges !== null): ?>
                <?php $render(array_map(static fn($p, $err) => ['label' => t('Privilege: {name}', ['name' => $p]), 'status' => $err === null ? 'ok' : 'fail', 'detail' => (string) $err], array_keys($privileges), array_values($privileges))); ?>
            <?php endif; ?>
        </section>
    </div>
</div>
