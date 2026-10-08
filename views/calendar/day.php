<?php
// The day view is the week timeline restricted to a single row, followed by the day's reports.
echo TimeTracker\View::capture('calendar/week', get_defined_vars());
