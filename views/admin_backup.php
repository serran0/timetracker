<?php
/** @var array $tables */
/** @var string[] $errors */
/** @var bool $gzip */
/** @var array{upload: string, post: string} $limits */
$size = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 0) . ' kB';
?>
<div class="page-head"><div><h1><?= te('Backup & restore') ?></h1><p class="muted"><?= te('Download the whole database as an .sql file, or restore from one.') ?></p></div></div>
<div class="split">
    <section class="card">
        <h2><?= te('Download a backup') ?></h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="op" value="download">
            <fieldset>
                <legend><?= te('File format') ?></legend>
                <label class="check-label"><input type="radio" name="format" value="sql" <?= $gzip ? '' : 'checked' ?>> <?= te('SQL (.sql)') ?></label>
                <?php if ($gzip): ?><label class="check-label"><input type="radio" name="format" value="gz" checked> <?= te('Compressed SQL (.sql.gz, much smaller)') ?></label><?php endif; ?>
            </fieldset>
            <label class="check-label"><input type="checkbox" name="audit" value="1" checked> <?= te('Include the audit log') ?></label>
            <p class="muted setting-help"><?= te('The backup is a consistent snapshot of users, clients, actions, working hours, free days, time reports, settings and (optionally) the audit log. Sessions and sign-in attempts are not included.') ?></p>
            <div class="alert alert-info"><?= te('A backup contains password hashes, the mail password and all time reports. Store it as carefully as the database itself.') ?></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Download backup') ?></button></div>
        </form>
        <h3 class="section-gap"><?= te('What is in the database') ?></h3>
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
    </section>
    <section class="card">
        <h2><?= te('Restore from a backup') ?></h2>
        <div class="alert alert-error"><?= te('Restoring replaces ALL current data (users, clients, time reports, settings and the audit log) with the contents of the file. Download a backup first.') ?></div>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="op" value="restore">
            <label><?= te('Backup file') ?> <span class="muted">(.sql, .sql.gz)</span>
                <input type="file" name="dump" accept=".sql,.gz,.sql.gz,application/sql,application/gzip" required>
            </label>
            <p class="muted setting-help"><?= te('This server accepts uploads up to {upload} per file ({post} per request). Load bigger backups with the mysql command line client instead.', ['upload' => $limits['upload'], 'post' => $limits['post']]) ?></p>
            <label><?= te('Your password') ?>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="check-label"><input type="checkbox" name="confirm" value="1" required> <?= te('I understand that this replaces all current data.') ?></label>
            <p class="muted setting-help"><?= te('The file is checked completely before anything is changed. A safety copy of the current data is saved in the config folder when the server allows it. Backups from older versions are upgraded automatically. You are signed out afterwards.') ?></p>
            <div class="form-actions"><button class="btn btn-danger" type="submit"><?= te('Restore database') ?></button></div>
        </form>
    </section>
</div>
