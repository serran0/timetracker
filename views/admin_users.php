<?php
/** @var array $user */
/** @var array $users */
/** @var array{total: int, page: int, pages: int, q: string, role: string} $paging */
/** @var array{users: int, admins: int, disabled: int} $counts */
/** @var array $form */
/** @var string[] $errors */
/** @var string[] $timezones */
$keep = ['q' => $paging['q'], 'role' => $paging['role'], 'page' => $paging['page']]; // returned to after an action
$hidden = static function () use ($keep): string {
    $h = '';
    foreach ($keep as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    return $h;
};
?>
<div class="page-head"><div><h1><?= te('Users') ?></h1><p class="muted"><?= te('{users} regular users and {admins} administrators. Every regular user has a completely separate workspace; administrators only manage Timetracker and have no time reports.', ['users' => $counts['users'], 'admins' => $counts['admins']]) ?></p></div></div>
<div class="split">
    <section class="card">
        <form method="get" action="admin_users.php" class="user-search">
            <span class="search-box"><input type="search" name="q" value="<?= e($paging['q']) ?>" placeholder="<?= te('Search by username or name') ?>" aria-label="<?= te('Search by username or name') ?>" maxlength="64"></span>
            <select name="role" aria-label="<?= te('Account type') ?>" data-autosubmit>
                <option value=""><?= te('All accounts') ?></option>
                <option value="user" <?= $paging['role'] === 'user' ? 'selected' : '' ?>><?= te('Regular users') ?></option>
                <option value="admin" <?= $paging['role'] === 'admin' ? 'selected' : '' ?>><?= te('Administrators') ?></option>
            </select>
            <button class="btn btn-primary" type="submit"><?= te('Search') ?></button>
            <?php if ($paging['q'] !== '' || $paging['role'] !== ''): ?><a class="btn" href="admin_users.php"><?= te('Clear') ?></a><?php endif; ?>
        </form>
        <?php if (!$users): ?><p class="muted"><?= te('No users match your search.') ?></p><?php endif; ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= te('User') ?></th><th><?= te('Account type') ?></th><th><?= te('Language') ?></th><th><?= te('Last sign-in') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="<?= $u['is_active'] ? '' : 'is-archived' ?>">
                    <td><strong><?= e($u['username']) ?></strong><?php if ($u['display_name'] && $u['display_name'] !== $u['username']): ?><br><small class="muted"><?= e($u['display_name']) ?></small><?php endif; ?></td>
                    <td><span class="badge <?= $u['is_admin'] ? 'badge-admin' : '' ?>"><?= te($u['is_admin'] ? 'Administrator' : 'Regular user') ?></span><?= $u['is_active'] ? '' : ' <span class="badge">' . te('Disabled') . '</span>' ?><?= $u['must_change_password'] ? ' <span class="badge" title="' . te('Must choose a new password at the next sign-in') . '">' . te('Password change pending') . '</span>' : '' ?></td>
                    <td><?= e(TimeTracker\I18n::LOCALES[$u['locale']] ?? $u['locale']) ?></td>
                    <td><small><?= $u['last_login_at'] ? e($u['last_login_at']) : '<span class="muted">' . te('never') . '</span>' ?></small></td>
                    <td class="row-actions">
                        <details class="inline-details">
                            <summary class="btn btn-sm"><?= te('Rename') ?></summary>
                            <form method="post" class="popover">
                                <?= csrf_field() ?><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="op" value="rename">
                                <input type="text" name="new_username" value="<?= e($u['username']) ?>" required pattern="[A-Za-z0-9._\-]{3,64}" maxlength="64" aria-label="<?= te('New username') ?>">
                                <button class="btn btn-sm btn-primary"><?= te('Set') ?></button>
                            </form>
                        </details>
                        <details class="inline-details">
                            <summary class="btn btn-sm"><?= te('Reset password') ?></summary>
                            <form method="post" class="popover" autocomplete="off">
                                <?= csrf_field() ?><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="op" value="reset">
                                <input type="password" name="new_password" placeholder="<?= te('New password (min. 10)') ?>" required autocomplete="new-password">
                                <label class="check-label"><input type="checkbox" name="force" value="1" checked> <?= te('Require a new password at the next sign-in') ?></label>
                                <button class="btn btn-sm btn-primary"><?= te('Set') ?></button>
                            </form>
                        </details>
                        <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm" name="op" value="toggle"><?= te($u['is_active'] ? 'Disable' : 'Enable') ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($paging['pages'] > 1): ?>
        <nav class="pager" aria-label="<?= te('Pages') ?>">
            <span class="muted"><?= te('Page {page} of {pages} · {total} users', ['page' => $paging['page'], 'pages' => $paging['pages'], 'total' => $paging['total']]) ?></span>
            <span class="pager-links">
                <?php if ($paging['page'] > 1): ?><a class="btn btn-sm" href="<?= e(url('admin_users.php', ['q' => $paging['q'], 'role' => $paging['role'], 'page' => $paging['page'] - 1])) ?>">‹ <?= te('Previous') ?></a><?php endif; ?>
                <?php if ($paging['page'] < $paging['pages']): ?><a class="btn btn-sm" href="<?= e(url('admin_users.php', ['q' => $paging['q'], 'role' => $paging['role'], 'page' => $paging['page'] + 1])) ?>"><?= te('Next') ?> ›</a><?php endif; ?>
            </span>
        </nav>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2><?= te('New account') ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="op" value="create">
            <fieldset>
                <legend><?= te('Account type') ?></legend>
                <label class="check-label"><input type="radio" name="kind" value="user" <?= $form['kind'] === 'user' ? 'checked' : '' ?>> <?= te('Regular user (reports time)') ?></label>
                <label class="check-label"><input type="radio" name="kind" value="admin" <?= $form['kind'] === 'admin' ? 'checked' : '' ?>> <?= te('Administrator (manages Timetracker)') ?></label>
                <p class="muted setting-help"><?= te('The type cannot be changed afterwards: a regular user can never be an administrator, and an administrator never gets a time reporting workspace.') ?></p>
            </fieldset>
            <label><?= te('Username') ?>
                <input type="text" name="username" value="<?= e($form['username']) ?>" required pattern="[A-Za-z0-9._\-]{3,64}">
            </label>
            <label><?= te('Display name') ?>
                <input type="text" name="display_name" value="<?= e($form['display_name']) ?>" maxlength="120">
            </label>
            <label><?= te('Password') ?> <span class="muted"><?= te('(min. 10 characters)') ?></span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label class="check-label"><input type="checkbox" name="force" value="1" <?= $form['force'] ? 'checked' : '' ?>> <?= te('Require a new password at the first sign-in') ?></label>
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
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Create account') ?></button></div>
        </form>
    </section>
</div>
