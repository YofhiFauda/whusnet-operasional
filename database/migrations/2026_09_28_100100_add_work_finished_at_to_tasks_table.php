<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Titik waktu kerja lapangan selesai, terpisah dari `completed_at` (waktu
 * laporan dikirim). Keduanya sama untuk task yang langsung dilaporkan, beda
 * untuk task Lapor Nanti — jeda menunggu laporan BUKAN waktu kerja
 * (keputusan user 2026-09-28: SLA = waktu kerja lapangan, konsisten dengan
 * task_reports). Dipakai Task::slaReferenceTime()/actualDurationMinutes().
 *
 * Backfill task yang sedang Lapor Nanti: pakai task_reports.pending_at
 * (ditulis TaskObserver saat siklus kerja ditutup), fallback updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('work_finished_at')->nullable()->after('started_at');
        });

        DB::table('tasks')
            ->where('status', 'lapor_nanti')
            ->orderBy('id')
            ->each(function (object $task) {
                $pendingAt = DB::table('task_reports')->where('task_id', $task->id)->value('pending_at');

                DB::table('tasks')->where('id', $task->id)->update([
                    'work_finished_at' => $pendingAt ?? $task->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('work_finished_at');
        });
    }
};
