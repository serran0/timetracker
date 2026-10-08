<?php
/** @var array $user */
/** @var string[] $errors */
/** @var string[] $pwErrors */
/** @var string[] $timezones */
?>
<div class="page-head"><div><h1>Account &amp; settings</h1><p class="muted">Signed in as <strong><?= e($user['username']) ?></strong>. Your clients, actions and time reports are private to your account.</p></div></div>
<div class="split">
    <section class="card">
        <h2>Profile</h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="op" value="profile">
            <label>Display name
                <input type="text" name="display_name" value="<?= e($user['display_name']) ?>" maxlength="120">
            </label>
            <label>Timezone
                <select name="timezone">
                    <?php foreach ($timezones as $tz): ?><option <?= $tz === $user['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Currency
                <input type="text" name="currency" value="<?= e($user['currency']) ?>" maxlength="8" size="6">
            </label>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
    </section>
    <section class="card">
        <h2>Change password</h2>
        <?php foreach ($pwErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="op" value="password">
            <label>Current password
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>New password <span class="muted">(min. 10 characters)</span>
                <input type="password" name="new_password" required autocomplete="new-password">
            </label>
            <label>Repeat new password
                <input type="password" name="new_password2" required autocomplete="new-password">
            </label>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Change password</button></div>
        </form>
    </section>
</div>
