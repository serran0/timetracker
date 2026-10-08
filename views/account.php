<?php
/** @var array $user */
/** @var string[] $errors */
/** @var string[] $pwErrors */
/** @var string[] $timezones */
/** @var string[] $dayErrors */
/** @var array $dayForm */
/** @var array $freeDays */
?>
<div class="page-head"><div><h1><?= te('Account & settings') ?></h1><p class="muted"><?= th('Signed in as {name}. Your clients, actions and time reports are private to your account.', ['name' => '<strong>' . e($user['username']) . '</strong>']) ?></p></div></div>
<div class="split">
    <section class="card">
        <h2><?= te('Profile') ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="op" value="profile">
            <?php if (!empty($user['is_admin'])): ?>
            <label><?= te('Username') ?>
                <input type="text" name="username" value="<?= e($user['username']) ?>" required pattern="[A-Za-z0-9._\-]{3,64}" maxlength="64" autocomplete="username">
                <small class="muted"><?= te('You sign in with this name. Changing it takes effect immediately.') ?></small>
            </label>
            <?php endif; ?>
            <label><?= te('Display name') ?>
                <input type="text" name="display_name" value="<?= e($user['display_name']) ?>" maxlength="120">
            </label>
            <label><?= te('Timezone') ?>
                <select name="timezone">
                    <?php foreach ($timezones as $tz): ?><option <?= $tz === $user['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><?= te('Currency') ?>
                <input type="text" name="currency" value="<?= e($user['currency']) ?>" maxlength="8" size="6">
            </label>
            <label><?= te('Language') ?>
                <select name="locale">
                    <?php foreach (TimeTracker\I18n::LOCALES as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $code === ($user['locale'] ?? 'en') ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="muted"><?= te('Applies to menus, dates, reports and exports. Action names you have renamed are never translated.') ?></small>
            </label>
            <label><?= te('Default calendar colouring') ?>
                <select name="default_color">
                    <option value="client" <?= ($user['default_color'] ?? 'client') === 'client' ? 'selected' : '' ?>><?= te('Colour by client') ?></option>
                    <option value="action" <?= ($user['default_color'] ?? 'client') === 'action' ? 'selected' : '' ?>><?= te('Colour by action') ?></option>
                </select>
                <small class="muted"><?= te('Used when you open the calendar. You can still switch in the filter bar.') ?></small>
            </label>
            <label class="check-label">
                <input type="checkbox" name="show_holidays" value="1" <?= !empty($user['show_holidays']) ? 'checked' : '' ?>>
                <?= te('Show Swedish public holidays in the calendar') ?>
            </label>
            <p class="muted setting-help under-check"><?= te('Red days such as Julafton and Midsommarafton are tinted red in every view and are left out of whole-week reports.') ?></p>
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

<section class="card" id="free-days">
    <h2><?= te('Work-free days') ?></h2>
    <p class="muted"><?= te('Add your own days off, such as vacation or bridge days. They are tinted in the calendar and left out of whole-week reports. Public holidays are controlled by the setting above.') ?></p>
    <?php foreach ($dayErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="freeday-form">
        <?= csrf_field() ?><input type="hidden" name="op" value="freeday_add">
        <label><?= te('From') ?>
            <input type="date" name="from" value="<?= e($dayForm['from']) ?>" required>
        </label>
        <label><?= te('To (optional)') ?>
            <input type="date" name="to" value="<?= e($dayForm['to']) ?>">
        </label>
        <label class="grow"><?= te('Name (optional)') ?>
            <input type="text" name="name" value="<?= e($dayForm['name']) ?>" maxlength="120" placeholder="<?= te('e.g. Summer holiday') ?>">
        </label>
        <button class="btn btn-primary" type="submit"><?= te('Add day off') ?></button>
    </form>
    <?php if (!$freeDays): ?>
        <p class="muted"><?= te('No work-free days added yet.') ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <tbody>
        <?php foreach ($freeDays as $fd): ?>
            <?php
            $a = new DateTimeImmutable($fd['start_date']);
            $b = new DateTimeImmutable($fd['end_date']);
            $n = $a->diff($b)->days + 1;
            $past = $fd['end_date'] < date('Y-m-d');
            ?>
            <tr class="<?= $past ? 'is-archived' : '' ?>">
                <td><span class="dot dot-free"></span><strong><?= e(TimeTracker\Repository\FreeDays::label($fd['name'])) ?></strong></td>
                <td class="nowrap">
                    <?= e(date_l10n($a, 'D j M Y')) ?><?php if ($n > 1): ?> – <?= e(date_l10n($b, 'D j M Y')) ?><?php endif; ?>
                </td>
                <td class="num nowrap"><?= te($n === 1 ? '{n} day' : '{n} days', ['n' => $n]) ?></td>
                <td class="row-actions">
                    <form method="post" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="op" value="freeday_delete"><input type="hidden" name="id" value="<?= (int) $fd['id'] ?>">
                        <button class="btn btn-sm btn-danger" data-confirm="<?= te('Remove this day off?') ?>"><?= te('Remove') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
