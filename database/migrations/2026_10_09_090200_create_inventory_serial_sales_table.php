<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penjualan unit SN dari gudang ke pelanggan (Beli dari Gudang). Cuma dibuat
     * untuk `acquisition_source=beli_gudang`, jadi tabel ini sumber tunggal buat
     * breakdown tagihan dan Laporan Barang Terjual.
     *
     * `fop_task_id` nullable cuma sebagai jaring pengaman skema — dalam praktik
     * SEMUA jalur penjual (PSB lewat `storeSpeedtest()`, MTN/C-REQ lewat
     * `TaskMaintenanceController::store()`) selalu mengisinya dengan FopTask
     * pekerjaan itu, termasuk PSB. Jangan pakai `fop_task_id IS NULL` untuk
     * membedakan PSB dari C-REQ/MTN.
     * Semua FK `restrictOnDelete` karena ini catatan keuangan — jangan sampai
     * menghapus SN/pelanggan/task menyapu riwayat penjualannya.
     */
    public function up(): void
    {
        Schema::create('inventory_serial_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_serial_id')->constrained('inventory_serials')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('fop_task_id')->nullable()->constrained('fop_tasks')->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_serial_sales');
    }
};
