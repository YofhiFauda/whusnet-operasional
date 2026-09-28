<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu Task C-REQ = satu detail verifikasi biaya. Relasinya `hasOne`
 * (Task::creqDetail()), tapi dulu laporan yang dikirim ulang menambah baris
 * baru — CS memproses baris lama, baris baru nyangkut di antrean selamanya.
 * Sekarang TaskMaintenanceController pakai updateOrCreate; unique index ini
 * menjaganya dari jalur mana pun.
 *
 * Baris dobel yang terlanjur ada dirapikan dulu: yang dipertahankan baris
 * TERBARU per task (isi laporan terakhir teknisi), sisanya dihapus — tanpa
 * ini pembuatan unique index gagal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicateTaskIds = DB::table('task_creq_details')
            ->select('task_id')
            ->groupBy('task_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('task_id');

        foreach ($duplicateTaskIds as $taskId) {
            $keepId = DB::table('task_creq_details')->where('task_id', $taskId)->max('id');

            DB::table('task_creq_details')
                ->where('task_id', $taskId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('task_creq_details', function (Blueprint $table) {
            $table->unique('task_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_creq_details', function (Blueprint $table) {
            // FK task_id butuh index — di MySQL index unique ini yang dipakai
            // FK, jadi ganti dulu ke index biasa sebelum unique-nya dilepas.
            $table->index('task_id');
            $table->dropUnique(['task_id']);
        });
    }
};
