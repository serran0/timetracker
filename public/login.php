<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\View;

$next = Auth::safeNext(to_str($_GET['next'] ?? $_POST['next'] ?? ''));

if ($u = Auth::user()) {
    redirect($u['is_admin'] ? 'admin.php' : $next);
}

$error = null;
$username = '';
if (is_post()) {
    require_csrf();
    $username = input('username');
    $result = Auth::attempt($username, to_str($_POST['password'] ?? ''));
    if ($result['ok']) {
        redirect(Auth::user()['is_admin'] ? 'admin.php' : $next);
    }
    $error = $result['error'];
}

View::render('login', [
    'title'     => t('Sign in'),
    'bodyClass' => 'auth',
    'error'     => $error,
    'username'  => $username,
    'next'      => $next,
]);
