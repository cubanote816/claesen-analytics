<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the Watchdog Report to run every Monday at 8:00 AM
Schedule::command('analytics:send-watchdog-report')->weeklyOn(1, '08:00');
