<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\I18n;
use TimeTracker\Repository\FreeDays;
use TimeTracker\Repository\Users;
use TimeTracker\View;

$user = Auth::require();
$uid = (int) $user['id'];
$errors = [];
$pwErrors = [];
$dayErrors = [];
$dayForm = ['from' => '', 'to' => '', 'name' => ''];

if (is_post()) {
    require_csrf();
    if (input('op') === 'profile') {
        $name = mb_substr(input('display_name'), 0, 120);
        $tz = input('timezone');
        $cur = strtoupper(input('currency'));
        $loc = input('locale');
        $holidays = !empty($_POST['show_holidays']) ? 1 : 0;
        $colorBy = input('default_color') === 'action' ? 'action' : 'client';
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
            // The username is fixed for regular users (only an administrator can change it).
            $display = $name !== '' ? $name : $user['username'];
            Db::run('UPDATE users SET display_name = ?, timezone = ?, currency = ?, locale = ?, show_holidays = ?, default_color = ? WHERE id = ?', [$display, $tz, $cur, $loc, $holidays, $colorBy, $uid]);
            Audit::log('account.settings');
            I18n::setLocale($loc); // the confirmation (and the next page) already use the new language
            set_lang_cookie($loc);
            flash('success', t('Settings saved.'));
            redirect('account.php');
        }
        $user = array_merge($user, ['display_name' => $name, 'timezone' => $tz, 'currency' => $cur, 'locale' => I18n::isValid($loc) ? $loc : $user['locale'], 'show_holidays' => $holidays, 'default_color' => $colorBy]);
    } elseif (input('op') === 'freeday_add') {
        $dayForm = ['from' => input('from'), 'to' => input('to'), 'name' => mb_substr(input('name'), 0, 120)];
        [$data, $dayErrors] = FreeDays::parse($dayForm);
        if (!$dayErrors) {
            FreeDays::add($uid, $data);
            Audit::log('freeday.add');
            flash('success', t('Day off added.'));
            redirect('account.php#free-days');
        }
    } elseif (input('op') === 'freeday_delete') {
        FreeDays::delete($uid, (int) input('id'));
        Audit::log('freeday.remove');
        flash('success', t('Day off removed.'));
        redirect('account.php#free-days');
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
            Users::setPassword($uid, $new);
            Audit::log('auth.password_changed');
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
    'dayErrors' => $dayErrors,
    'dayForm'   => $dayForm,
    'freeDays'  => FreeDays::all($uid),
    'timezones' => DateTimeZone::listIdentifiers(),
]);
