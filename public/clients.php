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
    $form = $row ?? ['id' => null, 'name' => '', 'reference' => '', 'color' => Clients::nextColor($uid), 'hourly_rate' => null, 'vat_percent' => 0, 'notes' => ''];
}

// Sorting of the client table; the choice is remembered for the session so it survives edits and archiving.
$sortKeys = ['name', 'rate', 'actions', 'reports'];
$remembered = $_SESSION['clients_sort'] ?? ['name', 'asc'];
$sort = in_array($_GET['sort'] ?? '', $sortKeys, true) ? $_GET['sort'] : $remembered[0];
$dir = isset($_GET['sort']) ? (($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc') : $remembered[1];
$_SESSION['clients_sort'] = [$sort, $dir];
$clients = Clients::all($uid);
usort($clients, static function (array $a, array $b) use ($sort, $dir): int {
    if ($a['is_archived'] !== $b['is_archived']) {
        return $a['is_archived'] <=> $b['is_archived']; // archived clients always last
    }
    $sign = $dir === 'desc' ? -1 : 1;
    $byName = strnatcasecmp((string) $a['name'], (string) $b['name']);
    if ($sort === 'rate') {
        if (($a['hourly_rate'] === null) !== ($b['hourly_rate'] === null)) {
            return $a['hourly_rate'] === null ? 1 : -1; // clients without a rate always last
        }
        return $sign * ((float) $a['hourly_rate'] <=> (float) $b['hourly_rate']) ?: $byName;
    }
    if ($sort === 'actions' || $sort === 'reports') {
        $k = $sort === 'actions' ? 'action_count' : 'entry_count';
        return $sign * ((int) $a[$k] <=> (int) $b[$k]) ?: $byName;
    }
    return $sign * $byName;
});

View::render('clients', [
    'title'   => t('Clients'),
    'active'  => 'clients',
    'user'    => $user,
    'clients' => $clients,
    'sort'    => $sort,
    'dir'     => $dir,
    'form'    => $form,
    'errors'  => $errors,
    'palette' => Clients::PALETTE,
]);
