<?php
/** @var array $values */
/** @var array $checks */
/** @var array $dbChecks */
/** @var array $errors */
/** @var bool $ran */
/** @var string[] $timezones */
$icons = ['ok' => '✓', 'warn' => '!', 'fail' => '✕'];
$renderChecks = static function (array $list) use ($icons): void {
    echo '<ul class="checks">';
    foreach ($list as $c) {
        echo '<li class="check check-' . e($c['status']) . '"><span class="check-icon">' . $icons[$c['status']] . '</span><span><strong>'
            . e($c['label']) . '</strong>' . ($c['detail'] !== '' ? '<br><small>' . e($c['detail']) . '</small>' : '') . '</span></li>';
    }
    echo '</ul>';
};
?>
<div class="auth-wrap wide">
    <div class="auth-head">
        <h1><?= te('Set up Timetracker') ?></h1>
        <p class="muted"><?= te('Enter your MySQL credentials and create the first administrator. Setup checks the server and database permissions, creates the tables and saves the configuration.') ?></p>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="card setup-form" autocomplete="off">
        <?= csrf_field() ?>

        <h2><?= te('1 · Database') ?></h2>
        <div class="grid-2">
            <label><?= te('Host') ?>
                <input type="text" name="db_host" value="<?= e($values['db_host']) ?>" required>
            </label>
            <label><?= te('Port') ?>
                <input type="number" name="db_port" value="<?= e($values['db_port']) ?>" min="1" max="65535" required>
            </label>
            <label><?= te('Database name') ?>
                <input type="text" name="db_name" value="<?= e($values['db_name']) ?>" required pattern="[A-Za-z0-9_$]{1,64}">
                <small class="muted"><?= te('Created if it does not exist and the user is allowed to.') ?></small>
            </label>
            <span></span>
            <label><?= te('Database user') ?>
                <input type="text" name="db_user" value="<?= e($values['db_user']) ?>" required autocomplete="off">
            </label>
            <label><?= te('Database password') ?>
                <input type="password" name="db_pass" value="<?= e($values['db_pass']) ?>" autocomplete="new-password">
            </label>
        </div>

        <h2><?= te('2 · Administrator account') ?></h2>
        <div class="grid-2">
            <label><?= te('Username') ?>
                <input type="text" name="username" value="<?= e($values['username']) ?>" autocomplete="off" pattern="[A-Za-z0-9._\-]{3,64}">
            </label>
            <label><?= te('Display name') ?> <span class="muted"><?= te('(optional)') ?></span>
                <input type="text" name="display_name" value="<?= e($values['display_name']) ?>" maxlength="120">
            </label>
            <label><?= te('Password') ?> <span class="muted"><?= te('(min. 10 characters)') ?></span>
                <input type="password" name="password" value="<?= e($values['password']) ?>" autocomplete="new-password">
            </label>
            <label><?= te('Repeat password') ?>
                <input type="password" name="password2" value="<?= e($values['password2']) ?>" autocomplete="new-password">
            </label>
            <label><?= te('Timezone') ?>
                <select name="timezone">
                    <?php foreach ($timezones as $tz): ?>
                        <option <?= $tz === $values['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= te('Currency') ?> <span class="muted"><?= te('(for hourly rates)') ?></span>
                <input type="text" name="currency" value="<?= e($values['currency']) ?>" maxlength="8" size="6">
            </label>
            <label><?= te('Language') ?>
                <select name="locale">
                    <?php foreach (TimeTracker\I18n::LOCALES as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $code === $values['locale'] ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit" name="mode" value="check"><?= te('Run checks') ?></button>
            <button class="btn btn-primary" type="submit" name="mode" value="install"><?= te('Check & install') ?></button>
        </div>
    </form>

    <div class="card">
        <h2><?= te('Server requirements') ?></h2>
        <?php $renderChecks($checks); ?>
        <?php if ($ran && $dbChecks): ?>
            <h2><?= te('Database & permissions') ?></h2>
            <?php $renderChecks($dbChecks); ?>
        <?php elseif ($ran): ?>
            <p class="muted"><?= te('Database checks were skipped because a server requirement failed.') ?></p>
        <?php endif; ?>
    </div>
</div>
