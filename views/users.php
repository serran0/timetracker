<?php
/** @var array $user */
/** @var array $users */
/** @var array $form */
/** @var string[] $errors */
/** @var string[] $timezones */
?>
<div class="page-head"><div><h1><?= te('Users') ?></h1><p class="muted"><?= te('Every user has a completely separate environment: their own clients, actions, working hours and time reports.') ?></p></div></div>
<div class="split">
    <section class="card">
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= te('User') ?></th><th><?= te('Role') ?></th><th><?= te('Language') ?></th><th><?= te('Last sign-in') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="<?= $u['is_active'] ? '' : 'is-archived' ?>">
                    <td><strong><?= e($u['username']) ?></strong><?php if ($u['display_name'] && $u['display_name'] !== $u['username']): ?><br><small class="muted"><?= e($u['display_name']) ?></small><?php endif; ?></td>
                    <td><?= te($u['is_admin'] ? 'Administrator' : 'User') ?><?= $u['is_active'] ? '' : ' <span class="badge">' . te('Disabled') . '</span>' ?></td>
                    <td><?= e(TimeTracker\I18n::LOCALES[$u['locale']] ?? $u['locale']) ?></td>
                    <td><small><?= $u['last_login_at'] ? e($u['last_login_at']) : '<span class="muted">' . te('never') . '</span>' ?></small></td>
                    <td class="row-actions">
                        <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm" name="op" value="toggle"><?= te($u['is_active'] ? 'Disable' : 'Enable') ?></button>
                        </form>
                        <?php endif; ?>
                        <details class="inline-details">
                            <summary class="btn btn-sm"><?= te('Reset password') ?></summary>
                            <form method="post" class="popover">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="op" value="reset">
                                <input type="password" name="new_password" placeholder="<?= te('New password (min. 10)') ?>" required autocomplete="new-password">
                                <button class="btn btn-sm btn-primary"><?= te('Set') ?></button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <section class="card">
        <h2><?= te('New user') ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="op" value="create">
            <label><?= te('Username') ?>
                <input type="text" name="username" value="<?= e($form['username']) ?>" required pattern="[A-Za-z0-9._\-]{3,64}">
            </label>
            <label><?= te('Display name') ?>
                <input type="text" name="display_name" value="<?= e($form['display_name']) ?>" maxlength="120">
            </label>
            <label><?= te('Password') ?> <span class="muted"><?= te('(min. 10 characters)') ?></span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <div class="grid-2">
                <label><?= te('Timezone') ?>
                    <select name="timezone"><?php foreach ($timezones as $tz): ?><option <?= $tz === $form['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select>
                </label>
                <label><?= te('Currency') ?>
                    <input type="text" name="currency" value="<?= e($form['currency']) ?>" maxlength="8" size="6">
                </label>
            </div>
            <label><?= te('Language') ?>
                <select name="locale">
                    <?php foreach (TimeTracker\I18n::LOCALES as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $code === ($form['locale'] ?? 'en') ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="check-label"><input type="checkbox" name="is_admin" value="1" <?= $form['is_admin'] ? 'checked' : '' ?>> <?= te('Administrator (can manage users)') ?></label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Create user') ?></button></div>
        </form>
    </section>
</div>
