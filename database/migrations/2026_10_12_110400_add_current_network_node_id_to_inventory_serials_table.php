<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi SN di titik infrastruktur jaringan (ODC/ODP/splitter), beda dari
     * `current_pop_id` (lokasi kalau lagi di gudang) — konsisten dengan
     * konvensi "cuma satu yang relevan tergantung status" di `InventorySerial`.
     * Diisi lewat transaksi ledger tipe DEPLOY (Task 10), di-null-kan lagi
     * lewat RETRIEVE_INFRA.
     */
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->foreignId('current_network_node_id')->nullable()->after('current_pop_id')
                ->constrained('network_nodes')->nullOnDelete();
            $table->index('current_network_node_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_network_node_id');
        });
    }
};
