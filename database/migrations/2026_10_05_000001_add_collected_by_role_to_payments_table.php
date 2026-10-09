<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot sumber pencatat (kolektor/teknisi) untuk badge & laporan.
     *
     * Backfill HANYA untuk baris yang `collected_by` sudah terisi — itu
     * pasti batch kolektor (satu-satunya jalur yang mengisinya). Baris lain
     * dibiarkan null (admin/Tagihan), jangan ditebak.
     *
     * docs/plan/kolektor/rancangan-pembayaran-teknisi.md §6.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('collected_by_role', 20)->nullable()->after('collected_by');
        });

        DB::table('payments')
            ->whereNotNull('collected_by')
            ->update(['collected_by_role' => 'kolektor']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('collected_by_role');
        });
    }
};
