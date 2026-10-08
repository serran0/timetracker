<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\I18n;
use TimeTracker\Repository\Users;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$pwErrors = [];

if (is_post()) {
    require_csrf();
    if (input('op') === 'profile') {
        $name = mb_substr(input('display_name'), 0, 120);
        $tz = input('timezone');
        $cur = strtoupper(input('currency'));
        $loc = input('locale');
        if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            $errors[] = t('Choose a valid timezone.');
        }
        if (!I18n::isValid($loc)) {
            $errors[] = t('Choose a valid language.');
        }
        if (!preg_match('/^[A-Z]{1,8}$/', $cur)) {
            $errors[] = t('Currency should be a short code such as EUR, SEK or USD.');
        }
        if (!$errors) {
            Db::run('UPDATE users SET display_name = ?, timezone = ?, currency = ?, locale = ? WHERE id = ?', [$name !== '' ? $name : $user['username'], $tz, $cur, $loc, $uid]);
            I18n::setLocale($loc); // the confirmation (and the next page) already use the new language
            set_lang_cookie($loc);
            flash('success', t('Settings saved.'));
            redirect('account.php');
        }
        $user = array_merge($user, ['display_name' => $name, 'timezone' => $tz, 'currency' => $cur, 'locale' => I18n::isValid($loc) ? $loc : $user['locale']]);
    } elseif (input('op') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            $pwErrors[] = t('Your current password is not correct.');
        }
        if ($e = Users::validatePassword($new)) {
            $pwErrors[] = $e;
        }
        if ($new !== (string) ($_POST['new_password2'] ?? '')) {
            $pwErrors[] = t('The new passwords do not match.');
        }
        if (!$pwErrors) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($new), $uid]);
            session_regenerate_id(true);
            flash('success', t('Password changed.'));
            redirect('account.php');
        }
    }
}

View::render('account', [
    'title'     => t('Account'),
    'user'      => $user,
    'errors'    => $errors,
    'pwErrors'  => $pwErrors,
    'timezones' => DateTimeZone::listIdentifiers(),
]);
