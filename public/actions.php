<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Repository\Actions;
use TimeTracker\Repository\Clients;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$form = null;

// Actions belong to a client: pick which client's actions to manage.
$clients = Clients::all($uid);
$clientId = (int) ($_POST['client_id'] ?? $_GET['client'] ?? 0);
$client = $clientId ? Clients::find($uid, $clientId) : null;
if (!$client && $clients) {
    $firstActive = array_values(array_filter($clients, static fn($c) => !$c['is_archived']));
    $client = Clients::find($uid, (int) (($firstActive[0] ?? $clients[0])['id']));
}
$here = $client ? 'actions.php?client=' . (int) $client['id'] : 'actions.php';

if (is_post() && $client) {
    require_csrf();
    $op = input('op');
    $id = (int) input('id') ?: null;
    $existing = $id ? Actions::find($uid, $id) : null;
    if ($id && (!$existing || (int) $existing['client_id'] !== (int) $client['id'])) {
        flash('error', 'Action not found.');
        redirect($here);
    }
    if (in_array($op, ['archive', 'unarchive', 'delete'], true) && !$id) {
        redirect($here);
    }

    switch ($op) {
        case 'save':
            [$data, $errors] = Actions::validate($uid, (int) $client['id'], $_POST, $id);
            if (!$errors) {
                Actions::save($uid, (int) $client['id'], $data, $id);
                flash('success', $id ? 'Action updated.' : 'Action created.');
                redirect($here);
            }
            $form = $data + ['id' => $id];
            break;
        case 'archive':
        case 'unarchive':
            Actions::setArchived($uid, $id, $op === 'archive');
            flash('success', $op === 'archive' ? 'Action archived. Existing time reports are kept.' : 'Action restored.');
            redirect($here);
        case 'delete':
            if (Actions::delete($uid, $id)) {
                flash('success', 'Action deleted.');
            } else {
                flash('error', 'This action is used by time reports and cannot be deleted. Archive it instead.');
            }
            redirect($here);
        case 'standard':
            $before = count(Actions::forClient($uid, (int) $client['id']));
            foreach (Actions::STANDARD as $i => [$name, $color, $mult, $billable]) {
                if (!TimeTracker\Db::value('SELECT 1 FROM actions WHERE client_id = ? AND name = ?', [(int) $client['id'], $name])) {
                    Actions::save($uid, (int) $client['id'], ['name' => $name, 'color' => $color, 'rate_multiplier' => $mult, 'is_billable' => $billable]);
                }
            }
            flash('success', 'Standard actions added (existing ones were left alone).');
            redirect($here);
        case 'copy':
            $source = Clients::find($uid, (int) input('from'));
            if (!$source || (int) $source['id'] === (int) $client['id']) {
                flash('error', 'Choose another client to copy from.');
            } else {
                $n = Actions::copyFrom($uid, (int) $source['id'], (int) $client['id']);
                flash('success', $n ? "Copied $n action(s) from {$source['name']}." : 'Nothing to copy: this client already has all of those actions.');
            }
            redirect($here);
    }
}

if ($form === null) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $row = ($editId && $client) ? Actions::find($uid, $editId) : null;
    if ($row && (int) $row['client_id'] !== (int) $client['id']) {
        $row = null;
    }
    $form = $row ?? ['id' => null, 'name' => '', 'color' => '#10b981', 'rate_multiplier' => '1.00', 'is_billable' => 1];
}

View::render('actions', [
    'title'   => 'Actions',
    'active'  => 'actions',
    'user'    => $user,
    'clients' => $clients,
    'client'  => $client,
    'actions' => $client ? Actions::forClient($uid, (int) $client['id']) : [],
    'form'    => $form,
    'errors'  => $errors,
    'palette' => Clients::PALETTE,
]);
