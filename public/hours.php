<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\Repository\WorkingHours;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$intervals = null;

if (is_post()) {
    require_csrf();
    [$parsed, $errors] = WorkingHours::parse(is_array($_POST['wh'] ?? null) ? $_POST['wh'] : []);

    // Lunch window: both empty = no default break
    $ls = input('lunch_start');
    $le = input('lunch_end');
    $lunch = [null, null];
    if ($ls !== '' || $le !== '') {
        $lsm = parse_time_minutes($ls);
        $lem = parse_time_minutes($le);
        if ($lsm === null || $lem === null) {
            $errors[] = 'Lunch window: enter both times as HH:MM, or leave both empty.';
        } elseif ($lem <= $lsm) {
            $errors[] = 'Lunch window: the end must be after the start.';
        } else {
            $lunch = [minutes_to_hhmm($lsm) . ':00', minutes_to_hhmm($lem) . ':00'];
        }
    }

    if (!$errors) {
        WorkingHours::save($uid, $parsed);
        Db::run('UPDATE users SET lunch_start = ?, lunch_end = ? WHERE id = ?', [$lunch[0], $lunch[1], $uid]);
        flash('success', 'Working hours saved.');
        redirect('hours.php');
    }
    $intervals = $parsed;
    $user['lunch_start'] = $ls !== '' ? $ls : null;
    $user['lunch_end'] = $le !== '' ? $le : null;
}

View::render('hours', [
    'title'     => 'Working hours',
    'active'    => 'hours',
    'user'      => $user,
    'intervals' => $intervals ?? WorkingHours::intervals($uid),
    'errors'    => $errors,
]);
