<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Atlantic auto-instant polling - runs every minute
Schedule::command('atlantic:poll-instant')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Order Kuota & QiosPay payment polling - runs every minute
// This replaces the need for manual daemon
Schedule::command('payment:poll-daemon --interval=5')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Cleanup old transactions (> 30 days) - runs daily at 2 AM
Schedule::command('transactions:cleanup')
    ->dailyAt('02:00')
    ->runInBackground();
