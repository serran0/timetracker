<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\Export\ReportBuilder;

/** JSON API: remembers the export format options as soon as they are changed on the export page. */

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
if (!empty($user['must_change_password'])) {
    json_out(['ok' => false, 'errors' => [t('Choose a new password first.')]], 403);
}
if ($user['is_admin']) {
    json_out(['ok' => false, 'errors' => [t('Administrator access required.')]], 403);
}
if (!csrf_valid()) {
    json_out(['ok' => false, 'errors' => [t('Invalid security token. Reload the page and try again.')]], 419);
}
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_out(['ok' => false, 'errors' => [t('Invalid request.')]], 400);
}

// Same sanitising as the export page itself: unknown values fall back to the defaults.
$opts = ReportBuilder::options([
    'submitted' => '1',
    'format'    => $body['format'] ?? '',
    'cols'      => $body['cols'] ?? [],
    'duration'  => $body['duration'] ?? '',
    'delimiter' => $body['delimiter'] ?? '',
    'decimal'   => $body['decimal'] ?? '',
    'totals'    => !empty($body['totals']) ? '1' : '',
    'vat'       => !empty($body['vat']) ? '1' : '',
]);
Db::run('UPDATE users SET export_prefs = ? WHERE id = ?', [json_encode(ReportBuilder::storable($opts), JSON_THROW_ON_ERROR), (int) $user['id']]);
json_out(['ok' => true]);
