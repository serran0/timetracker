<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\I18n;
use TimeTracker\Repository\Users;
use TimeTracker\View;

$user = Auth::requireAdmin();
$errors = [];
$form = ['username' => '', 'display_name' => '', 'timezone' => $user['timezone'], 'currency' => $user['currency'], 'locale' => $user['locale'], 'is_admin' => 0];

if (is_post()) {
    require_csrf();
    $op = input('op');
    $id = (int) input('id');

    if ($op === 'create') {
        $form = [
            'username'     => input('username'),
            'display_name' => mb_substr(input('display_name'), 0, 120),
            'timezone'     => input('timezone'),
            'currency'     => strtoupper(input('currency')),
            'locale'       => I18n::isValid(input('locale')) ? input('locale') : 'en',
            'is_admin'     => !empty($_POST['is_admin']) ? 1 : 0,
        ];
        $password = (string) ($_POST['password'] ?? '');
        if ($e = Users::validateUsername($form['username'])) {
            $errors[] = $e;
        } elseif (Users::usernameTaken($form['username'])) {
            $errors[] = t('That username is already taken.');
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
            Users::create($form['username'], $password, $form['display_name'], (bool) $form['is_admin'], $form['timezone'], $form['currency'], $form['locale']);
            flash('success', t('User "{name}" created with their own empty workspace.', ['name' => $form['username']]));
            redirect('users.php');
        }
    } elseif ($op === 'toggle' && $id) {
        if ($id === (int) $user['id']) {
            flash('error', t('You cannot deactivate your own account.'));
        } else {
            Db::run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
            flash('success', t('User updated.'));
        }
        redirect('users.php');
    } elseif ($op === 'reset' && $id) {
        $new = (string) ($_POST['new_password'] ?? '');
        if ($e = Users::validatePassword($new)) {
            flash('error', $e);
        } elseif (!Users::find($id)) {
            flash('error', t('User not found.'));
        } else {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($new), $id]);
            flash('success', t('Password reset.'));
        }
        redirect('users.php');
    }
}

$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 64);
$result = Users::page($search, (int) ($_GET['page'] ?? 1));

View::render('users', [
    'title'     => t('Users'),
    'user'      => $user,
    'users'     => $result['rows'],
    'paging'    => $result + ['q' => $search],
    'form'      => $form,
    'errors'    => $errors,
    'timezones' => DateTimeZone::listIdentifiers(),
]);
