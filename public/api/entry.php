<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Repository\Entries;

/** JSON API used by the calendar dialogs: create, update and delete time reports. */

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_post()) {
    json_out(['ok' => false, 'errors' => [t('POST required.')]], 405);
}
$user = Auth::user();
if (!$user) {
    json_out(['ok' => false, 'errors' => [t('Your session has expired. Please sign in again.')]], 401);
}
if (!csrf_valid()) {
    json_out(['ok' => false, 'errors' => [t('Invalid security token. Reload the page and try again.')]], 419);
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_out(['ok' => false, 'errors' => [t('Invalid request.')]], 400);
}

$uid = (int) $user['id'];
$op = (string) ($body['op'] ?? 'save');
$id = (int) ($body['id'] ?? 0) ?: null;
$existing = $id ? Entries::find($uid, $id) : null;
if ($id && !$existing) {
    json_out(['ok' => false, 'errors' => [t('That time report no longer exists.')]], 404);
}

if ($op === 'delete') {
    if (!$id) {
        json_out(['ok' => false, 'errors' => [t('Nothing to delete.')]], 400);
    }
    Entries::delete($uid, $id);
    json_out(['ok' => true]);
}

if ($op === 'save_many') {
    // One client/action/description, several days: validate everything first, then insert all-or-nothing.
    $rows = $body['entries'] ?? null;
    if (!is_array($rows) || !$rows || count($rows) > 62) {
        json_out(['ok' => false, 'errors' => [t('Add between 1 and 62 days.')]], 400);
    }
    $all = [];
    $errors = [];
    foreach (array_values($rows) as $i => $row) {
        if (!is_array($row)) {
            $errors[] = t('Row {n} is not valid.', ['n' => $i + 1]);
            continue;
        }
        $in = [
            'date'        => $row['date'] ?? '',
            'start'       => $row['start'] ?? '',
            'end'         => $row['end'] ?? '',
            'client_id'   => $body['client_id'] ?? 0,
            'action_id'   => $body['action_id'] ?? 0,
            'description' => $body['description'] ?? '',
        ];
        if (array_key_exists('break', $row)) {
            $in['break'] = $row['break'];
        }
        [$data, $rowErrors] = Entries::validate($uid, $in, null, $user);
        foreach ($rowErrors as $err) {
            $label = (string) ($row['date'] ?? '') !== '' ? (string) $row['date'] : t('Row {n}', ['n' => $i + 1]);
            $errors[] = count($rows) > 1 ? $label . ': ' . $err : $err;
        }
        $all[] = $data;
    }
    if ($errors) {
        json_out(['ok' => false, 'errors' => array_values(array_unique($errors))], 422);
    }
    $pdo = TimeTracker\Db::pdo();
    $pdo->beginTransaction();
    try {
        $ids = array_map(static fn(array $d): int => Entries::create($uid, $d), $all);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    json_out(['ok' => true, 'ids' => $ids]);
}

if ($op !== 'save') {
    json_out(['ok' => false, 'errors' => [t('Unknown operation.')]], 400);
}

[$data, $errors] = Entries::validate($uid, $body, $existing, $user);
if ($errors) {
    json_out(['ok' => false, 'errors' => $errors], 422);
}
if ($id) {
    Entries::update($uid, $id, $data);
} else {
    $id = Entries::create($uid, $data);
}
json_out(['ok' => true, 'id' => $id]);
