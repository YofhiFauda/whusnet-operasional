<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dukungan pemenuhan parsial Permintaan Stok (2026-09-07) — sebelumnya
     * `fulfill()` cuma biner (langsung FULFILLED), gak ada cara catat "baru
     * kekirim sebagian" per baris barang. `qty_fulfilled` numpuk (bukan snapshot
     * qty transfer terakhir) — status `StockRequestItem` "berapa yang UDAH
     * dikirim sejauh ini", direkonsiliasi manual oleh admin Pusat setelah
     * Transfer sungguhan dibikin terpisah lewat `WarehouseTransferController`
     * (lihat docblock `StockRequestService` — tetap bukan sumber ledger).
     */
    public function up(): void
    {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->decimal('qty_fulfilled', 12, 2)->default(0)->after('qty_requested');
        });
    }

    public function down(): void
    {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->dropColumn('qty_fulfilled');
        });
    }
};
