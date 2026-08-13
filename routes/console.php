<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('check:countdown --minutes=60')->everyFiveMinutes();
// `fop:reset-cancelled-tasks` DIHAPUS 2026-08-13 (ADHOC-34). Ia menghidupkan
// kembali Task FOP yang dibatalkan jadi `in_progress` tiap 00:01 — menghapus
// keputusan manusia tanpa jejak di riwayat tiket, dengan status yang palsu
// (tak ada teknisi yang mengerjakan) dan tanggal lama. Pembatalan Task FOP
// bersifat FINAL; penundaan sehari lewat Pending atau ubah tanggal.
// Penjaga: FopTasksTest::test_cancelled_task_stays_cancelled_and_is_never_auto_revived().
Schedule::command('billing:generate-monthly-invoices')->monthlyOn(1, '01:00');
Schedule::command('notifications:prune-read')->dailyAt('00:30');
Schedule::command('fop-tasks:check-sla-breach')->everyThirtyMinutes();
