<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Repository\WorkingHours;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$intervals = null;

if (is_post()) {
    require_csrf();
    [$parsed, $errors] = WorkingHours::parse(is_array($_POST['wh'] ?? null) ? $_POST['wh'] : []);
    if (!$errors) {
        WorkingHours::save($uid, $parsed);
        flash('success', 'Working hours saved.');
        redirect('hours.php');
    }
    $intervals = $parsed;
}

View::render('hours', [
    'title'     => 'Working hours',
    'active'    => 'hours',
    'user'      => $user,
    'intervals' => $intervals ?? WorkingHours::intervals($uid),
    'errors'    => $errors,
]);
