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
    json_out(['ok' => false, 'errors' => ['POST required.']], 405);
}
$user = Auth::user();
if (!$user) {
    json_out(['ok' => false, 'errors' => ['Your session has expired. Please sign in again.']], 401);
}
if (!csrf_valid()) {
    json_out(['ok' => false, 'errors' => ['Invalid security token. Reload the page and try again.']], 419);
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_out(['ok' => false, 'errors' => ['Invalid request.']], 400);
}

$uid = (int) $user['id'];
$op = (string) ($body['op'] ?? 'save');
$id = (int) ($body['id'] ?? 0) ?: null;
$existing = $id ? Entries::find($uid, $id) : null;
if ($id && !$existing) {
    json_out(['ok' => false, 'errors' => ['That time report no longer exists.']], 404);
}

if ($op === 'delete') {
    if (!$id) {
        json_out(['ok' => false, 'errors' => ['Nothing to delete.']], 400);
    }
    Entries::delete($uid, $id);
    json_out(['ok' => true]);
}

if ($op !== 'save') {
    json_out(['ok' => false, 'errors' => ['Unknown operation.']], 400);
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
