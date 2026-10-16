<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled checks. On the server, run the Laravel scheduler every minute:
|   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
| On Windows/XAMPP use Task Scheduler, or run `php artisan schedule:work` while testing.
*/
Schedule::command('pmas:check-overdue')->everyFifteenMinutes()->withoutOverlapping();
