<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\I18n;
use TimeTracker\Settings;
use TimeTracker\Smtp;
use TimeTracker\View;

$user = Auth::requireAdmin();
$errors = ['general' => [], 'security' => [], 'mail' => []];
$values = [];
foreach (array_keys(Settings::DEFAULTS) as $k) {
    $values[$k] = Settings::get($k);
}
$intIn = static fn(string $v, int $min, int $max): ?int => (preg_match('/^\d{1,6}$/', trim($v)) && (int) $v >= $min && (int) $v <= $max) ? (int) $v : null;

if (is_post()) {
    require_csrf();
    $op = input('op');

    if ($op === 'general') {
        $values['default_timezone'] = input('default_timezone');
        $values['default_currency'] = strtoupper(input('default_currency'));
        $values['default_locale'] = input('default_locale');
        if ($values['default_timezone'] !== '' && !in_array($values['default_timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors['general'][] = t('Choose a valid timezone.');
        }
        if (!preg_match('/^[A-Z]{1,8}$/', $values['default_currency'])) {
            $errors['general'][] = t('Currency should be a short code such as EUR, SEK or USD.');
        }
        if (!I18n::isValid($values['default_locale'])) {
            $errors['general'][] = t('Choose a valid language.');
        }
        if (!$errors['general']) {
            Settings::setMany(array_intersect_key($values, array_flip(['default_timezone', 'default_currency', 'default_locale'])));
            Audit::log('settings.general', [], $user);
            flash('success', t('Settings saved.'));
            redirect('admin_settings.php');
        }
    } elseif ($op === 'security') {
        $rules = [
            'login.max_per_user' => [t('Failed sign-ins per username'), 1, 50],
            'login.max_per_ip'   => [t('Failed sign-ins per IP address'), 1, 1000],
            'login.lock_minutes' => [t('Lock-out time (minutes)'), 1, 1440],
        ];
        foreach ($rules as $k => [$label, $min, $max]) {
            $values[$k] = trim(input(str_replace('.', '_', $k)));
            if ($intIn($values[$k], $min, $max) === null) {
                $errors['security'][] = t('"{field}" must be a whole number between {min} and {max}.', ['field' => $label, 'min' => $min, 'max' => $max]);
            }
        }
        if (!$errors['security']) {
            Settings::setMany(array_intersect_key($values, array_flip(['login.max_per_user', 'login.max_per_ip', 'login.lock_minutes'])));
            Audit::log('settings.security', [], $user);
            flash('success', t('Settings saved.'));
            redirect('admin_settings.php');
        }
    } elseif ($op === 'mail') {
        $values['mail.enabled'] = !empty($_POST['mail_enabled']) ? '1' : '0';
        $values['mail.host'] = trim(input('mail_host'));
        $values['mail.port'] = trim(input('mail_port'));
        $values['mail.encryption'] = in_array(input('mail_encryption'), ['tls', 'ssl', 'none'], true) ? input('mail_encryption') : 'tls';
        $values['mail.username'] = trim(input('mail_username'));
        $newPass = (string) ($_POST['mail_password'] ?? '');
        if ($newPass !== '' || !empty($_POST['mail_clear_password'])) {
            $values['mail.password'] = !empty($_POST['mail_clear_password']) ? '' : $newPass; // blank keeps the saved one
        }
        $values['mail.from_email'] = trim(input('mail_from_email'));
        $values['mail.from_name'] = mb_substr(trim(input('mail_from_name')), 0, 100);
        $raw = (string) ($_POST['mail_recipients'] ?? '');
        $tokens = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = Settings::recipients($raw);
        foreach ($tokens as $tok) {
            if (!filter_var($tok, FILTER_VALIDATE_EMAIL)) {
                $errors['mail'][] = t('"{address}" is not a valid email address.', ['address' => $tok]);
            }
        }
        $values['mail.recipients'] = implode("\n", $valid);
        if ($values['mail.host'] !== '' && !preg_match('/^[A-Za-z0-9.\-]{1,253}$|^\[[0-9a-fA-F:.]+\]$/', $values['mail.host'])) {
            $errors['mail'][] = t('The mail server name is not valid.');
        }
        if ($intIn($values['mail.port'], 1, 65535) === null) {
            $errors['mail'][] = t('"{field}" must be a whole number between {min} and {max}.', ['field' => t('Port'), 'min' => 1, 'max' => 65535]);
        }
        if ($values['mail.from_email'] !== '' && !filter_var($values['mail.from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['mail'][] = t('"{address}" is not a valid email address.', ['address' => $values['mail.from_email']]);
        }
        if ($values['mail.enabled'] === '1' && ($values['mail.host'] === '' || $values['mail.from_email'] === '')) {
            $errors['mail'][] = t('To enable notifications, enter the mail server and the sender address.');
        }
        if (!$errors['mail']) {
            Settings::setMany(array_intersect_key($values, array_flip(['mail.enabled', 'mail.host', 'mail.port', 'mail.encryption', 'mail.username', 'mail.password', 'mail.from_email', 'mail.from_name', 'mail.recipients'])));
            Audit::log('settings.mail', [], $user);
            flash('success', t('Settings saved.'));
            redirect('admin_settings.php#mail');
        }
    } elseif ($op === 'mail_test') {
        $smtp = Smtp::fromSettings();
        $to = Settings::recipients();
        if (!$smtp) {
            flash('error', t('Save the mail server and sender address first.'));
        } elseif (!$to) {
            flash('error', t('Add at least one recipient address first.'));
        } else {
            $r = $smtp->send($to, t('Timetracker test email'), t('This is a test email from Timetracker {version}. If you can read this, the mail settings work.', ['version' => TT_VERSION]));
            Audit::log($r['ok'] ? 'mail.test' : 'mail.test_failed', [], $user);
            flash($r['ok'] ? 'success' : 'error', $r['ok'] ? t('Test email sent to {count} recipient(s).', ['count' => count($to)]) : t('The test email could not be sent: {error}', ['error' => (string) $r['error']]));
        }
        redirect('admin_settings.php#mail');
    }
}

View::render('admin_settings', [
    'title'     => t('Settings'),
    'active'    => 'admin_settings',
    'user'      => $user,
    'v'         => $values,
    'errors'    => $errors,
    'timezones' => DateTimeZone::listIdentifiers(),
]);
