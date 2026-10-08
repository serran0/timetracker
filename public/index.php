<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

redirect((\TimeTracker\Auth::user()['is_admin'] ?? false) ? 'admin.php' : 'calendar.php');
