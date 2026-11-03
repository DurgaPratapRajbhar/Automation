<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The agents ask the owner each day (times in the business's time zone); nothing is sent before a Yes
Schedule::command('agents:propose-reminders')->dailyAt('10:00')->timezone(config('promises.timezone'));
Schedule::command('agents:propose-booking-followups')->hourly();
Schedule::command('agents:propose-reports')->dailyAt('20:00')->timezone(config('promises.timezone'));
