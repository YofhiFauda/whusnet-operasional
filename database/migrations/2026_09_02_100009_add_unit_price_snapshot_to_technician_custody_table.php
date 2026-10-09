<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ketahuan pas nulis `InventoryService::consumeFromCustody()` (Fase
     * Service, ADHOC-54): harga-saat-pakai (§3.5 rancangan-ui.md) harus
     * disalin ke `task_materials.unit_price_snapshot` PER LOT yang dipotong
     * FIFO — dan sumbernya paling benar adalah harga yang berlaku PAS
     * barang itu di-ISSUE ke custody teknisi, bukan di-query ulang ke ledger
     * tiap kali ada laporan pemakaian (query ulang = harga bisa beda kalau
     * ada ISSUE lain di antaranya).
     *
     * Jadi harga di-snapshot SEKALI di sini, pas baris custody dibuat oleh
     * `InventoryIssueService::issue()` — bukan dihitung ulang tiap konsumsi.
     * Nullable — custody lama (kalau ada, sebelum migration ini) atau item
     * yang gak pernah punya histori harga di ledger tetap valid tanpa harga.
     */
    public function up(): void
    {
        Schema::table('technician_custody', function (Blueprint $table) {
            $table->decimal('unit_price_snapshot', 12, 2)->nullable()->after('qty_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('technician_custody', function (Blueprint $table) {
            $table->dropColumn('unit_price_snapshot');
        });
    }
};
