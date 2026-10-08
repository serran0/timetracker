<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use TimeTracker\Auth;

require_csrf();
Auth::logout();
session_start(); // fresh session just to carry the confirmation message
flash('success', 'You have been signed out.');
redirect('login.php');
