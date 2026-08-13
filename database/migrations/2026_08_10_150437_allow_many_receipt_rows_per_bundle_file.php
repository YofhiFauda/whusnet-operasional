<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu LEMBAR cetak memuat banyak kwitansi.
     *
     * `receipt-print.blade.php` mencetak grid 2 kolom bergaris putus-putus —
     * satu halaman A4 berisi 8 kwitansi untuk digunting. Admin yang menekan
     * Print lalu "Save as PDF" menghasilkan SATU berkas untuk BANYAK
     * pembayaran, dan itulah bentuk yang dipakai untuk verifikasi massal.
     *
     * `checksum` unique global menolak bentuk itu: baris kedua untuk lembar
     * yang sama langsung ditolak database, sehingga tujuh kwitansi lain tak
     * pernah bisa tercatat. Kuncinya digeser jadi (checksum, payment_id) —
     * satu baris per (lembar, pembayaran).
     *
     * Maksud asli indeks lama tetap dijaga: unggah ulang berkas identik tidak
     * melahirkan baris menganggur kedua. Itu ditegakkan
     * PaymentReceiptService::store() yang mencari checksum lebih dulu dan
     * mengembalikan baris yang sudah ada — bukan oleh indeks ini, karena
     * `payment_id` yang masih NULL dianggap berbeda satu sama lain oleh MySQL
     * maupun SQLite.
     */
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropUnique(['checksum']);
            $table->unique(['checksum', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropUnique(['checksum', 'payment_id']);
            $table->unique(['checksum']);
        });
    }
};
