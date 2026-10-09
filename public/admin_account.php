<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Audit;
use TimeTracker\Auth;
use TimeTracker\Db;
use TimeTracker\I18n;
use TimeTracker\Repository\Users;
use TimeTracker\View;

$user = Auth::requireAdmin();
$uid = (int) $user['id'];
$errors = [];
$pwErrors = [];

if (is_post()) {
    require_csrf();
    if (input('op') === 'profile') {
        $username = trim(input('username'));
        $display = mb_substr(input('display_name'), 0, 120);
        $loc = input('locale');
        if ($username !== $user['username'] && ($e = Users::usernameError($username, $uid))) {
            $errors[] = $e;
        }
        if (!I18n::isValid($loc)) {
            $errors[] = t('Choose a valid language.');
        }
        if (!$errors) {
            // A display name that was just the old username (the default) follows the new one.
            $display = ($display === '' || $display === $user['username']) ? $username : $display;
            Db::run('UPDATE users SET username = ?, display_name = ?, locale = ? WHERE id = ?', [$username, $display, $loc, $uid]);
            if ($username !== $user['username']) {
                Audit::log('account.renamed', ['old' => $user['username'], 'new' => $username], ['id' => $uid, 'username' => $username]);
            } else {
                Audit::log('account.profile', [], $user);
            }
            I18n::setLocale($loc);
            set_lang_cookie($loc);
            flash('success', t('Settings saved.'));
            redirect('admin_account.php');
        }
        $user = array_merge($user, ['username' => $username, 'display_name' => $display, 'locale' => I18n::isValid($loc) ? $loc : $user['locale']]);
    } elseif (input('op') === 'password') {
        $new = to_str($_POST['new_password'] ?? '');
        if (!Auth::confirmPassword($user, to_str($_POST['current_password'] ?? ''))) {
            $pwErrors[] = t('Your current password is not correct.');
        }
        if ($e = Users::validatePassword($new)) {
            $pwErrors[] = $e;
        }
        if ($new !== to_str($_POST['new_password2'] ?? '')) {
            $pwErrors[] = t('The new passwords do not match.');
        }
        if (!$pwErrors) {
            Users::setPassword($uid, $new);
            Auth::rememberPassword($uid);
            session_regenerate_id(true);
            Audit::log('auth.password_changed', [], $user);
            flash('success', t('Password changed.'));
            redirect('admin_account.php');
        }
    }
}

View::render('admin_account', ['title' => t('My account'), 'active' => '', 'user' => $user, 'errors' => $errors, 'pwErrors' => $pwErrors]);
