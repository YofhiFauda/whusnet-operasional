<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Lapor Nanti" naik kelas jadi status sendiri (`lapor_nanti`), bukan lagi
 * `pending` + flag `tasks.report_deferred` (lihat TaskStatus::LAPOR_NANTI
 * kenapa). Data lama dikonversi dulu, baru kolom flag-nya dipensiunkan —
 * dibiarkan hidup cuma bikin dua sumber kebenaran yang gampang menyimpang.
 *
 * `fop_tasks.status` share vocab yang sama dengan `tasks.status` (unifikasi
 * 2026-07-20) dan biasanya di-copy oleh TaskObserver, jadi FopTask yang
 * nyambung ke task Lapor Nanti ikut dikonversi di sini — observer gak jalan
 * di migration (query builder, bukan Eloquent).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deferredTaskIds = DB::table('tasks')
            ->where('status', 'pending')
            ->where('report_deferred', true)
            ->pluck('id');

        foreach ($deferredTaskIds->chunk(500) as $chunk) {
            DB::table('tasks')->whereIn('id', $chunk)->update(['status' => 'lapor_nanti']);

            DB::table('fop_tasks')
                ->whereIn('task_id', $chunk)
                ->where('status', 'pending')
                ->update(['status' => 'lapor_nanti']);
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('report_deferred');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('report_deferred')->default(false)->after('pending_reason');
        });

        DB::table('tasks')
            ->where('status', 'lapor_nanti')
            ->update(['status' => 'pending', 'report_deferred' => true]);

        DB::table('fop_tasks')
            ->where('status', 'lapor_nanti')
            ->update(['status' => 'pending']);
    }
};
