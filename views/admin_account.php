<?php
/** @var array $user */
/** @var string[] $errors */
/** @var string[] $pwErrors */
?>
<div class="page-head"><div><h1><?= te('My account') ?></h1><p class="muted"><?= te('Administrator accounts are used for managing Timetracker only; they have no clients or time reports.') ?></p></div></div>
<div class="split">
    <section class="card">
        <h2><?= te('Profile') ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="op" value="profile">
            <label><?= te('Username') ?>
                <input type="text" name="username" value="<?= e($user['username']) ?>" required pattern="[A-Za-z0-9._\-]{3,64}" maxlength="64" autocomplete="username">
                <small class="muted"><?= te('You sign in with this name. Changing it takes effect immediately.') ?></small>
            </label>
            <label><?= te('Display name') ?>
                <input type="text" name="display_name" value="<?= e($user['display_name']) ?>" maxlength="120">
            </label>
            <label><?= te('Language') ?>
                <select name="locale">
                    <?php foreach (TimeTracker\I18n::LOCALES as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $code === ($user['locale'] ?? 'en') ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Save') ?></button></div>
        </form>
    </section>
    <section class="card">
        <h2><?= te('Change password') ?></h2>
        <?php foreach ($pwErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="op" value="password">
            <label><?= te('Current password') ?>
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label><?= te('New password') ?> <span class="muted"><?= te('(min. 10 characters)') ?></span>
                <input type="password" name="new_password" required autocomplete="new-password">
            </label>
            <label><?= te('Repeat new password') ?>
                <input type="password" name="new_password2" required autocomplete="new-password">
            </label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Change password') ?></button></div>
        </form>
    </section>
</div>
