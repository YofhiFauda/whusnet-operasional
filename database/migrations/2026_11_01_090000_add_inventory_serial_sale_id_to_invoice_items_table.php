<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penaut opsional baris "Jual Modem" (jalur Pemasangan) ke
     * `inventory_serial_sales` — rancangan 2026-10-31, `docs/plan/warehouse/
     * rancangan-modem-terjual-visibilitas-dan-rekonsiliasi.md` §5.
     *
     * Sebelum ini baris `jual_modem` di `invoice_items` tidak menunjuk sale
     * mana pun — mencocokkan lewat pelanggan + subkategori meleset kalau satu
     * pelanggan beli modem dua kali. Kolom ini dipakai laporan rekonsiliasi
     * "SN terjual belum ditagih" sebagai penaut pasti, sejalan
     * `task_billing_items.inventory_serial_sale_id` yang sudah ada untuk
     * jalur C-REQ/MTN.
     *
     * `restrictOnDelete` — selaras aturan FK induk → invoices/invoice_items
     * (lihat CLAUDE.md §Billing): sale yang sudah ditagih tidak boleh
     * terhapus diam-diam.
     *
     * Data lama TIDAK di-backfill — baris lama dicocokkan laporan lewat
     * fallback (pelanggan + subkategori), ditandai berbeda dari pencocokan
     * pasti lewat kolom ini.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('inventory_serial_sale_id')->nullable()
                ->after('description')
                ->constrained('inventory_serial_sales')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_serial_sale_id');
        });
    }
};
