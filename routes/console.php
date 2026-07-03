<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('check:countdown --minutes=60')->everyFiveMinutes();
\Illuminate\Support\Facades\Schedule::command('fop:reset-cancelled-tasks')->dailyAt('00:01');
