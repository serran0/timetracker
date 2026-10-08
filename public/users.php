<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\Repository\Users;
use TimeTracker\View;

$user = Auth::requireAdmin();
$errors = [];
$form = ['username' => '', 'display_name' => '', 'timezone' => $user['timezone'], 'currency' => $user['currency'], 'is_admin' => 0];

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
            'is_admin'     => !empty($_POST['is_admin']) ? 1 : 0,
        ];
        $password = (string) ($_POST['password'] ?? '');
        if ($e = Users::validateUsername($form['username'])) {
            $errors[] = $e;
        } elseif (Users::usernameTaken($form['username'])) {
            $errors[] = 'That username is already taken.';
        }
        if ($e = Users::validatePassword($password)) {
            $errors[] = $e;
        }
        if (!in_array($form['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Choose a valid timezone.';
        }
        if (!preg_match('/^[A-Z]{1,8}$/', $form['currency'])) {
            $errors[] = 'Currency should be a short code such as EUR, SEK or USD.';
        }
        if (!$errors) {
            Users::create($form['username'], $password, $form['display_name'], (bool) $form['is_admin'], $form['timezone'], $form['currency']);
            flash('success', 'User "' . $form['username'] . '" created with their own empty workspace.');
            redirect('users.php');
        }
    } elseif ($op === 'toggle' && $id) {
        if ($id === (int) $user['id']) {
            flash('error', 'You cannot deactivate your own account.');
        } else {
            Db::run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
            flash('success', 'User updated.');
        }
        redirect('users.php');
    } elseif ($op === 'reset' && $id) {
        $new = (string) ($_POST['new_password'] ?? '');
        if ($e = Users::validatePassword($new)) {
            flash('error', $e);
        } elseif (!Users::find($id)) {
            flash('error', 'User not found.');
        } else {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($new), $id]);
            flash('success', 'Password reset.');
        }
        redirect('users.php');
    }
}

View::render('users', [
    'title'     => 'Users',
    'user'      => $user,
    'users'     => Users::all(),
    'form'      => $form,
    'errors'    => $errors,
    'timezones' => DateTimeZone::listIdentifiers(),
]);
