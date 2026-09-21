<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot status tujuan ADJUSTMENT (LOST/DAMAGED/SCRAPPED/QUARANTINE)
     * PADA SAAT baris ledger ditulis — beda dari baca `serial->status`/
     * `roll->status` SAAT INI, yang gak akurat historis kalau unit itu
     * di-adjust lagi belakangan (append-only cuma jamin baris gak dihapus,
     * bukan jamin baris itu masih mencerminkan status yang benar). General
     * buat SEMUA row (SERIALIZED & ROLL), NULL buat row lama/tipe lain.
     * Lihat docs/plan/warehouse/analisa-gap-kondisi-barang.md rancangan
     * poin 7.
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('resulting_status', 20)->nullable()->after('evidence_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('resulting_status');
        });
    }
};
