<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan Gudang menghitung saldo per tanggal (WarehouseStockAsOfService) untuk
 * tiap pasangan item × POP — ratusan query yang semuanya menyaring
 * `item_id` + kolom lokasi/unit + `created_at`. Tabel ledger hanya punya index
 * tunggal (`item_id`, `to_pop_id`, ...), sehingga MySQL memindai semua baris
 * item lalu membuat temporary table untuk GROUP BY. Index gabungan ini
 * melayani filter sekaligus pengelompokannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['item_id', 'serial_id', 'created_at'], 'inv_tx_item_serial_time_idx');
            $table->index(['item_id', 'roll_id', 'created_at'], 'inv_tx_item_roll_time_idx');
            $table->index(['item_id', 'to_pop_id', 'created_at'], 'inv_tx_item_topop_time_idx');
            $table->index(['item_id', 'from_pop_id', 'created_at'], 'inv_tx_item_frompop_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inv_tx_item_serial_time_idx');
            $table->dropIndex('inv_tx_item_roll_time_idx');
            $table->dropIndex('inv_tx_item_topop_time_idx');
            $table->dropIndex('inv_tx_item_frompop_time_idx');
        });
    }
};
