<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2 Gudang, Prioritas 1 (kontrol-anti-manipulasi.md §2) — bukti
     * fisik wajib buat klaim kerugian. Nullable karena bukan SEMUA baris
     * ledger butuh bukti (RECEIVE/TRANSFER/ISSUE normal gak ada klaim
     * kerugian) — guard "wajib diisi" ditegakkan di Service
     * (`InventoryAdjustmentService::adjustSerialStatus()`/`adjustCustody()`)
     * cuma buat transisi LOST/DAMAGED/SCRAPPED, bukan di sini (DB-level
     * NOT NULL gak bisa kondisional per-type/per-reason).
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('evidence_file_path', 255)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('evidence_file_path');
        });
    }
};
