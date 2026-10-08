<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;

require_csrf();
if ($who = Auth::user()) {
    TimeTracker\Audit::log('auth.logout', [], $who);
}
// // manual sign-outs only; an idle timeout is not logged
set_lang_cookie(\TimeTracker\I18n::locale()); // the login page keeps the language of the user who just left
Auth::logout();
session_start(); // fresh session just to carry the confirmation message
flash('success', t('You have been signed out.'));
redirect('login.php');
