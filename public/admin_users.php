<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\I18n;
use TimeTracker\Repository\Users;
use TimeTracker\Settings;
use TimeTracker\View;

$user = Auth::requireAdmin();
$errors = [];
$defaultTz = Settings::get('default_timezone') ?: (string) TimeTracker\Config::get('app.timezone', 'UTC');
$form = ['kind' => 'user', 'username' => '', 'display_name' => '', 'timezone' => $defaultTz, 'currency' => Settings::get('default_currency'), 'locale' => Settings::get('default_locale'), 'force' => 1];

if (is_post()) {
    require_csrf();
    $op = input('op');
    $id = (int) input('id');
    $target = $id ? Users::find($id) : null;

    if ($op === 'create') {
        // The kind of account is final: administrator or regular user, never both and never changed later.
        $form = [
            'kind'         => input('kind') === 'admin' ? 'admin' : 'user',
            'username'     => trim(input('username')),
            'display_name' => mb_substr(input('display_name'), 0, 120),
            'timezone'     => input('timezone'),
            'currency'     => strtoupper(input('currency')),
            'locale'       => I18n::isValid(input('locale')) ? input('locale') : 'en',
            'force'        => !empty($_POST['force']) ? 1 : 0,
        ];
        $password = to_str($_POST['password'] ?? '');
        if ($e = Users::usernameError($form['username'])) {
            $errors[] = $e;
        }
        if ($e = Users::validatePassword($password)) {
            $errors[] = $e;
        }
        if (!in_array($form['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors[] = t('Choose a valid timezone.');
        }
        if (!preg_match('/^[A-Z]{1,8}$/', $form['currency'])) {
            $errors[] = t('Currency should be a short code such as EUR, SEK or USD.');
        }
        if (!$errors) {
            $admin = $form['kind'] === 'admin';
            Users::create($form['username'], $password, $form['display_name'], $admin, $form['timezone'], $form['currency'], $form['locale'], null, (bool) $form['force']);
            Audit::log($admin ? 'admin.create' : 'user.create', ['username' => $form['username']], $user);
            flash('success', $admin
                ? t('Administrator "{name}" created.', ['name' => $form['username']])
                : t('User "{name}" created with their own empty workspace.', ['name' => $form['username']]));
            redirect('admin_users.php');
        }
    } elseif ($op === 'rename' && $target) {
        $new = trim(input('new_username'));
        if ($new === $target['username']) {
            flash('success', t('Nothing to change.'));
        } elseif ($e = Users::usernameError($new, (int) $target['id'])) {
            flash('error', $e);
        } else {
            $display = $target['display_name'] === $target['username'] ? $new : $target['display_name'];
            Db::run('UPDATE users SET username = ?, display_name = ? WHERE id = ?', [$new, $display, (int) $target['id']]);
            Audit::log('user.rename', ['old' => $target['username'], 'new' => $new], $user);
            flash('success', t('Username changed to "{name}".', ['name' => $new]));
        }
        redirect(url('admin_users.php', ['q' => input('q'), 'role' => input('role'), 'page' => input('page')]));
    } elseif ($op === 'reset' && $target) {
        $new = to_str($_POST['new_password'] ?? '');
        if ($e = Users::validatePassword($new)) {
            flash('error', $e);
        } else {
            Users::setPassword((int) $target['id'], $new, !empty($_POST['force']));
            Audit::log('user.password_reset', ['username' => $target['username']], $user);
            flash('success', t('Password reset.'));
        }
        redirect(url('admin_users.php', ['q' => input('q'), 'role' => input('role'), 'page' => input('page')]));
    } elseif ($op === 'toggle' && $target) {
        if ((int) $target['id'] === (int) $user['id']) {
            flash('error', t('You cannot deactivate your own account.'));
        } elseif ($target['is_admin'] && $target['is_active'] && (int) Db::value('SELECT COUNT(*) FROM users WHERE is_admin = 1 AND is_active = 1') <= 1) {
            flash('error', t('You cannot disable the last active administrator.'));
        } else {
            Db::run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [(int) $target['id']]);
            Audit::log($target['is_active'] ? 'user.disable' : 'user.enable', ['username' => $target['username']], $user);
            flash('success', t('User updated.'));
        }
        redirect(url('admin_users.php', ['q' => input('q'), 'role' => input('role'), 'page' => input('page')]));
    }
}

$search = mb_substr(trim(to_str($_GET['q'] ?? '')), 0, 64);
$role = in_array($_GET['role'] ?? '', ['user', 'admin'], true) ? $_GET['role'] : '';
$result = Users::page($search, (int) ($_GET['page'] ?? 1), Users::PER_PAGE, $role);

View::render('admin_users', [
    'title'     => t('Users'),
    'active'    => 'admin_users',
    'user'      => $user,
    'users'     => $result['rows'],
    'paging'    => $result + ['q' => $search, 'role' => $role],
    'counts'    => Users::counts(),
    'form'      => $form,
    'errors'    => $errors,
    'timezones' => DateTimeZone::listIdentifiers(),
]);
