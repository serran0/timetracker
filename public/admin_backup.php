<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Backup;
use TimeTracker\Db;
use TimeTracker\View;

$user = Auth::requireAdmin();
$errors = [];

if (is_post()) {
    require_csrf();
    $op = input('op');

    if ($op === 'download') {
        $withAudit = !empty($_POST['audit']);
        $gzip = input('format') === 'gz' && function_exists('deflate_init');
        $name = 'timetracker-backup-' . date('Ymd-His') . ($gzip ? '.sql.gz' : '.sql');
        Audit::log('backup.create', ['format' => $gzip ? 'SQL.GZ' : 'SQL'], $user);
        session_write_close(); // do not hold the session while streaming
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($gzip ? 'application/gzip' : 'application/sql') . '; charset=binary');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
        $ctx = $gzip ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]) : null;
        $out = static function (string $chunk) use ($ctx): void {
            echo $ctx ? deflate_add($ctx, $chunk, ZLIB_NO_FLUSH) : $chunk;
            flush();
        };
        try {
            Backup::dump($out, $withAudit);
            if ($ctx) {
                echo deflate_add($ctx, '', ZLIB_FINISH);
            }
        } catch (Throwable $e) {
            error_log('Timetracker backup failed: ' . $e->getMessage());
            // The file is already on its way; it will lack the end marker, so a restore refuses it.
        }
        exit;
    }

    if ($op === 'restore') {
        $file = $_FILES['dump'] ?? null;
        if (!Auth::confirmPassword($user, to_str($_POST['password'] ?? ''))) {
            $errors[] = t('Your password is not correct.');
        }
        if (empty($_POST['confirm'])) {
            $errors[] = t('Tick the box to confirm that you want to replace all data.');
        }
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('The file is larger than this server accepts ({limit}). Load big backups with the mysql command line client instead.', ['limit' => (string) ini_get('upload_max_filesize')]),
                UPLOAD_ERR_NO_FILE => t('Choose a backup file.'),
                default => t('The upload failed. Try again.'),
            };
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = t('The upload failed. Try again.');
        }
        if (!$errors) {
            try {
                $info = Backup::inspect($file['tmp_name']);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
                Audit::log('backup.restore_failed', [], $user);
            }
        }
        if (!$errors) {
            // Safety copy of the current data first, when the server lets us write one.
            $safety = '';
            $dir = TT_ROOT . '/config';
            if (is_dir($dir) && is_writable($dir) && function_exists('gzopen')) {
                $safety = 'config/before-restore-' . date('Ymd-His') . '.sql.gz';
                $gz = @gzopen(TT_ROOT . '/' . $safety, 'wb6');
                if ($gz) {
                    try {
                        Backup::dump(static function (string $c) use ($gz): void { gzwrite($gz, $c); });
                    } catch (Throwable) {
                        $safety = '';
                    }
                    gzclose($gz);
                    @chmod(TT_ROOT . '/' . $safety, 0640);
                } else {
                    $safety = '';
                }
            }
            try {
                Backup::restore($file['tmp_name']);
                Audit::log('backup.restore', [], $user);
                Auth::logout(); // the restored data may not contain this account
                session_start();
                flash('success', t('The database was restored from the backup. Please sign in again.') . ($safety !== '' ? ' ' . t('A copy of the previous data was saved as {file}.', ['file' => $safety]) : ''));
                redirect('login.php');
            } catch (Throwable $e) {
                error_log('Timetracker restore failed: ' . $e->getMessage());
                try {
                    Audit::log('backup.restore_failed', [], $user);
                } catch (Throwable) {
                }
                $errors[] = t('The restore stopped part-way: {error}', ['error' => $e->getMessage()]) . ($safety !== '' ? ' ' . t('The previous data was saved as {file}; restore that file to go back.', ['file' => $safety]) : ' ' . t('Restore a backup again to repair the database.'));
            }
        }
    }
}

$stats = [];
foreach (Db::all('SELECT TABLE_NAME AS n, TABLE_ROWS AS r, DATA_LENGTH + INDEX_LENGTH AS b FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') as $r) {
    $stats[$r['n']] = ['rows' => (int) $r['r'], 'bytes' => (int) $r['b']];
}
View::render('admin_backup', [
    'title'  => t('Backup'),
    'active' => 'admin_backup',
    'user'   => $user,
    'tables' => array_intersect_key($stats, array_flip(Backup::tables(true))),
    'errors' => $errors,
    'gzip'   => function_exists('deflate_init'),
    'limits' => ['upload' => (string) ini_get('upload_max_filesize'), 'post' => (string) ini_get('post_max_size')],
]);
