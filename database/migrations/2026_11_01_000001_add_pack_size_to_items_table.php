<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konversi Pack→pcs (mis. 1 Pack RJ45 = 50 pcs) tetap di master barang —
     * bukan diinput ulang tiap Barang Masuk/Keluar, sama pola dengan
     * `meter_per_roll` (konversi Roll→Meter). Nullable — cuma relevan buat
     * item `tracking_type=quantity` (lihat `ItemController::validateItem()`).
     *
     * Beda dari `meter_per_roll`: angka ini TIDAK di-snapshot ke baris stok
     * manapun. Konversi tampil belakangan lewat `entered_pack_qty` di
     * `inventory_transactions` (jumlah Pack yang diketik staf saat
     * transaksi) — jadi kalau admin ubah `pack_size` di master nanti,
     * histori transaksi lama tetap terbaca utuh tanpa perlu baca ulang
     * kolom ini.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('pack_size', 10, 2)->nullable()->after('meter_per_roll');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('pack_size');
        });
    }
};
