<?php
/** @var array<string,string> $v */
/** @var array<string,string[]> $errors */
/** @var string[] $timezones */
?>
<div class="page-head"><div><h1><?= te('Settings') ?></h1><p class="muted"><?= te('Application settings for the whole installation.') ?></p></div></div>

<div class="split">
    <section class="card">
        <h2><?= te('Defaults for new accounts') ?></h2>
        <?php foreach ($errors['general'] as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="op" value="general">
            <label><?= te('Timezone') ?>
                <select name="default_timezone">
                    <option value=""><?= te('Same as the server') ?></option>
                    <?php foreach ($timezones as $tz): ?><option <?= $tz === $v['default_timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
                </select>
            </label>
            <div class="grid-2">
                <label><?= te('Currency') ?>
                    <input type="text" name="default_currency" value="<?= e($v['default_currency']) ?>" maxlength="8" size="6">
                </label>
                <label><?= te('Language') ?>
                    <select name="default_locale">
                        <?php foreach (TimeTracker\I18n::LOCALES as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $code === $v['default_locale'] ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <p class="muted setting-help"><?= te('These are pre-filled when you create a new account on the Users page.') ?></p>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Save') ?></button></div>
        </form>
    </section>

    <section class="card">
        <h2><?= te('Sign-in security') ?></h2>
        <?php foreach ($errors['security'] as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="op" value="security">
            <div class="grid-2">
                <label><?= te('Failed sign-ins per username') ?>
                    <input type="text" inputmode="numeric" name="login_max_per_user" value="<?= e($v['login.max_per_user']) ?>">
                </label>
                <label><?= te('Failed sign-ins per IP address') ?>
                    <input type="text" inputmode="numeric" name="login_max_per_ip" value="<?= e($v['login.max_per_ip']) ?>">
                </label>
            </div>
            <label><?= te('Lock-out time (minutes)') ?>
                <input type="text" inputmode="numeric" name="login_lock_minutes" value="<?= e($v['login.lock_minutes']) ?>">
            </label>
            <p class="muted setting-help"><?= te('After that many failed sign-ins within the lock-out time, further attempts are refused until it has passed.') ?></p>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Save') ?></button></div>
        </form>
    </section>
</div>

<section class="card" id="mail">
    <h2><?= te('Mail server') ?></h2>
    <p class="muted"><?= te('Used for notifications to administrators. Which events send mail will be added later; for now you can configure the server and the recipients and send a test email.') ?></p>
    <?php foreach ($errors['mail'] as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="op" value="mail">
        <label class="check-label"><input type="checkbox" name="mail_enabled" value="1" <?= $v['mail.enabled'] === '1' ? 'checked' : '' ?>> <?= te('Enable email notifications') ?></label>
        <div class="grid-3">
            <label><?= te('SMTP server') ?>
                <input type="text" name="mail_host" value="<?= e($v['mail.host']) ?>" placeholder="smtp.example.com" maxlength="253">
            </label>
            <label><?= te('Port') ?>
                <input type="text" inputmode="numeric" name="mail_port" value="<?= e($v['mail.port']) ?>">
            </label>
            <label><?= te('Encryption') ?>
                <select name="mail_encryption">
                    <option value="tls" <?= $v['mail.encryption'] === 'tls' ? 'selected' : '' ?>><?= te('STARTTLS (usually port 587)') ?></option>
                    <option value="ssl" <?= $v['mail.encryption'] === 'ssl' ? 'selected' : '' ?>><?= te('SSL/TLS (usually port 465)') ?></option>
                    <option value="none" <?= $v['mail.encryption'] === 'none' ? 'selected' : '' ?>><?= te('None (not recommended)') ?></option>
                </select>
            </label>
        </div>
        <div class="grid-2">
            <label><?= te('Username') ?>
                <input type="text" name="mail_username" value="<?= e($v['mail.username']) ?>" autocomplete="off" maxlength="190">
            </label>
            <label><?= te('Password') ?>
                <input type="password" name="mail_password" value="" autocomplete="new-password" placeholder="<?= $v['mail.password'] !== '' ? te('(saved – leave empty to keep)') : '' ?>">
                <?php if ($v['mail.password'] !== ''): ?><span class="check-label"><input type="checkbox" name="mail_clear_password" value="1"> <?= te('Remove the saved password') ?></span><?php endif; ?>
            </label>
        </div>
        <div class="grid-2">
            <label><?= te('Sender address') ?>
                <input type="text" name="mail_from_email" value="<?= e($v['mail.from_email']) ?>" placeholder="timetracker@example.com" maxlength="190">
            </label>
            <label><?= te('Sender name') ?>
                <input type="text" name="mail_from_name" value="<?= e($v['mail.from_name']) ?>" maxlength="100">
            </label>
        </div>
        <label><?= te('Notification recipients') ?> <span class="muted"><?= te('(one address per line)') ?></span>
            <textarea name="mail_recipients" rows="4" placeholder="admin@example.com"><?= e($v['mail.recipients']) ?></textarea>
        </label>
        <p class="muted setting-help"><?= te('The mail password is stored in the database. Restrict access to the database accordingly.') ?></p>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Save mail settings') ?></button></div>
    </form>
    <form method="post" class="inline-form card-sub">
        <?= csrf_field() ?>
        <button class="btn btn-sm" name="op" value="mail_test"><?= te('Send test email') ?></button>
        <span class="muted"><?= te('Sends to the saved recipients using the saved settings.') ?></span>
    </form>
</section>
