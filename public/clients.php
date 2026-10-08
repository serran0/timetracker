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

if (is_post()) {
    require_csrf();
    $op = input('op');
    $id = (int) input('id') ?: null;
    $existing = $id ? Clients::find($uid, $id) : null;
    if ($id && !$existing) {
        flash('error', t('Client not found.'));
        redirect('clients.php');
    }

    if (in_array($op, ['archive', 'unarchive', 'delete'], true) && !$id) {
        redirect('clients.php');
    }

    switch ($op) {
        case 'save':
            [$data, $errors] = Clients::validate($uid, $_POST, $id);
            if (!$errors) {
                $savedId = Clients::save($uid, $data, $id);
                if (!$id) {
                    // Every client has its own actions: start from the standard set, a copy of another client, or nothing.
                    $seed = input('seed', 'standard');
                    $source = str_starts_with($seed, 'copy:') ? Clients::find($uid, (int) substr($seed, 5)) : null;
                    if ($source) {
                        Actions::copyFrom($uid, (int) $source['id'], $savedId);
                    } elseif ($seed !== 'none') {
                        Actions::seedStandard($uid, $savedId);
                    }
                }
                flash('success', $id ? t('Client updated.') : t('Client created. Adjust its actions and rate multipliers on the Actions page.'));
                redirect($id ? 'clients.php' : 'actions.php?client=' . $savedId);
            }
            $form = $data + ['id' => $id];
            break;
        case 'archive':
        case 'unarchive':
            Clients::setArchived($uid, $id, $op === 'archive');
            flash('success', $op === 'archive' ? t('Client archived. Existing time reports are kept.') : t('Client restored.'));
            redirect('clients.php');
        case 'delete':
            if (Clients::delete($uid, $id)) {
                flash('success', t('Client deleted.'));
            } else {
                flash('error', t('This client has time reports and cannot be deleted. Archive it instead.'));
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
    'title'   => t('Clients'),
    'active'  => 'clients',
    'user'    => $user,
    'clients' => Clients::all($uid),
    'form'    => $form,
    'errors'  => $errors,
    'palette' => Clients::PALETTE,
]);
