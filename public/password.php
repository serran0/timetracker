<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\Repository\Users;
use TimeTracker\View;

/** Shown to an account that must choose a new password (the placeholder administrator, or after a reset by an administrator). */
$user = Auth::requireAny(true);
$home = $user['is_admin'] ? 'admin.php' : 'calendar.php';
if (empty($user['must_change_password'])) {
    redirect($home);
}

$errors = [];
if (is_post()) {
    require_csrf();
    $new = (string) ($_POST['new_password'] ?? '');
    if ($e = Users::validatePassword($new)) {
        $errors[] = $e;
    }
    if ($new !== (string) ($_POST['new_password2'] ?? '')) {
        $errors[] = t('The new passwords do not match.');
    }
    if (password_verify($new, $user['password_hash'])) {
        $errors[] = t('Choose a password that is different from the current one.');
    }
    if (!$errors) {
        Users::setPassword((int) $user['id'], $new, false);
        session_regenerate_id(true);
        Audit::log('auth.password_changed', [], $user);
        flash('success', t('Password changed.'));
        redirect($home);
    }
}

View::render('password', ['title' => t('Choose a new password'), 'bodyClass' => 'auth', 'user' => $user, 'errors' => $errors]);
