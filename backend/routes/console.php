<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programed command to check deviations monthly on the 4th at 5:00 am
Schedule::command('deviations:detect')
    ->monthlyOn(4, '05:00')
    ->withoutOverlapping()
    ->runInBackground();
