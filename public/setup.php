<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Config;
use TimeTracker\Installer;
use TimeTracker\Repository\Users;
use TimeTracker\View;

header('Cache-Control: no-store');

if (Config::isInstalled()) {
    View::render('setup_done', ['title' => t('Setup'), 'bodyClass' => 'auth']);
    exit;
}

$values = [
    'db_host'  => 'localhost',
    'db_port'  => '3306',
    'db_name'  => 'timetracker',
    'db_user'  => '',
    'db_pass'  => '',
    'username' => '',
    'display_name' => '',
    'password' => '',
    'password2' => '',
    'currency' => 'EUR',
    'timezone' => date_default_timezone_get(),
    'locale'   => TimeTracker\I18n::locale(),
];
// The language picked in the form applies to this very response (checks and messages included).
if (is_post() && isset($_POST['locale']) && is_string($_POST['locale']) && TimeTracker\I18n::isValid($_POST['locale'])) {
    TimeTracker\I18n::setLocale($_POST['locale']);
    set_lang_cookie($_POST['locale']);
}
$values['locale'] = TimeTracker\I18n::locale();
$checks = Installer::environmentChecks();
$dbChecks = [];
$errors = [];
$ran = false;

if (is_post()) {
    require_csrf();
    foreach (array_keys($values) as $k) {
        $values[$k] = isset($_POST[$k]) && is_string($_POST[$k]) ? ($k === 'db_pass' || $k === 'password' || $k === 'password2' ? $_POST[$k] : trim($_POST[$k])) : $values[$k];
    }
    $mode = ($_POST['mode'] ?? 'check') === 'install' ? 'install' : 'check';
    $ran = true;

    $db = [
        'host' => $values['db_host'],
        'port' => (int) $values['db_port'] ?: 3306,
        'name' => $values['db_name'],
        'user' => $values['db_user'],
        'pass' => $values['db_pass'],
    ];

    if (!Installer::hasFailure($checks)) {
        $dbResult = Installer::databaseChecks($db);
        $dbChecks = $dbResult['checks'];
    }

    if ($mode === 'install') {
        if ($e = Users::validateUsername($values['username'])) {
            $errors[] = $e;
        }
        if ($e = Users::validatePassword($values['password'])) {
            $errors[] = $e;
        }
        if ($values['password'] !== $values['password2']) {
            $errors[] = t('The two passwords do not match.');
        }
        if (!in_array($values['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors[] = t('Choose a valid timezone.');
        }
        if (!TimeTracker\I18n::isValid($values['locale'])) {
            $values['locale'] = TimeTracker\I18n::DEFAULT;
        }
        if (!preg_match('/^[A-Za-z]{1,8}$/', $values['currency'])) {
            $errors[] = t('Currency should be a short code such as EUR, SEK or USD.');
        }

        if (!$errors && !Installer::hasFailure($checks) && !Installer::hasFailure($dbChecks)) {
            try {
                Installer::install($db, [
                    'username'     => $values['username'],
                    'password'     => $values['password'],
                    'display_name' => $values['display_name'],
                    'currency'     => strtoupper($values['currency']),
                    'locale'       => $values['locale'],
                ], $values['timezone']);
                flash('success', t('Timetracker is installed. Sign in with the administrator account you just created, then create the users who will report time in the admin panel.'));
                redirect('login.php');
            } catch (Throwable $e) {
                $errors[] = t('Installation failed: {error}', ['error' => $e->getMessage()]);
            }
        } elseif (!$errors) {
            $errors[] = t('Installation was not started because some checks failed. Fix the items marked below and try again.');
        }
    }
}

View::render('setup', [
    'title'     => t('Setup'),
    'bodyClass' => 'auth',
    'values'    => $values,
    'checks'    => $checks,
    'dbChecks'  => $dbChecks,
    'errors'    => $errors,
    'ran'       => $ran,
    'timezones' => DateTimeZone::listIdentifiers(),
]);
