<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ambang "roll sisa kecil" (TrackingType::ROLL) — properti JENIS kabel,
     * bukan per-roll individual, makanya nempel di `items` sejalan
     * `meter_per_roll`, BUKAN kolom baru di `inventory_rolls`. Beda axis dari
     * `inventory_balances.minimum_stock`/`maximum_stock` (itu ambang TOTAL
     * AGREGAT per pop+item+lot) — jangan disamain.
     *
     * Roll dengan `length_remaining` < ini (tapi masih > 0, status
     * AVAILABLE/RECEIVED/ISSUED/IN_USE) di-flag "Sisa Kecil" — TETAP tercatat
     * sebagai stok, TIDAK di-write-off otomatis (konsisten prinsip repo: gak
     * ada auto-write-off tanpa approval manusia, docs/warehouse/business-logic.md).
     * Nullable — opsional, gak wajib diisi kalau admin belum tau ambang yang
     * masuk akal buat jenis kabel itu.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('minimum_length', 10, 2)->nullable()->after('meter_per_roll');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('minimum_length');
        });
    }
};
