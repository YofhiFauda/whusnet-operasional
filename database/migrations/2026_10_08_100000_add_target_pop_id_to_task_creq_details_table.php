<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori C-REQ `MIGRASI` (ADHOC-108) — pindah lokasi LINTAS POP, beda dari
 * `PINDAH_LOKASI` yang POP-nya tetap sama. Kolom ini nyimpen POP tujuan yang
 * dipilih teknisi di form; eksekusi pindah pop_id pelanggan sendiri lewat
 * `$customer->update(['pop_id' => ...])` biasa (TaskMaintenanceController),
 * supaya guard piutang & efek samping (kolektor dilepas, CID dihitung ulang,
 * dst) tetap satu sumber di `CustomerObserver::updating()` — bukan dobel di
 * sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_creq_details', function (Blueprint $table) {
            $table->foreignId('target_pop_id')->nullable()->after('category_custom_name')->constrained('pops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_creq_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_pop_id');
        });
    }
};
