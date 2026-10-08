<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Repository\Clients;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$form = null;

if (is_post()) {
    require_csrf();
    $op = input('op');
    $id = (int) input('id') ?: null;
    $existing = $id ? Clients::find($uid, $id) : null;
    if ($id && !$existing) {
        flash('error', 'Client not found.');
        redirect('clients.php');
    }

    if (in_array($op, ['archive', 'unarchive', 'delete'], true) && !$id) {
        redirect('clients.php');
    }

    switch ($op) {
        case 'save':
            [$data, $errors] = Clients::validate($uid, $_POST, $id);
            if (!$errors) {
                Clients::save($uid, $data, $id);
                flash('success', $id ? 'Client updated.' : 'Client created.');
                redirect('clients.php');
            }
            $form = $data + ['id' => $id];
            break;
        case 'archive':
        case 'unarchive':
            Clients::setArchived($uid, $id, $op === 'archive');
            flash('success', $op === 'archive' ? 'Client archived. Existing time reports are kept.' : 'Client restored.');
            redirect('clients.php');
        case 'delete':
            if (Clients::delete($uid, $id)) {
                flash('success', 'Client deleted.');
            } else {
                flash('error', 'This client has time reports and cannot be deleted. Archive it instead.');
            }
            redirect('clients.php');
    }
}

if ($form === null) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $row = $editId ? Clients::find($uid, $editId) : null;
    $form = $row ?? ['id' => null, 'name' => '', 'reference' => '', 'color' => Clients::nextColor($uid), 'hourly_rate' => null, 'notes' => ''];
}

View::render('clients', [
    'title'   => 'Clients',
    'active'  => 'clients',
    'user'    => $user,
    'clients' => Clients::all($uid),
    'form'    => $form,
    'errors'  => $errors,
    'palette' => Clients::PALETTE,
]);
