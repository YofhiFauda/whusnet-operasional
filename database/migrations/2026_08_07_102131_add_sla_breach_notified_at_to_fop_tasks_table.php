<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fop_tasks.sla_breach_notified_at` — dedup flag buat scheduled command SLA
 * breach (`fop-tasks:check-sla-breach`, docs/plan/analisa-status-implementasi-
 * notifikasi.md §8). Tanpa ini, command yang jalan berkala bakal notif ulang
 * FopTask yang sama tiap kali dijadwalkan jalan — bukan sekali pas breach
 * pertama terjadi. Direset ke null tiap kali task di-reschedule/reassign
 * (TaskService::syncToFopTask() & sejenisnya via kolom ini, bukan dihitung
 * ulang dari nol) — kalau SLA-nya berubah gara-gara reschedule, layak
 * kena-notif lagi kalau breach lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->timestamp('sla_breach_notified_at')->nullable()->after('handling_sla_hours');
        });
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropColumn('sla_breach_notified_at');
        });
    }
};
