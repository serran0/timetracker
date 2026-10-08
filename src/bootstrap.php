<?php
declare(strict_types=1);

/**
 * Application bootstrap: autoloading, error handling, security headers, session.
 * Every public page starts with: require __DIR__ . '/../src/bootstrap.php';
 */

define('TT_ROOT', dirname(__DIR__));
const TT_VERSION = '0.2.8';
/** Database schema number; bump together with a new step in src/Migrator.php. */
const TT_SCHEMA = 5;

require_once __DIR__ . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'TimeTracker\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use TimeTracker\Config;

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('Timetracker requires PHP 8.2 or newer (PHP 8.5 recommended). This server runs ' . PHP_VERSION . '.');
}

mb_internal_encoding('UTF-8');
date_default_timezone_set(Config::get('app.timezone', 'UTC'));

set_exception_handler(static function (Throwable $e): void {
    error_log('Timetracker: ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Config::get('app.debug', false)) {
        echo '<pre style="padding:1rem;white-space:pre-wrap">' . e((string) $e) . '</pre>';
    } else {
        echo t('Something went wrong. Check the server error log for details.');
    }
    exit;
});

// Security headers (no inline scripts are used anywhere, so script-src can stay strict).
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
    . "script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=15552000');
}

// Session
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('tt_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => is_https(),
]);
session_start();

// Language: signed-in users get their own setting (applied in Auth::user()); visitors get the cookie or browser language.
\TimeTracker\I18n::setLocale(\TimeTracker\I18n::detect());
if (empty($_SESSION['uid']) && isset($_GET['lang']) && is_string($_GET['lang']) && \TimeTracker\I18n::isValid($_GET['lang'])) {
    \TimeTracker\I18n::setLocale($_GET['lang']);
    set_lang_cookie($_GET['lang']);
}

// Not installed yet? Everything except the setup page goes to the installer.
if (!Config::isInstalled() && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
    redirect('setup.php');
}

// Bring an existing database up to the schema this code expects (no-op when already current).
if (Config::isInstalled() && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
    try {
        \TimeTracker\Migrator::run();
    } catch (Throwable $e) {
        error_log('Timetracker migration failed: ' . $e->getMessage());
        http_response_code(500);
        exit(e(t('Timetracker could not upgrade the database: {error} (the database user needs ALTER privileges).', ['error' => $e->getMessage()])));
    }
}
