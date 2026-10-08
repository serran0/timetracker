<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\View;

$next = Auth::safeNext((string) ($_GET['next'] ?? $_POST['next'] ?? ''));

if (Auth::user()) {
    redirect($next);
}

$error = null;
$username = '';
if (is_post()) {
    require_csrf();
    $username = input('username');
    $result = Auth::attempt($username, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        redirect($next);
    }
    $error = $result['error'];
}

View::render('login', [
    'title'     => 'Sign in',
    'bodyClass' => 'auth',
    'error'     => $error,
    'username'  => $username,
    'next'      => $next,
]);
