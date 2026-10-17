<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan perangkat tambahan ke unit fisik di stok Gudang (InventorySerial).
     * Nullable karena baris lama diisi free text dan tidak punya unit stok.
     * nullOnDelete: menghapus unit dari stok tidak boleh ikut menghapus riwayat
     * perangkat yang sudah terpasang di pelanggan.
     */
    public function up(): void
    {
        Schema::table('customer_additional_devices', function (Blueprint $table) {
            $table->foreignId('inventory_serial_id')->nullable()->constrained('inventory_serials')->nullOnDelete();
            $table->string('status')->default('terpasang');
            $table->timestamp('retrieved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_additional_devices', function (Blueprint $table) {
            $table->dropForeign(['inventory_serial_id']);
            $table->dropColumn(['inventory_serial_id', 'status', 'retrieved_at']);
        });
    }
};
