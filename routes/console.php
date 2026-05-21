<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fire every minute; the command itself filters to the [9, 11] minute
// pre-shift-end window and dedupes within 30 minutes per recipient.
Schedule::command('work-orders:notify-shift-ending')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
