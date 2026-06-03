<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use Illuminate\Support\Facades\Schedule;

// Runs every minute to fire scheduled notifications and alarms
Schedule::command('app:check-notifications')->everyMinute();

// Already exists — keep this too
Schedule::command('app:recalculate-streaks')->dailyAt('00:05');


