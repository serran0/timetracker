<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Repository\Actions;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$form = null;

if (is_post()) {
    require_csrf();
    $op = input('op');
    $id = (int) input('id') ?: null;
    $existing = $id ? Actions::find($uid, $id) : null;
    if ($id && !$existing) {
        flash('error', 'Action not found.');
        redirect('actions.php');
    }

    if (in_array($op, ['archive', 'unarchive', 'delete'], true) && !$id) {
        redirect('actions.php');
    }

    switch ($op) {
        case 'save':
            [$data, $errors] = Actions::validate($uid, $_POST, $id);
            if (!$errors) {
                Actions::save($uid, $data, $id);
                flash('success', $id ? 'Action updated.' : 'Action created.');
                redirect('actions.php');
            }
            $form = $data + ['id' => $id];
            break;
        case 'archive':
        case 'unarchive':
            Actions::setArchived($uid, $id, $op === 'archive');
            flash('success', $op === 'archive' ? 'Action archived. Existing time reports are kept.' : 'Action restored.');
            redirect('actions.php');
        case 'delete':
            if (Actions::delete($uid, $id)) {
                flash('success', 'Action deleted.');
            } else {
                flash('error', 'This action is used by time reports and cannot be deleted. Archive it instead.');
            }
            redirect('actions.php');
    }
}

if ($form === null) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $row = $editId ? Actions::find($uid, $editId) : null;
    $form = $row ?? ['id' => null, 'name' => '', 'color' => '#10b981', 'rate_multiplier' => '1.00', 'is_billable' => 1];
}

View::render('actions', [
    'title'   => 'Actions',
    'active'  => 'actions',
    'user'    => $user,
    'actions' => Actions::all($uid),
    'form'    => $form,
    'errors'  => $errors,
    'palette' => TimeTracker\Repository\Clients::PALETTE,
]);
