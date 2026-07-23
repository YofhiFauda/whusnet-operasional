<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('check:countdown --minutes=60')->everyFiveMinutes();
Schedule::command('fop:reset-cancelled-tasks')->dailyAt('00:01');
Schedule::command('billing:generate-monthly-invoices')->monthlyOn(1, '01:00');
